<?php

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Renombra el estado/bucket `autocierre` → `autoarchivo` en MailDispatch.
 *
 * El nombre "autocierre" se libera para otro uso futuro. Este cambio toca datos
 * vivos, así que se hace en pasos seguros e idempotentes:
 *   1. Amplía el ENUM de `maildispatch_conversations.status` para incluir AMBOS
 *      valores temporalmente.
 *   2. Migra las filas existentes `autocierre` → `autoarchivo` (conversaciones)
 *      y el historial de la bitácora (`maildispatch_events.from_value/to_value`,
 *      que son VARCHAR).
 *   3. Reduce el ENUM para quitar el valor viejo.
 *
 * El código de la app ya usa `autoarchivo` en todos sus puntos.
 */
class RenameAutocierreToAutoarchivo extends Migration
{
    private const ENUM_BOTH = "ENUM('nueva','asignada','en_atencion','respondida','esperando_agente','autocierre','autoarchivo','cerrada')";
    private const ENUM_NEW  = "ENUM('nueva','asignada','en_atencion','respondida','esperando_agente','autoarchivo','cerrada')";
    private const ENUM_OLD  = "ENUM('nueva','asignada','en_atencion','respondida','esperando_agente','autocierre','cerrada')";

    public function up(): void
    {
        $db = $this->db;

        // 1. Ampliar enum (ambos valores) para poder actualizar sin error.
        $db->query('ALTER TABLE maildispatch_conversations MODIFY COLUMN status ' . self::ENUM_BOTH . " NOT NULL DEFAULT 'nueva'");

        // 2. Migrar filas y bitácora.
        $db->query("UPDATE maildispatch_conversations SET status = 'autoarchivo' WHERE status = 'autocierre'");
        $db->query("UPDATE maildispatch_events SET from_value = 'autoarchivo' WHERE from_value = 'autocierre'");
        $db->query("UPDATE maildispatch_events SET to_value = 'autoarchivo' WHERE to_value = 'autocierre'");

        // 3. Reducir enum al valor nuevo.
        $db->query('ALTER TABLE maildispatch_conversations MODIFY COLUMN status ' . self::ENUM_NEW . " NOT NULL DEFAULT 'nueva'");
    }

    public function down(): void
    {
        $db = $this->db;

        $db->query('ALTER TABLE maildispatch_conversations MODIFY COLUMN status ' . self::ENUM_BOTH . " NOT NULL DEFAULT 'nueva'");
        $db->query("UPDATE maildispatch_conversations SET status = 'autocierre' WHERE status = 'autoarchivo'");
        $db->query("UPDATE maildispatch_events SET from_value = 'autocierre' WHERE from_value = 'autoarchivo'");
        $db->query("UPDATE maildispatch_events SET to_value = 'autocierre' WHERE to_value = 'autoarchivo'");
        $db->query('ALTER TABLE maildispatch_conversations MODIFY COLUMN status ' . self::ENUM_OLD . " NOT NULL DEFAULT 'nueva'");
    }
}
