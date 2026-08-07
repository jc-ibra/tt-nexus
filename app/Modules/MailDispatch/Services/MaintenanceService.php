<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use CodeIgniter\Database\BaseConnection;

/**
 * Danger-zone maintenance for MailDispatch: wipes the operational data that
 * makes up the Nexus inbox (conversations, messages, attachments, thread refs,
 * audit events, sync history and cursors) WITHOUT touching any configuration
 * (settings, agents, dispositions, templates, rules, signatures, autogen rules)
 * and WITHOUT ever touching the real mailbox — the sync only ever reads it.
 *
 * Two modes:
 *  - purgeAll():         full reset of the queue; also clears the sync cursor so
 *                        the next run re-pulls from the configured cutoff.
 *  - purgeBefore($date): prunes only conversations with no activity since $date;
 *                        keeps recent threads and does NOT touch the cursor.
 */
class MaintenanceService
{
    /** Operational tables, child → parent (FK-safe delete order). */
    private const DATA_TABLES = [
        'maildispatch_attachments',
        'maildispatch_message_refs',
        'maildispatch_events',
        'maildispatch_messages',
        'maildispatch_conversations',
        'maildispatch_sync_runs',
    ];

    /** Attachment files live here, under WRITEPATH. */
    private const ATTACH_DIR = 'maildispatch/attachments';

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Full wipe of the operational data. Truncates every data table (resetting
     * auto-increment), clears the sync cursor so the next sync starts fresh from
     * the cutoff, and removes all attachment files from disk.
     */
    public function purgeAll(): ServiceResult
    {
        $counts = [];
        foreach (self::DATA_TABLES as $t) {
            $counts[$t] = $this->db->table($t)->countAllResults();
        }
        $convCount = $counts['maildispatch_conversations'] ?? 0;

        $this->db->disableForeignKeyChecks();
        try {
            foreach (self::DATA_TABLES as $t) {
                $this->db->table($t)->truncate();
            }
            // Reset cursors so the next run re-pulls from the configured cutoff.
            $this->db->table('maildispatch_sync_state')->truncate();
        } finally {
            $this->db->enableForeignKeyChecks();
        }

        $this->deleteAttachmentTree();

        return ServiceResult::ok(
            $counts,
            sprintf('Bandeja limpiada: %d conversación(es) y sus mensajes, adjuntos e historial eliminados. El cursor de sincronización se reinició.', $convCount)
        );
    }

    /**
     * Prunes conversations whose last activity is strictly before $cutoff
     * ('Y-m-d H:i:s'). Deletes their messages, attachments (rows + files), thread
     * refs and events. Leaves the sync cursor and sync-run history untouched.
     */
    public function purgeBefore(string $cutoff): ServiceResult
    {
        $ts = strtotime($cutoff);
        if ($ts === false) {
            return ServiceResult::fail('La fecha de corte no es válida.');
        }
        $cutoff = date('Y-m-d H:i:s', $ts);

        $convIds = array_map(
            static fn ($r) => (int) $r['id'],
            $this->db->table('maildispatch_conversations')
                ->select('id')
                ->where('last_activity_at <', $cutoff)
                ->get()->getResultArray()
        );

        if ($convIds === []) {
            return ServiceResult::ok(['conversations' => 0], 'No hay conversaciones anteriores a esa fecha.');
        }

        $msgIds = array_map(
            static fn ($r) => (int) $r['id'],
            $this->db->table('maildispatch_messages')
                ->select('id')->whereIn('conversation_id', $convIds)
                ->get()->getResultArray()
        );

        // Remove attachment files first (rows are deleted with the table sweep).
        $this->deleteAttachmentFilesForConversations($convIds);

        $this->db->disableForeignKeyChecks();
        try {
            $this->db->table('maildispatch_attachments')->whereIn('conversation_id', $convIds)->delete();
            if ($msgIds !== []) {
                $this->db->table('maildispatch_message_refs')->whereIn('message_id', $msgIds)->delete();
            }
            $this->db->table('maildispatch_events')->whereIn('conversation_id', $convIds)->delete();
            $this->db->table('maildispatch_messages')->whereIn('conversation_id', $convIds)->delete();
            $this->db->table('maildispatch_conversations')->whereIn('id', $convIds)->delete();
        } finally {
            $this->db->enableForeignKeyChecks();
        }

        return ServiceResult::ok(
            ['conversations' => count($convIds), 'messages' => count($msgIds)],
            sprintf('Se eliminaron %d conversación(es) anteriores a %s (%d mensaje(s)). El cursor de sincronización no se tocó.', count($convIds), $cutoff, count($msgIds))
        );
    }

    // -----------------------------------------------------------------------
    // Filesystem helpers
    // -----------------------------------------------------------------------

    /** Recursively removes the whole attachments directory tree. */
    private function deleteAttachmentTree(): void
    {
        $base = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . self::ATTACH_DIR;
        if (! is_dir($base)) {
            return;
        }
        $this->rrmdir($base, false);
    }

    /** Deletes the on-disk files for the attachments of the given conversations. */
    private function deleteAttachmentFilesForConversations(array $convIds): void
    {
        $rows = $this->db->table('maildispatch_attachments')
            ->select('storage_path')
            ->whereIn('conversation_id', $convIds)
            ->get()->getResultArray();

        $base = realpath(rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . self::ATTACH_DIR);
        if ($base === false) {
            return;
        }
        foreach ($rows as $r) {
            $rel = (string) ($r['storage_path'] ?? '');
            if ($rel === '') {
                continue;
            }
            $abs = realpath(WRITEPATH . $rel);
            // Guard against traversal: only delete inside the attachments base.
            if ($abs !== false && strncmp($abs, $base, strlen($base)) === 0 && is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    /** Recursively deletes a directory's contents (and the dir itself if $self). */
    private function rrmdir(string $dir, bool $self = true): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->rrmdir($path, true);
            } else {
                @unlink($path);
            }
        }
        if ($self) {
            @rmdir($dir);
        }
    }
}
