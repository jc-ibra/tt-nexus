<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

/**
 * Lista blanca por regla (maildispatch_autogen_whitelist). La autogestión solo
 * dispara si el remitente (type 'sender') o el destinatario (type 'recipient')
 * del correo coincide con una entrada activa de la regla. Es obligatoria.
 */
class AutogenWhitelistModel extends Model
{
    protected $table         = 'maildispatch_autogen_whitelist';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = ['rule_id', 'type', 'value', 'is_active', 'created_at'];

    /** Active whitelist entries for a rule. */
    public function forRule(int $ruleId): array
    {
        return $this->where('rule_id', $ruleId)->where('is_active', 1)->findAll();
    }

    /** Active entries for many rules at once, grouped by rule_id. */
    public function activeByRule(array $ruleIds): array
    {
        if ($ruleIds === []) {
            return [];
        }
        $rows = $this->whereIn('rule_id', $ruleIds)->where('is_active', 1)->findAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['rule_id']][] = $r;
        }
        return $out;
    }
}
