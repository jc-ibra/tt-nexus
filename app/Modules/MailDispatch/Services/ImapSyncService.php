<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\SyncRunModel;
use App\Modules\MailDispatch\Models\SyncStateModel;

/**
 * Orchestrates the IMAP sync, the plain-IMAP counterpart of MailboxSyncService.
 * Pulls new messages from a single IMAP folder (which receives both inbound
 * customer mail and copies of agent replies via a forwarding rule), page by
 * page with a persisted UID cursor, and hands each one — normalized to the
 * Graph shape by ImapMailService — to ConversationService::ingest.
 *
 * Direction ('in'/'out') is derived by ConversationService::extract from the
 * From header against the logical mailbox address, exactly as in Graph mode, so
 * agent replies that arrive here still advance the conversation to "respondida".
 *
 * Idempotent (dedupe by message id), resumable (UID cursor persisted), and
 * defensive: a bad message never aborts the run.
 */
class ImapSyncService
{
    /** Sync-state folder key for the IMAP provider (distinct from graph rows). */
    private const STATE_FOLDER = 'imap';

    /** Safety cap on pages per run (avoids runaway loops). */
    private const MAX_PAGES = 200;

    public function __construct(
        private MailDispatchSettings $settings,
        private ConversationService $conversations,
        private SyncStateModel $state,
        private SyncRunModel $runs
    ) {}

    /**
     * @param string        $trigger 'cron' | 'manual' | 'test'
     * @param bool          $full    ignore the stored UID cursor (full resync)
     * @param callable|null $log     optional line logger for CLI/debug
     */
    public function sync(string $trigger = 'cron', bool $full = false, ?callable $log = null): ServiceResult
    {
        $log ??= static fn(string $s) => null;

        if (! $this->settings->isConfigured()) {
            return ServiceResult::fail('MailDispatch (IMAP) no está configurado (host, usuario, contraseña o buzón faltantes).');
        }
        if (! $this->settings->isSyncEnabled() && $trigger === 'cron') {
            return ServiceResult::ok(['skipped' => true], 'Sincronización deshabilitada en la configuración.');
        }

        $mailbox = $this->settings->mailbox();
        $imap    = service('imapMailService');
        $started = microtime(true);

        $totals = ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

        $stateRow = $this->state->forMailbox($mailbox, self::STATE_FOLDER);
        $cursor   = $full ? null : (string) ($stateRow['delta_link'] ?? '');

        $errorMessage = '';
        $pages        = 0;
        $prevCursor   = $cursor;

        while ($pages < self::MAX_PAGES) {
            $pages++;
            $log("Página {$pages} (cursor: " . ($cursor ?: 'inicio') . ')');

            $page = $imap->fetchPage($cursor ?: null, $this->settings->pageSize(), $full && $pages === 1);
            if (! $page['success']) {
                $totals['errors']++;
                $errorMessage = (string) $page['error'];
                $log('  error: ' . $errorMessage);
                break;
            }

            $count = count($page['messages']);
            foreach ($page['messages'] as $m) {
                try {
                    $outcome = $this->conversations->ingest($m, $mailbox, 'inbox');
                    $totals['processed']++;
                    if ($outcome === 'created') {
                        $totals['created']++;
                    } elseif ($outcome === 'appended') {
                        $totals['updated']++;
                    }
                } catch (\Throwable $e) {
                    $totals['errors']++;
                    log_message('error', '[ImapSync] ingest failed: ' . $e->getMessage());
                }
            }

            $cursor = (string) ($page['cursor'] ?? '');

            // Persist progress after each clean page so the run is resumable.
            if ($totals['errors'] === 0 && $cursor !== '') {
                $this->state->saveDelta((int) $stateRow['id'], $cursor);
            }

            // Stop when the page was not full (no more new messages) or the
            // cursor did not advance (defensive against loops).
            if ($count < $this->settings->pageSize() || $cursor === $prevCursor) {
                break;
            }
            $prevCursor = $cursor;
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
        if ($errorMessage !== '') {
            $summary .= ' ' . $errorMessage;
        }

        $this->state->update((int) $stateRow['id'], [
            'last_run_at'     => date('Y-m-d H:i:s'),
            'last_result'     => $ok ? 'ok' : 'error',
            'last_message'    => $ok ? "{$totals['processed']} mensaje(s) procesado(s)." : $errorMessage,
            'processed_count' => $totals['processed'],
            'error_count'     => $totals['errors'],
        ]);

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
}
