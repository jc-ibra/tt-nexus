<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

/**
 * Reglas de Autogestión (maildispatch_autogen_rules). Un correo entrante que
 * cumple el asunto de una regla activa y cuyo remitente/destinatario está en su
 * lista blanca crea un ticket GLPI automáticamente.
 */
class AutogenRuleModel extends Model
{
    protected $table         = 'maildispatch_autogen_rules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name', 'is_active', 'sort_order',
        'subject_pattern', 'subject_match_mode', 'recipient_pattern',
        'glpi_ticket_type', 'glpi_category_id', 'glpi_entities_id',
        'glpi_requester_user_id', 'request_source_id', 'container_ids',
        'field_map', 'reply_subject', 'reply_body', 'ai_enabled',
    ];

    /** Active rules ordered as the ingest matcher evaluates them. */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('sort_order, id', 'ASC')->findAll();
    }

    /** All rules for the admin editor. */
    public function allOrdered(): array
    {
        return $this->orderBy('sort_order, id', 'ASC')->findAll();
    }
}
