<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

/**
 * Fuente de tickets GLPI agnóstica al origen.
 *
 * Implementaciones:
 *   - GlpiCsvSource: lee CSV/XLSX subido por el admin (fase 2).
 *   - GlpiApiSource: stub para fase futura (conexión directa a GLPI).
 */
interface GlpiTicketSourceInterface
{
    /**
     * Itera sobre los tickets normalizados de la fuente.
     *
     * Cada elemento es un array con las claves lógicas de
     * App\Modules\KPIsOperativos\Config\GlpiSchema::COLUMNS:
     *   glpi_id, titulo, estado, fecha_apertura, fecha_cierre,
     *   regional, estado_geo, municipio, proyecto, categoria,
     *   solicitud, idc, urgencia, impacto, sucursal, cliente.
     *
     * Fechas: DateTime o null. Strings: ya trimmed; valores ausentes vienen como ''.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function tickets(): iterable;

    /**
     * Aplica filtro por rango de fechas (sobre fecha_apertura).
     * Si ambos son null no filtra.
     */
    public function setDateRange(?\DateTimeInterface $from, ?\DateTimeInterface $to): void;

    /**
     * Etiqueta legible del origen para logs/diagnóstico.
     */
    public function describe(): string;
}
