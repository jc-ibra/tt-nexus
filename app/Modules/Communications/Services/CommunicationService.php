<?php

namespace App\Modules\Communications\Services;

use App\Modules\Communications\Models\CommunicationLogModel;
use App\Modules\Communications\Models\CommunicationModel;
use App\Modules\Communications\Models\RecipientListModel;
use App\Modules\Core\Services\ServiceResult;

class CommunicationService
{
    public function __construct(
        private CommunicationModel    $model,
        private CommunicationLogModel $logModel,
        private RecipientListModel    $listModel,
    ) {}

    public function paginate(int $perPage = 20): array
    {
        $page = (int) ($_GET['page'] ?? 1);

        return $this->model->paginateWithCounts($perPage, $page);
    }

    public function total(): int
    {
        return $this->model->countAll();
    }

    public function findById(int $id): ?array
    {
        return $this->model->findWithLists($id);
    }

    public function create(array $data): ServiceResult
    {
        $listIds = array_map('intval', (array) ($data['list_ids'] ?? []));
        unset($data['list_ids']);

        $data['created_by'] = session()->get('user_id');
        $data['status']     = 'draft';

        $this->model->skipValidation(false);

        if (! $this->model->insert($data)) {
            return ServiceResult::fail($this->model->errors());
        }

        $id = $this->model->getInsertID();
        $this->model->syncLists($id, $listIds);

        return ServiceResult::ok($this->findById($id), 'Comunicado creado.');
    }

    public function update(int $id, array $data): ServiceResult
    {
        $comm = $this->model->find($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        if (in_array($comm['status'], ['sending', 'sent'])) {
            return ServiceResult::fail('No se puede editar un comunicado que ya fue enviado.');
        }

        $listIds = array_map('intval', (array) ($data['list_ids'] ?? []));
        unset($data['list_ids']);

        $this->model->skipValidation(false);

        if (! $this->model->update($id, $data)) {
            return ServiceResult::fail($this->model->errors());
        }

        $this->model->syncLists($id, $listIds);

        return ServiceResult::ok($this->findById($id), 'Comunicado actualizado.');
    }

    public function saveDraft(int $id, array $data): ServiceResult
    {
        $comm = $this->model->find($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        $listIds = array_map('intval', (array) ($data['list_ids'] ?? []));
        unset($data['list_ids']);
        $data['status'] = 'draft';

        $this->model->skipValidation(true);
        $this->model->update($id, $data);
        $this->model->syncLists($id, $listIds);

        return ServiceResult::ok($this->findById($id), 'Borrador guardado.');
    }

    public function destroy(int $id): ServiceResult
    {
        $comm = $this->model->find($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        if ($comm['status'] === 'sending') {
            return ServiceResult::fail('No se puede eliminar un comunicado mientras se está enviando.');
        }

        $this->model->delete($id);

        return ServiceResult::ok(null, 'Comunicado eliminado.');
    }

    public function renderPreview(int $id): ServiceResult
    {
        $comm = $this->findById($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        $html = $this->buildEmailHtml($comm['subject'], $comm['body']);

        return ServiceResult::ok(['html' => $html]);
    }

    public function buildEmailHtml(string $subject, string $body): string
    {
        $template = file_get_contents(APPPATH . 'Modules/Communications/Views/emails/base.php');

        return str_replace(
            ['{{SUBJECT}}', '{{BODY}}', '{{BASE_URL}}'],
            [htmlspecialchars($subject, ENT_QUOTES), $this->injectInlineWordBreak($body), base_url()],
            $template
        );
    }

    /**
     * Prepare the editor HTML so that mid-word breaks cannot happen in any
     * client. Three independent defenses:
     *   1) Strip invisible break-opportunity characters (soft hyphen, ZWSP,
     *      ZWJ, ZWNJ, word-joiner) that get pasted in from Word/PDF sources
     *      and cause renderers to split words at their position.
     *   2) Auto-linkify plain-text URLs so URL breaking is handled by <a>
     *      styling and we never need overflow-wrap:break-word on body text.
     *   3) Inline word-break:normal + overflow-wrap:normal on every block
     *      element — survives clients that strip the <style> block.
     */
    private function injectInlineWordBreak(string $html): string
    {
        // Replace non-breaking spaces with regular spaces so the renderer has
        // word-break opportunities. Pasted content from Word/Google Docs uses
        // these everywhere and makes whole sentences appear as one unbreakable
        // string (causing overflow or mid-word breaks).
        $html = str_replace(['&nbsp;', '&#160;', '&#xA0;', '&#xa0;'], ' ', $html);
        $html = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $html);

        // Strip zero-width characters that create phantom break opportunities
        // inside words (soft hyphen, ZWSP, ZWJ, ZWNJ, word-joiner, BOM).
        $html = preg_replace(
            '/[\x{00AD}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u',
            '',
            $html
        );

        $html = $this->autolinkUrls($html);

        $blockStyle = 'word-break:normal;overflow-wrap:normal;-webkit-hyphens:manual;hyphens:manual';
        $linkStyle  = 'word-break:break-word;overflow-wrap:anywhere';

        $blockTags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'div', 'span', 'strong', 'em', 'b', 'i', 'u'];

        foreach ($blockTags as $tag) {
            $html = preg_replace_callback(
                '/<' . $tag . '\b([^>]*)>/i',
                static function ($m) use ($tag, $blockStyle) {
                    $attrs = $m[1];
                    if (preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/i', $attrs, $sm)) {
                        $existing = rtrim($sm[2], '; ');
                        $newAttrs = preg_replace(
                            '/\sstyle\s*=\s*(["\']).*?\1/i',
                            ' style="' . $existing . ';' . $blockStyle . '"',
                            $attrs,
                            1
                        );
                        return '<' . $tag . $newAttrs . '>';
                    }
                    return '<' . $tag . $attrs . ' style="' . $blockStyle . '">';
                },
                $html
            );
        }

        $html = preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function ($m) use ($linkStyle) {
                $attrs = $m[1];
                if (preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/i', $attrs, $sm)) {
                    $existing = rtrim($sm[2], '; ');
                    $newAttrs = preg_replace(
                        '/\sstyle\s*=\s*(["\']).*?\1/i',
                        ' style="' . $existing . ';' . $linkStyle . '"',
                        $attrs,
                        1
                    );
                    return '<a' . $newAttrs . '>';
                }
                return '<a' . $attrs . ' style="' . $linkStyle . '">';
            },
            $html
        );

        return $html;
    }

    /**
     * Wrap bare URLs (not already inside an <a> tag) with proper anchors so
     * the <a> styling handles their wrapping. Skips content inside existing
     * <a>...</a> blocks and inside attributes.
     */
    private function autolinkUrls(string $html): string
    {
        $parts  = preg_split('/(<a\b[^>]*>.*?<\/a>|<[^>]+>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        foreach ($parts as $part) {
            if ($part === '' || $part === null) {
                continue;
            }
            if ($part[0] === '<') {
                $result .= $part;
                continue;
            }
            $result .= preg_replace_callback(
                '/(https?:\/\/[^\s<>"\']+)/i',
                static function ($m) {
                    $url = $m[1];
                    $url = rtrim($url, '.,;:!?)');
                    $trail = substr($m[1], strlen($url));
                    return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener">'
                        . htmlspecialchars($url) . '</a>' . htmlspecialchars($trail);
                },
                $part
            );
        }
        return $result;
    }

    public function getLogs(int $id, int $perPage = 50): array
    {
        return [
            'logs'  => $this->logModel->getForCommunication($id, $perPage),
            'stats' => $this->logModel->getStats($id),
            'pager' => $this->logModel->pager,
        ];
    }

    public function queueForSending(int $id): ServiceResult
    {
        $comm = $this->findById($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        if (! in_array($comm['status'], ['draft', 'failed'])) {
            return ServiceResult::fail('Solo los comunicados en borrador o fallidos pueden enviarse.');
        }

        if (empty($comm['list_ids'])) {
            return ServiceResult::fail('El comunicado no tiene listas asignadas.');
        }

        // Collect recipients from all assigned lists, deduplicated by email
        $seen       = [];
        $recipients = [];

        foreach ($comm['list_ids'] as $listId) {
            foreach ($this->listModel->getRecipients((int) $listId) as $r) {
                if ($r['status'] !== 'active') {
                    continue;
                }
                $email = strtolower(trim($r['email']));
                if (! isset($seen[$email])) {
                    $seen[$email]   = true;
                    $recipients[]   = $r;
                }
            }
        }

        if (empty($recipients)) {
            return ServiceResult::fail('No hay destinatarios activos en las listas asignadas.');
        }

        // Delete any existing queued logs for a clean re-queue on retry
        $this->logModel->where('communication_id', $id)->where('status', 'queued')->delete();

        $count = $this->logModel->bulkInsertForCommunication($id, $recipients);

        $this->model->skipValidation(true)->update($id, ['status' => 'queued']);

        return ServiceResult::ok(
            ['queued' => $count],
            "{$count} destinatario(s) añadidos a la cola."
        );
    }

    public function getListsForSelect(): array
    {
        return $this->listModel->getAll();
    }
}
