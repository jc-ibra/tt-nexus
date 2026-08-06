<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Autogestión: reglas que crean automáticamente un ticket GLPI a partir de un
 * correo entrante (asunto + lista blanca obligatoria + cuerpo `Campo: valor`).
 *
 * Crea:
 *  - maildispatch_autogen_rules      (reglas administradas por el SuperAdmin)
 *  - maildispatch_autogen_whitelist  (remitentes/destinatarios permitidos por regla)
 * y agrega a maildispatch_conversations las columnas del ciclo de autogestión.
 *
 * Siembra una regla de EJEMPLO (activa) + su lista blanca placeholder. Como el
 * toggle global `autogestion_enabled` viene apagado por defecto, no dispara nada
 * hasta que el admin lo active y ajuste la regla.
 */
class CreateMailDispatchAutogenTables extends Migration
{
    public function up(): void
    {
        // --- Reglas ---------------------------------------------------------
        if (! $this->db->tableExists('maildispatch_autogen_rules')) {
            $this->forge->addField([
                'id'                     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'                   => ['type' => 'VARCHAR', 'constraint' => 150],
                'is_active'              => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort_order'             => ['type' => 'INT', 'default' => 0],
                // Disparo
                'subject_pattern'        => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'subject_match_mode'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'contains'], // contains|exact
                'recipient_pattern'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                // Destino GLPI (null = hereda el default global del módulo)
                'glpi_ticket_type'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'glpi_category_id'       => ['type' => 'INT', 'null' => true],
                'glpi_entities_id'       => ['type' => 'INT', 'null' => true],
                'glpi_requester_user_id' => ['type' => 'INT', 'null' => true],
                'request_source_id'      => ['type' => 'INT', 'null' => true],
                'container_ids'          => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                // Mapeo de campos del cuerpo (JSON: [{label,target,required}])
                'field_map'              => ['type' => 'TEXT', 'null' => true],
                // Plantilla de respuesta
                'reply_subject'          => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'reply_body'             => ['type' => 'TEXT', 'null' => true],
                'ai_enabled'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at'             => ['type' => 'DATETIME', 'null' => true],
                'updated_at'             => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('is_active');
            $this->forge->createTable('maildispatch_autogen_rules');
        }

        // --- Lista blanca ---------------------------------------------------
        if (! $this->db->tableExists('maildispatch_autogen_whitelist')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'rule_id'    => ['type' => 'INT', 'unsigned' => true],
                'type'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'sender'], // sender|recipient
                'value'      => ['type' => 'VARCHAR', 'constraint' => 255], // correo o @dominio
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('rule_id');
            $this->forge->createTable('maildispatch_autogen_whitelist');
        }

        // --- Columnas del ciclo de autogestión en conversaciones ------------
        $cols = [];
        $add = function (string $name, array $def) use (&$cols) {
            if (! $this->db->fieldExists($name, 'maildispatch_conversations')) {
                $cols[$name] = $def;
            }
        };
        $add('autogen_rule_id',        ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'auto_rule_id']);
        $add('auto_ticket_id',         ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'autogen_rule_id']);
        $add('autogen_state',          ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'auto_ticket_id']); // pending|created|review|failed
        $add('autogen_payload',        ['type' => 'TEXT', 'null' => true, 'after' => 'autogen_state']);
        $add('autogen_attempts',       ['type' => 'INT', 'default' => 0, 'after' => 'autogen_payload']);
        $add('autogen_error',          ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'autogen_attempts']);
        $add('autogen_reply_sent',     ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'autogen_error']);
        $add('autogen_next_attempt_at', ['type' => 'DATETIME', 'null' => true, 'after' => 'autogen_reply_sent']);
        if ($cols !== []) {
            $this->forge->addColumn('maildispatch_conversations', $cols);
        }

        // --- Regla de ejemplo (inofensiva: autogestion_enabled viene en 0) --
        if ($this->db->table('maildispatch_autogen_rules')->countAllResults() === 0) {
            $fieldMap = json_encode([
                ['label' => 'Título',      'target' => 'title',       'required' => true],
                ['label' => 'Descripción', 'target' => 'description', 'required' => true],
                ['label' => 'Cliente',     'target' => 'description', 'required' => false],
                ['label' => 'Sucursal',    'target' => 'description', 'required' => false],
            ], JSON_UNESCAPED_UNICODE);

            $now = date('Y-m-d H:i:s');
            $this->db->table('maildispatch_autogen_rules')->insert([
                'name'               => 'Ejemplo · Solicitud de ticket',
                'is_active'          => 1,
                'sort_order'         => 1,
                'subject_pattern'    => 'SOLICITUD DE TICKET',
                'subject_match_mode' => 'contains',
                'glpi_ticket_type'   => 'INCIDENCIA',
                'field_map'          => $fieldMap,
                'reply_subject'      => 'Ticket generado: #{{ticket_id}}',
                'reply_body'         => "Hola,\n\nHemos registrado tu solicitud con el folio #{{ticket_id}} ({{titulo}}).\n\nUn agente le dará seguimiento. Gracias.",
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
            $ruleId = (int) $this->db->insertID();
            $this->db->table('maildispatch_autogen_whitelist')->insert([
                'rule_id'    => $ruleId,
                'type'       => 'sender',
                'value'      => '@ejemplo.com',
                'is_active'  => 1,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach ([
            'autogen_next_attempt_at', 'autogen_reply_sent', 'autogen_error', 'autogen_attempts',
            'autogen_payload', 'autogen_state', 'auto_ticket_id', 'autogen_rule_id',
        ] as $col) {
            if ($this->db->fieldExists($col, 'maildispatch_conversations')) {
                $this->forge->dropColumn('maildispatch_conversations', $col);
            }
        }
        $this->forge->dropTable('maildispatch_autogen_whitelist', true);
        $this->forge->dropTable('maildispatch_autogen_rules', true);
    }
}
