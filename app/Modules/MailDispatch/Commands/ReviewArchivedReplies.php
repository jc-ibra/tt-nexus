<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Commands;

use App\Modules\MailDispatch\Services\ConversationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Retroactive sweep for the "autoarchivo" bug fixed alongside this command:
 * appendToConversation() didn't reopen an auto-triaged thread when a reply
 * arrived from someone the rules don't cover, so real replies (a customer, a
 * different agent) sat buried in the Autoarchivo bucket indefinitely. Fresh
 * mail is fixed going forward; this walks the threads that were already
 * buried before the fix existed.
 *
 * For every conversation in "autoarchivo", looks at its LAST inbound message.
 * If that sender doesn't match any currently-active autoarchivo rule, it's
 * treated as a human reply and the thread is reopened (same decision
 * ConversationService::reopenIfHumanReply() applies going forward).
 *
 *   php spark maildispatch:review-archived              # dry-run, lista todo
 *   php spark maildispatch:review-archived --apply       # aplica los cambios
 *   php spark maildispatch:review-archived --since=2026-08-01 --apply
 */
class ReviewArchivedReplies extends BaseCommand
{
    protected $group       = 'MailDispatch';
    protected $name        = 'maildispatch:review-archived';
    protected $description = 'Revisa hilos en Autoarchivo cuya última respuesta no cae en ninguna regla y los regresa a la bandeja.';
    protected $usage       = 'maildispatch:review-archived [--since=YYYY-MM-DD] [--apply]';
    protected $options     = [
        '--since' => 'Solo conversaciones cuyo último mensaje entrante llegó desde esta fecha (default: todas).',
        '--apply' => 'Aplica los cambios. Sin esta bandera solo se lista lo que se haría (dry-run).',
    ];

    public function run(array $params): void
    {
        $since = (string) (CLI::getOption('since') ?? '');
        $apply = array_key_exists('apply', $params) || CLI::getOption('apply');

        $db = Database::connect();

        // Última respuesta entrante por conversación en autoarchivo: un solo
        // JOIN contra el id máximo de mensaje 'in' por hilo, en vez de una
        // consulta por conversación.
        $builder = $db->table('maildispatch_conversations c')
            ->select('c.id, c.subject, c.agent_id, c.auto_rule_id, c.verified_at, m.from_email, m.subject AS last_subject, m.received_at')
            ->join(
                '(SELECT conversation_id, MAX(id) AS max_id FROM maildispatch_messages WHERE direction = \'in\' GROUP BY conversation_id) t',
                't.conversation_id = c.id'
            )
            ->join('maildispatch_messages m', 'm.id = t.max_id')
            ->where('c.status', 'autoarchivo');

        if ($since !== '') {
            $builder->where('m.received_at >=', $since . ' 00:00:00');
        }

        $rows = $builder->orderBy('m.received_at', 'DESC')->get()->getResultArray();

        if ($rows === []) {
            CLI::write('No hay conversaciones en Autoarchivo' . ($since !== '' ? " desde {$since}" : '') . '.', 'green');
            return;
        }

        /** @var ConversationService $conversations */
        $conversations = service('mailDispatchConversations');

        $candidates = 0;
        $reopened   = 0;

        CLI::write(sprintf('Revisando %s conversación(es) en Autoarchivo%s...', number_format(count($rows)), $apply ? '' : ' (dry-run)'), 'cyan');
        CLI::newLine();

        foreach ($rows as $r) {
            $conv = [
                'id'       => (int) $r['id'],
                'status'   => 'autoarchivo',
                'agent_id' => $r['agent_id'],
            ];
            $lastInbound = [
                'from_email' => (string) $r['from_email'],
                'subject'    => (string) $r['last_subject'],
            ];

            $wouldReopen = $conversations->reopenIfHumanReply($conv, $lastInbound, $apply);

            if (! $wouldReopen) {
                continue;
            }

            $candidates++;
            if ($apply) {
                $reopened++;
            }

            CLI::write(sprintf(
                '  #%-5d %-60s  %s  (%s)',
                (int) $r['id'],
                mb_substr((string) $r['subject'], 0, 60),
                (string) $r['received_at'],
                $r['from_email']
            ), $apply ? 'green' : 'yellow');
        }

        CLI::newLine();
        if ($apply) {
            CLI::write(sprintf('Listo: %s conversación(es) regresada(s) a la bandeja.', number_format($reopened)), 'green');
        } else {
            CLI::write(sprintf(
                '%s de %s conversación(es) se regresarían a la bandeja. Corre con --apply para aplicarlo.',
                number_format($candidates),
                number_format(count($rows))
            ), 'yellow');
        }
    }
}
