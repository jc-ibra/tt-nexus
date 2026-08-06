<?php

namespace App\Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Corrige el tipo de la columna `ci_sessions.timestamp`.
 *
 * La migración original (CreateCiSessionsTable) creó la columna como
 * `INT UNSIGNED` (esquema viejo de CI4). Desde CI4 4.5+ el DatabaseHandler
 * escribe y compara esa columna con la función SQL `now()` (un DATETIME).
 * Con la columna en INT, `now()` se coacciona a un entero de 14 dígitos que
 * desborda y se trunca a 4294967295, y el garbage collector
 * (`WHERE timestamp < now() - INTERVAL ... second`) hace match con TODAS las
 * filas → borra todas las sesiones cuando corre (logout aleatorio).
 *
 * Esta migración cambia la columna a `TIMESTAMP` (lo que espera CI4 4.5+).
 *
 * Es GUARDADA e IDEMPOTENTE: solo actúa si la columna sigue siendo `int`.
 * Si ya es `timestamp`/`datetime` (entornos ya corregidos), no hace nada.
 * Antes de convertir vacía la tabla, porque los valores enteros corruptos
 * (4294967295) no convierten a un datetime válido bajo modo estricto; las
 * sesiones son efímeras, así que limpiarlas solo obliga a re-iniciar sesión
 * una vez.
 */
class FixCiSessionsTimestampType extends Migration
{
    private function timestampColumnType(): ?string
    {
        $row = $this->db->query(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->getDatabase(), 'ci_sessions', 'timestamp'],
        )->getRow();

        return $row->DATA_TYPE ?? null;
    }

    public function up(): void
    {
        $type = $this->timestampColumnType();

        // Tabla/columna ausente, o ya corregida (timestamp/datetime): nada que hacer.
        if ($type === null || strtolower($type) !== 'int') {
            return;
        }

        // Limpia las sesiones (efímeras) con el valor entero corrupto y corrige el tipo.
        $this->db->query('TRUNCATE TABLE ci_sessions');
        $this->db->query('ALTER TABLE ci_sessions MODIFY `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        $type = $this->timestampColumnType();

        // Solo revierte si actualmente es timestamp/datetime.
        if ($type === null || strtolower($type) === 'int') {
            return;
        }

        // Rollback: vuelve al esquema INT original (reintroduce el bug con CI4 4.5+).
        $this->db->query('TRUNCATE TABLE ci_sessions');
        $this->db->query('ALTER TABLE ci_sessions MODIFY `timestamp` INT(10) UNSIGNED NOT NULL DEFAULT 0');
    }
}
