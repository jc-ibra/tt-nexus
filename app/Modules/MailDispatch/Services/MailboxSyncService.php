<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\SyncRunModel;
use App\Modules\MailDispatch\Models\SyncStateModel;

/**
 * Orchestrates the delta sync of the shared mailbox. Walks the Inbox (incoming)
 * and Sent Items (agent replies from Outlook) with per-folder delta links, and
 * hands each message to ConversationService for threading and state advancement.
 *
 * Idempotent (dedupe by Graph message id), resumable (delta link persisted per
 * folder), and defensive: a bad message or a folder error never aborts the run
 * or the operational area — it is logged and reported in the admin status panel.
 */
class MailboxSyncService
{
    /** Folders we track. Inbox for inbound; Sent Items for agent replies. */
    private const FOLDERS = ['inbox', 'sentitems'];

    /** Safety cap on pages per folder per run (avoids runaway loops). */
    private const MAX_PAGES = 200;

    public function __construct(
        private MailDispatchSettings $settings,
        private ConversationService $conversations,
        private SyncStateModel $state,
        private SyncRunModel $runs
    ) {}

    /**
     * Runs a sync pass.
     *
     * @param string        $trigger 'cron' | 'manual' | 'test'
     * @param bool          $full    ignore stored delta links (full resync)
     * @param callable|null $log     optional line logger for CLI/debug
     */
    public function sync(string $trigger = 'cron', bool $full = false, ?callable $log = null): ServiceResult
    {
        $log ??= static fn(string $s) => null;

        if (! $this->settings->isConfigured()) {
            return ServiceResult::fail('MailDispatch no está configurado (credenciales de Graph o buzón faltantes).');
        }
        if (! $this->settings->isSyncEnabled() && $trigger === 'cron') {
            return ServiceResult::ok(['skipped' => true], 'Sincronización deshabilitada en la configuración.');
        }

        $mailbox = $this->settings->mailbox();
        $graph   = service('graphMailService');
        $started = microtime(true);

        $totals = ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];
        $messages = [];

        foreach (self::FOLDERS as $folder) {
            $log("Sincronizando carpeta: {$folder}");
            $res = $this->syncFolder($graph, $mailbox, $folder, $full, $totals, $log);
            if ($res !== '') {
                $messages[] = "{$folder}: {$res}";
            }
        }

        $duration = (int) round((microtime(true) - $started) * 1000);
        $ok       = $totals['errors'] === 0;
        $summary  = sprintf(
            '%d mensaje(s): %d nueva(s), %d actualizada(s), %d error(es).',
            $totals['processed'],
            $totals['created'],
            $totals['updated'],
            $totals['errors']
        );
        if ($messages !== []) {
            $summary .= ' ' . implode(' | ', $messages);
        }

        $this->runs->insert([
            'mailbox_address' => $mailbox,
            'status'          => $ok ? 'ok' : 'error',
            'trigger'         => $trigger,
            'processed'       => $totals['processed'],
            'created'         => $totals['created'],
            'updated'         => $totals['updated'],
            'errors'          => $totals['errors'],
            'message'         => $summary,
            'duration_ms'     => $duration,
        ]);

        return $ok
            ? ServiceResult::ok($totals, $summary)
            : ServiceResult::fail($summary, $totals);
    }

    /**
     * Syncs one folder end to end. Updates $totals by reference and returns a
     * short per-folder status string ('' on clean success).
     */
    private function syncFolder($graph, string $mailbox, string $folder, bool $full, array &$totals, callable $log): string
    {
        $stateRow = $this->state->forMailbox($mailbox, $folder);
        $delta    = (string) ($stateRow['delta_link'] ?? '');
        $url      = ($full || $delta === '')
            ? $graph->initialDeltaUrl($folder, $this->settings->pageSize(), $this->settings->syncSinceUtcIso())
            : $delta;

        $folderProcessed = 0;
        $folderErrors    = 0;
        $newDelta        = null;
        $errorMessage    = '';
        $pages           = 0;

        while ($url !== null && $pages < self::MAX_PAGES) {
            $pages++;
            $page = $graph->getDeltaPage($url);

            if (! $page['success']) {
                $folderErrors++;
                $errorMessage = (string) $page['error'];
                $log("  error: {$errorMessage}");
                break;
            }

            foreach ($page['messages'] as $m) {
                // Deleted items come through delta as @removed — skip cleanly.
                if (isset($m['@removed'])) {
                    continue;
                }
                try {
                    $outcome = $this->conversations->ingest($m, $mailbox, $folder);
                    $folderProcessed++;
                    if ($outcome === 'created') {
                        $totals['created']++;
                    } elseif ($outcome === 'appended') {
                        $totals['updated']++;
                    }
                } catch (\Throwable $e) {
                    $folderErrors++;
                    log_message('error', '[MailboxSync] ingest failed: ' . $e->getMessage());
                }
            }

            if (! empty($page['nextLink'])) {
                $url = $page['nextLink'];
                continue;
            }
            if (! empty($page['deltaLink'])) {
                $newDelta = $page['deltaLink'];
            }
            $url = null;
        }

        $totals['processed'] += $folderProcessed;
        $totals['errors']    += $folderErrors;

        // Persist the new delta only on a clean folder pass.
        if ($newDelta !== null && $folderErrors === 0) {
            $this->state->saveDelta((int) $stateRow['id'], $newDelta);
        }

        $this->state->update((int) $stateRow['id'], [
            'last_run_at'     => date('Y-m-d H:i:s'),
            'last_result'     => $folderErrors === 0 ? 'ok' : 'error',
            'last_message'    => $folderErrors === 0
                ? "{$folderProcessed} mensaje(s) procesado(s)."
                : $errorMessage,
            'processed_count' => $folderProcessed,
            'error_count'     => $folderErrors,
        ]);

        return $folderErrors === 0 ? '' : $errorMessage;
    }
}
