<?php

declare(strict_types=1);

namespace App\Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Services;
use Throwable;

/**
 * Reconcilia la tabla `migrations` con el esquema real ("baseline").
 *
 * Caso de uso: una BD cuyo ESQUEMA ya existe (tablas/columnas creadas a mano o
 * por un dump parcial) pero cuya tabla `migrations` NO registra esas migraciones.
 * En ese estado `migrate` las ve como pendientes e intenta recrear objetos que ya
 * existen → "Table ... already exists" / "Duplicate column". Fue justo lo que pasó
 * en producción tras el levantamiento manual con scripts SQL.
 *
 * Qué hace, de forma SEGURA:
 *   - Descubre todas las migraciones de todos los módulos.
 *   - Para cada una que NO esté registrada en el historial, VERIFICA que su efecto
 *     ya exista en la BD (la tabla del createTable, o la columna del addColumn).
 *   - Si el efecto ya existe → inserta la fila de historial (la marca aplicada) SIN
 *     ejecutar la migración.
 *   - Si el efecto NO existe → la deja como genuinamente pendiente (no la toca);
 *     esas se aplican después con `migrate --all` normalmente.
 *   - Las que no puede verificar (ALTER/data/SQL crudo) las reporta y NO las marca.
 *
 * Nunca borra ni modifica filas existentes ni datos: solo INSERTA historial que falta.
 *
 * Uso:
 *   php spark db:baseline            # aplica el baseline
 *   php spark db:baseline --dry-run  # solo muestra qué haría, sin escribir
 */
class Baseline extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:baseline';
    protected $description = 'Marca como aplicadas las migraciones cuyo efecto ya existe en la BD (reconcilia historial desincronizado).';
    protected $usage       = 'db:baseline [--dry-run]';
    protected $options     = [
        '--dry-run' => 'Muestra el plan sin escribir en la tabla migrations.',
    ];

    public function run(array $params): void
    {
        $dryRun = array_key_exists('dry-run', CLI::getOptions());

        $db = Database::connect();

        $runner = Services::migrations();
        $runner->setNamespace(null);

        try {
            $migrations = $runner->findMigrations(); // uid => objeto (version, class, namespace, path)
        } catch (Throwable $e) {
            CLI::error('No se pudieron descubrir migraciones: ' . $e->getMessage());
            return;
        }

        if ($migrations === []) {
            CLI::error('No se encontró ninguna migración.');
            return;
        }

        // UIDs ya registrados en el historial (todos los grupos/namespaces).
        $applied = [];
        foreach ($runner->getHistory('') as $history) {
            $applied[$runner->getObjectUid($history)] = true;
        }

        try {
            $existingTables = array_flip($db->listTables());
        } catch (Throwable $e) {
            CLI::error('No se pudo listar tablas: ' . $e->getMessage());
            return;
        }

        $toBaseline   = [];  // efecto ya presente → marcar aplicada
        $genuinePend  = [];  // efecto ausente → dejar para migrate --all
        $unverifiable = [];  // no clasificable → reportar, no marcar

        foreach ($migrations as $m) {
            if (isset($applied[$m->uid])) {
                continue; // ya registrada, nada que hacer
            }

            [$kind, $present, $detail] = $this->inspectEffect($db, $existingTables, (string) $m->path);

            if ($kind === 'other') {
                $unverifiable[] = [$m, $detail];
            } elseif ($present) {
                $toBaseline[] = [$m, $detail];
            } else {
                $genuinePend[] = [$m, $detail];
            }
        }

        // ── Reporte ──────────────────────────────────────────────────────────────
        CLI::write('Migraciones descubiertas: ' . count($migrations)
            . ' · ya registradas: ' . count($applied), 'dark_gray');
        CLI::newLine();

        if ($toBaseline === [] && $genuinePend === [] && $unverifiable === []) {
            CLI::write('✓ El historial ya está sincronizado. No hay nada que reconciliar.', 'green');
            return;
        }

        if ($toBaseline !== []) {
            CLI::write('A marcar como aplicadas (su efecto YA existe en la BD):', 'yellow');
            foreach ($toBaseline as [$m, $detail]) {
                CLI::write('  + ' . $m->version . ' ' . $this->shortClass($m->class) . '  [' . $detail . ']', 'green');
            }
            CLI::newLine();
        }

        if ($genuinePend !== []) {
            CLI::write('Genuinamente pendientes (su efecto NO existe; las aplicará `migrate --all`):', 'yellow');
            foreach ($genuinePend as [$m, $detail]) {
                CLI::write('  · ' . $m->version . ' ' . $this->shortClass($m->class) . '  [falta: ' . $detail . ']', 'dark_gray');
            }
            CLI::newLine();
        }

        if ($unverifiable !== []) {
            CLI::write('No verificables automáticamente (ALTER/data/SQL crudo) — NO se marcan:', 'yellow');
            foreach ($unverifiable as [$m, $detail]) {
                CLI::write('  ? ' . $m->version . ' ' . $this->shortClass($m->class), 'dark_gray');
            }
            CLI::write('  Revísalas a mano si `migrate --all` falla en alguna.', 'dark_gray');
            CLI::newLine();
        }

        if ($toBaseline === []) {
            CLI::write('No hay migraciones "fantasma" que marcar. Corre `php spark migrate --all` para aplicar las pendientes.', 'green');
            return;
        }

        if ($dryRun) {
            CLI::write('[dry-run] No se escribió nada. Quita --dry-run para aplicar.', 'cyan');
            return;
        }

        // ── Inserción del historial faltante ──────────────────────────────────────
        $batch = $this->nextBatch($db);
        $now   = Time::now()->getTimestamp();
        $count = 0;

        foreach ($toBaseline as [$m, $detail]) {
            $db->table('migrations')->insert([
                'version'   => $m->version,
                'class'     => $m->class,
                'group'     => 'default',
                'namespace' => $m->namespace,
                'time'      => $now,
                'batch'     => $batch,
            ]);
            $count++;
        }

        CLI::newLine();
        CLI::write("✓ Baseline aplicado: {$count} migración(es) marcada(s) como aplicadas (batch {$batch}).", 'green');
        if ($genuinePend !== []) {
            CLI::write('Ahora corre `php spark migrate --all` para aplicar las ' . count($genuinePend) . ' genuinamente pendiente(s).', 'cyan');
        } else {
            CLI::write('Historial sincronizado. Verifica con `php spark db:verify-schema`.', 'cyan');
        }
    }

    /**
     * Clasifica una migración por su archivo y verifica si su efecto ya existe.
     *
     * @return array{0: string, 1: bool, 2: string}  [kind, present, detail]
     *   kind: 'table' | 'column' | 'other'
     */
    private function inspectEffect($db, array $existingTables, string $path): array
    {
        $code = (string) @file_get_contents($path);

        // createTable('x') — el efecto es que la tabla exista.
        if (preg_match("/createTable\(\s*'([a-zA-Z0-9_]+)'/", $code, $mm)) {
            $table   = $mm[1];
            $present = isset($existingTables[$table]);

            return ['table', $present, 'tabla ' . $table];
        }

        // addColumn('t', ['col' => [...], ...]) — el efecto es que la(s) columna(s) existan.
        if (preg_match("/addColumn\(\s*'([a-zA-Z0-9_]+)'/", $code, $mm)) {
            $table = $mm[1];
            // Nombres de columna: claves 'col' => [ ... ] en el archivo.
            preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*\[/", $code, $cm);
            $cols = array_values(array_unique($cm[1]));

            if (! isset($existingTables[$table])) {
                return ['column', false, 'tabla ' . $table . ' (ausente)'];
            }
            try {
                foreach ($cols as $col) {
                    if (! $db->fieldExists($col, $table)) {
                        return ['column', false, $table . '.' . $col];
                    }
                }
            } catch (Throwable $e) {
                return ['other', false, 'no verificable: ' . $e->getMessage()];
            }

            return ['column', true, $table . '.' . implode(',', $cols)];
        }

        return ['other', false, 'ALTER/data/SQL crudo'];
    }

    private function nextBatch($db): int
    {
        $row = $db->table('migrations')->selectMax('batch', 'b')->get()->getRow();

        return (int) ($row->b ?? 0) + 1;
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
