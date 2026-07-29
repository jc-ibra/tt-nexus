<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Syncs the shared mailbox into the dispatch queue via Microsoft Graph delta
 * queries. Run from cron every 1-2 minutes. A lock file prevents overlapping
 * runs; --full ignores stored delta links (controlled full resync); --debug
 * prints per-folder progress.
 *
 *   php spark maildispatch:sync-mailbox
 *   * * * * * cd /path && php spark maildispatch:sync-mailbox >> /dev/null 2>&1
 */
class SyncMailbox extends BaseCommand
{
    protected $group       = 'MailDispatch';
    protected $name        = 'maildispatch:sync-mailbox';
    protected $description = 'Sincroniza el buzón compartido (Inbox + Enviados) en la cola de despacho.';
    protected $usage       = 'maildispatch:sync-mailbox [--full] [--debug]';
    protected $options     = [
        '--full'  => 'Ignora los delta links guardados y hace una resincronización completa.',
        '--debug' => 'Muestra el progreso por carpeta.',
    ];

    public function run(array $params): void
    {
        $full  = array_key_exists('full', $params) || CLI::getOption('full');
        $debug = array_key_exists('debug', $params) || CLI::getOption('debug');

        $lock = sys_get_temp_dir() . '/maildispatch_sync.lock';
        if (is_file($lock)) {
            CLI::write('Ya hay una sincronización en curso (lock presente). Saliendo.', 'yellow');
            return;
        }
        file_put_contents($lock, (string) getmypid());
        register_shutdown_function(static fn() => @unlink($lock));

        $log = $debug
            ? static fn(string $s) => CLI::write('  ' . $s, 'dark_gray')
            : static fn(string $s) => null;

        CLI::write(sprintf('[%s] Iniciando sincronización%s…', date('H:i:s'), $full ? ' completa' : ''), 'cyan');

        $result = service('mailboxSyncService')->sync('cron', $full, $log);

        if ($result->success) {
            CLI::write('[' . date('H:i:s') . '] ' . $result->message, 'green');
        } else {
            CLI::write('[' . date('H:i:s') . '] ' . $result->message, 'red');
        }
    }
}
