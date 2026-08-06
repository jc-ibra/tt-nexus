<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega el estado `autogenerado` al enum de maildispatch_conversations.status,
 * para el bucket de tickets creados automáticamente por Autogestión.
 */
class AddAutogeneradoStatus extends Migration
{
    private const ENUM_NEW = "ENUM('nueva','asignada','en_atencion','respondida','esperando_agente','autoarchivo','autogenerado','cerrada')";
    private const ENUM_OLD = "ENUM('nueva','asignada','en_atencion','respondida','esperando_agente','autoarchivo','cerrada')";

    public function up(): void
    {
        $this->db->query('ALTER TABLE maildispatch_conversations MODIFY COLUMN status ' . self::ENUM_NEW . " NOT NULL DEFAULT 'nueva'");
    }

    public function down(): void
    {
        $this->db->query("UPDATE maildispatch_conversations SET status = 'nueva' WHERE status = 'autogenerado'");
        $this->db->query('ALTER TABLE maildispatch_conversations MODIFY COLUMN status ' . self::ENUM_OLD . " NOT NULL DEFAULT 'nueva'");
    }
}
