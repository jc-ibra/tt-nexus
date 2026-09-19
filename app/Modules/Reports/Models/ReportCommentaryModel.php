<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use CodeIgniter\Model;

class ReportCommentaryModel extends Model
{
    protected $table         = 'reports_commentary';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'snapshot_id',
        'section_key',
        'body',
        'is_ai_draft',
        'author_id',
    ];

    /** @return array<string,array<string,mixed>> keyed by section_key */
    public function forSnapshot(int $snapshotId): array
    {
        $rows = $this->where('snapshot_id', $snapshotId)->findAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['section_key']] = $r;
        }
        return $out;
    }

    /** Upserts one section's commentary (unique key snapshot_id+section_key). */
    public function upsert(int $snapshotId, string $sectionKey, string $body, bool $isAiDraft, ?int $authorId): void
    {
        $existing = $this->where('snapshot_id', $snapshotId)->where('section_key', $sectionKey)->first();
        $data     = [
            'snapshot_id' => $snapshotId,
            'section_key' => $sectionKey,
            'body'        => $body,
            'is_ai_draft' => $isAiDraft ? 1 : 0,
            'author_id'   => $authorId,
        ];
        if ($existing) {
            $this->update($existing['id'], $data);
        } else {
            $this->insert($data);
        }
    }
}
