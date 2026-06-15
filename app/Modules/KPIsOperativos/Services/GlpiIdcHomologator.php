<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Config\GlpiSchema;
use App\Modules\KPIsOperativos\Models\GlpiIdcAliasModel;
use App\Modules\KPIsOperativos\Models\GlpiIdcCanonicalModel;
use Transliterator;

/**
 * Resuelve un nombre crudo de IDC contra el catálogo canónico,
 * persistiendo aliases y aplicando fuzzy match con thresholds:
 *
 *   ≥ 92%   → auto-merge (alias creado, auto_matched=1, needs_review=0)
 *   80-92%  → auto-link al mejor candidato pero needs_review=1
 *   < 80%   → nuevo canonical (alias inicial creado)
 *
 * Mantiene cache en memoria por instancia para no recomputar el mismo
 * raw repetidas veces dentro de un import.
 */
final class GlpiIdcHomologator
{
    private const THRESHOLD_AUTO   = 92.0;
    private const THRESHOLD_REVIEW = 80.0;
    private const MIN_FUZZY_LEN    = 8;

    private GlpiIdcCanonicalModel $canonicals;
    private GlpiIdcAliasModel $aliases;
    private ?Transliterator $transliterator;

    /** @var array<string, int> raw_idc → canonical_id */
    private array $cache = [];

    /** @var list<array{id: int, normalized_form: string, canonical_name: string}> */
    private array $canonicalIndex = [];

    private bool $indexLoaded = false;

    public function __construct()
    {
        $this->canonicals = new GlpiIdcCanonicalModel();
        $this->aliases    = new GlpiIdcAliasModel();
        // Strip diacritics: NFD then remove non-spacing marks then back to NFC
        $this->transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
    }

    /**
     * Resuelve un IDC raw a un canonical_id. Crea canonical y alias si hace falta.
     *
     * Returns null si raw es vacío o "SIN ASIGNAR" (no se homologa).
     */
    public function resolve(string $rawIdc, ?int $sourceReportId = null): ?int
    {
        $raw = trim($rawIdc);
        if ($raw === '' || strcasecmp($raw, GlpiSchema::IDC_UNASSIGNED) === 0) {
            return null;
        }

        // Cache por raw (mismo string, mismo resultado dentro del import)
        if (isset($this->cache[$raw])) {
            return $this->cache[$raw];
        }

        $normalized = $this->normalize($raw);
        if ($normalized === '') {
            return null;
        }

        $this->loadIndex();

        // 1. Match exacto en aliases existentes
        $existingAlias = $this->aliases->findByNormalized($normalized);
        if ($existingAlias) {
            $id = (int) $existingAlias['canonical_id'];
            $this->cache[$raw] = $id;
            return $id;
        }

        // 2. Match exacto en canonicals (raw nunca visto pero coincide con normalized de un canonical)
        foreach ($this->canonicalIndex as $c) {
            if ($c['normalized_form'] === $normalized) {
                $this->insertAlias($c['id'], $raw, $normalized, 100.0, true, false, $sourceReportId);
                $this->cache[$raw] = $c['id'];
                return $c['id'];
            }
        }

        // 3. Fuzzy match — solo si la string es razonablemente larga
        $bestScore = 0.0;
        $bestId    = null;

        if (mb_strlen($normalized) >= self::MIN_FUZZY_LEN) {
            foreach ($this->canonicalIndex as $c) {
                if (mb_strlen($c['normalized_form']) < self::MIN_FUZZY_LEN) {
                    continue;
                }
                // Pre-filtro: longitudes muy distintas se descartan (>40% diff)
                $lenA = mb_strlen($normalized);
                $lenB = mb_strlen($c['normalized_form']);
                $lenDiff = abs($lenA - $lenB) / max($lenA, $lenB);
                if ($lenDiff > 0.4) {
                    continue;
                }

                $pct = 0.0;
                similar_text($normalized, $c['normalized_form'], $pct);
                if ($pct > $bestScore) {
                    $bestScore = $pct;
                    $bestId    = (int) $c['id'];
                }
            }
        }

        // 4. Decisión por threshold
        if ($bestId !== null && $bestScore >= self::THRESHOLD_AUTO) {
            $this->insertAlias($bestId, $raw, $normalized, $bestScore, true, false, $sourceReportId);
            $this->cache[$raw] = $bestId;
            return $bestId;
        }

        if ($bestId !== null && $bestScore >= self::THRESHOLD_REVIEW) {
            $this->insertAlias($bestId, $raw, $normalized, $bestScore, true, true, $sourceReportId);
            $this->cache[$raw] = $bestId;
            return $bestId;
        }

        // 5. Nuevo canonical
        $newId = (int) $this->canonicals->insert([
            'canonical_name'  => $raw,
            'normalized_form' => $normalized,
            'is_verified'     => 0,
        ], true);

        $this->insertAlias($newId, $raw, $normalized, null, true, false, $sourceReportId);

        $this->canonicalIndex[] = [
            'id'              => $newId,
            'normalized_form' => $normalized,
            'canonical_name'  => $raw,
        ];

        $this->cache[$raw] = $newId;
        return $newId;
    }

    /**
     * Normaliza un nombre: UPPER, sin diacríticos, sin doble espacio, trim.
     */
    public function normalize(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }

        $s = mb_strtoupper($s, 'UTF-8');

        if ($this->transliterator !== null) {
            $translit = $this->transliterator->transliterate($s);
            if (is_string($translit)) {
                $s = $translit;
            }
        }

        // Colapsa múltiples espacios y caracteres invisibles
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /**
     * Operación de admin: mergea source → target.
     * - Reasigna aliases
     * - Reasigna tickets
     * - Borra el source canonical
     */
    public function mergeCanonical(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            return;
        }

        $db = db_connect();
        $db->transStart();

        $db->table('kpi_glpi_idc_aliases')
            ->where('canonical_id', $sourceId)
            ->update(['canonical_id' => $targetId, 'needs_review' => 0]);

        $db->table('kpi_glpi_tickets')
            ->where('idc_canonical_id', $sourceId)
            ->update(['idc_canonical_id' => $targetId]);

        $db->table('kpi_glpi_idc_canonical')->where('id', $sourceId)->delete();

        $db->transComplete();

        // Invalidar cache
        $this->cache         = [];
        $this->canonicalIndex = [];
        $this->indexLoaded    = false;
    }

    private function loadIndex(): void
    {
        if ($this->indexLoaded) {
            return;
        }
        $rows = $this->canonicals
            ->select('id, normalized_form, canonical_name')
            ->findAll();

        // El driver puede devolver columnas como strings — normalizamos
        // el id a int para no romper la firma de insertAlias().
        $this->canonicalIndex = array_map(static fn($r) => [
            'id'              => (int) $r['id'],
            'normalized_form' => (string) $r['normalized_form'],
            'canonical_name'  => (string) $r['canonical_name'],
        ], $rows);

        $this->indexLoaded = true;
    }

    private function insertAlias(
        int $canonicalId,
        string $rawAlias,
        string $normalized,
        ?float $score,
        bool $autoMatched,
        bool $needsReview,
        ?int $sourceReportId
    ): void {
        // Evitar duplicar si ya existe (UNIQUE en alias_normalized previene; chequeamos antes)
        $existing = $this->aliases->where('alias_normalized', $normalized)->first();
        if ($existing) {
            return;
        }

        $this->aliases->insert([
            'canonical_id'     => $canonicalId,
            'alias_raw'        => mb_substr($rawAlias, 0, 200),
            'alias_normalized' => mb_substr($normalized, 0, 200),
            'similarity_score' => $score,
            'auto_matched'     => $autoMatched ? 1 : 0,
            'needs_review'     => $needsReview ? 1 : 0,
            'source_report_id' => $sourceReportId,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }
}
