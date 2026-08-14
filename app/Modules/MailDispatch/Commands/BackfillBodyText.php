<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Commands;

use App\Modules\MailDispatch\Models\MessageModel;
use App\Modules\MailDispatch\Services\ForwardParser;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Fills `body_text` for messages stored before the search existed.
 *
 * New mail gets it at ingestion; this walks the history. Built to run against a
 * live mailbox: it reads one bounded batch at a time (never the whole table),
 * writes it back in a single statement, and can pause between batches so the
 * disk is not saturated while agents are working.
 *
 *   php spark maildispatch:backfill-body-text                    # todo, lotes de 200
 *   php spark maildispatch:backfill-body-text --batch=100 --sleep=250
 *   php spark maildispatch:backfill-body-text --limit=1000       # una probada corta
 *   php spark maildispatch:backfill-body-text --dry-run
 *
 * Idempotent and resumable: it only picks rows whose `body_text` is still NULL,
 * so interrupting it (Ctrl-C, timeout, reboot) loses nothing. Re-run to continue.
 */
class BackfillBodyText extends BaseCommand
{
    protected $group       = 'MailDispatch';
    protected $name        = 'maildispatch:backfill-body-text';
    protected $description = 'Genera el texto plano buscable de los mensajes ya almacenados.';
    protected $usage       = 'maildispatch:backfill-body-text [--batch=200] [--sleep=0] [--limit=0] [--dry-run]';
    protected $options     = [
        '--batch'   => 'Mensajes por lote (default 200). Bájalo si el servidor va justo de RAM.',
        '--sleep'   => 'Pausa en milisegundos entre lotes (default 0). Súbelo para no saturar el disco en producción.',
        '--limit'   => 'Máximo de mensajes a procesar en esta corrida (default: todos).',
        '--dry-run' => 'Mide sin escribir nada.',
    ];

    public function run(array $params): void
    {
        $batch = max(1, $this->intOption('batch', 200));
        $sleep = max(0, $this->intOption('sleep', 0));
        $limit = max(0, $this->intOption('limit', 0));
        $dry   = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');

        $db      = Database::connect();
        $table   = 'maildispatch_messages';
        $pending = $db->table($table)->where('body_text IS NULL', null, false)->countAllResults();

        if ($pending === 0) {
            CLI::write('No hay mensajes pendientes: el texto buscable está al día.', 'green');
            return;
        }

        $target = $limit > 0 ? min($limit, $pending) : $pending;
        CLI::write(sprintf(
            'Pendientes: %s   ·   a procesar ahora: %s   ·   lote %d, pausa %d ms%s',
            number_format($pending),
            number_format($target),
            $batch,
            $sleep,
            $dry ? '   (dry-run)' : ''
        ), 'cyan');

        $done     = 0;
        $htmlSum  = 0;
        $textSum  = 0;
        $started  = microtime(true);

        while ($done < $target) {
            $take = min($batch, $target - $done);

            // Siempre la cabeza de los pendientes: como cada lote deja de ser
            // NULL, la ventana avanza sola sin necesidad de OFFSET (que en una
            // tabla grande obliga a releer todo lo ya procesado).
            $rows = $db->table($table)
                ->select('id, body')
                ->where('body_text IS NULL', null, false)
                ->orderBy('id', 'ASC')
                ->limit($take)
                ->get()->getResultArray();

            if ($rows === []) {
                break;
            }

            $updates = [];
            foreach ($rows as $r) {
                $body = (string) ($r['body'] ?? '');
                $text = $body === '' ? '' : ForwardParser::plainText($body, MessageModel::BODY_TEXT_LIMIT);
                $htmlSum += strlen($body);
                $textSum += strlen($text);
                $updates[] = ['id' => (int) $r['id'], 'body_text' => $text];
            }

            if (! $dry) {
                $db->table($table)->updateBatch($updates, 'id');
            } else {
                // Sin escritura no se vacía la cola de pendientes, así que el
                // dry-run mide un solo lote y se detiene.
                $done += count($rows);
                break;
            }

            $done += count($rows);
            CLI::write(sprintf('  %s / %s procesados', number_format($done), number_format($target)));

            if ($sleep > 0 && $done < $target) {
                usleep($sleep * 1000);
            }
        }

        $secs = max(0.001, microtime(true) - $started);
        CLI::write(sprintf(
            'Listo: %s mensajes en %.1f s (%.1f/s). HTML %.1f MB -> texto %.1f MB (%.0f%%).',
            number_format($done),
            $secs,
            $done / $secs,
            $htmlSum / 1048576,
            $textSum / 1048576,
            $htmlSum > 0 ? $textSum * 100 / $htmlSum : 0
        ), 'green');

        if (! $dry) {
            $left = $db->table($table)->where('body_text IS NULL', null, false)->countAllResults();
            if ($left > 0) {
                CLI::write(sprintf('Quedan %s pendientes. Vuelve a correr el comando para continuar.', number_format($left)), 'yellow');
            }
        }
    }

    /**
     * Reads a numeric option accepting both `--batch 200` and `--batch=200`.
     * CI4's parser only splits the first form; the second arrives as an option
     * whose *name* carries the value, and silently falling back to the default
     * is how a careful "--batch=50" ends up hammering the server with 200.
     */
    private function intOption(string $name, int $default): int
    {
        $value = CLI::getOption($name);

        if ($value === null || $value === true) {
            foreach (array_keys(CLI::getOptions()) as $key) {
                if (str_starts_with((string) $key, $name . '=')) {
                    $value = substr((string) $key, strlen($name) + 1);
                    break;
                }
            }
        }

        return $value === null || $value === true || ! is_numeric($value) ? $default : (int) $value;
    }
}
