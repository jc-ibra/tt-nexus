<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class SyncStateModel extends Model
{
    protected $table         = 'maildispatch_sync_state';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mailbox_address',
        'folder',
        'delta_link',
        'last_run_at',
        'last_result',
        'last_message',
        'processed_count',
        'error_count',
    ];

    /** Get (or lazily create) the state row for a mailbox + folder. */
    public function forMailbox(string $mailbox, string $folder = 'inbox'): array
    {
        $row = $this->where('mailbox_address', $mailbox)->where('folder', $folder)->first();
        if ($row) {
            return $row;
        }
        $id = $this->insert([
            'mailbox_address' => $mailbox,
            'folder'          => $folder,
            'last_result'     => 'never',
        ], true);
        return $this->find($id);
    }

    public function saveDelta(int $id, ?string $deltaLink): void
    {
        $this->update($id, ['delta_link' => $deltaLink]);
    }

    /** Drop the delta link to force a full resync on next run (all folders). */
    public function resetDelta(string $mailbox): void
    {
        $this->where('mailbox_address', $mailbox)->set('delta_link', null)->update();
    }
}
