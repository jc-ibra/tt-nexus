<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Controllers;

use App\Controllers\BaseController;
use App\Modules\MailDispatch\Config\MailDispatch as MailDispatchConfig;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\SyncRunModel;
use App\Modules\MailDispatch\Models\SyncStateModel;
use App\Modules\MailDispatch\Services\GraphMailService;
use App\Modules\MailDispatch\Services\MailDispatchSettings;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SuperAdmin configuration for MailDispatch: Microsoft Graph credentials,
 * shared mailbox, sync control, agent roster, disposition catalog, SLA
 * thresholds, and the sync status panel. No operational data lives here.
 */
class MailDispatchAdmin extends BaseController
{
    public function settings(): string
    {
        /** @var MailDispatchSettings $settings */
        $settings = service('mailDispatchSettings');
        $db       = \Config\Database::connect();

        // All active Nexus users, flagged with their current agent status.
        $users     = $db->table('core_users')->select('id, name, email')
            ->where('status', 'active')->orderBy('name', 'ASC')->get()->getResultArray();
        $agentRows = (new AgentModel())->allWithUsers();
        $agentMap  = [];
        foreach ($agentRows as $a) {
            $agentMap[(int) $a['user_id']] = $a;
        }

        return view('App\Modules\MailDispatch\Views\admin\settings', [
            'pageTitle'    => 'Configuración · Despacho de Correo',
            'settings'     => $settings->all(),
            'hasSecret'    => $settings->hasSecret(),
            'hasImapPassword' => $settings->hasImapPassword(),
            'hasSmtpPassword' => $settings->hasSmtpPassword(),
            'provider'     => $settings->provider(),
            'isConfigured' => $settings->isConfigured(),
            'users'        => $users,
            'agentMap'     => $agentMap,
            'dispositions' => (new DispositionModel())->allOrdered(),
            'syncRuns'     => (new SyncRunModel())->recent(12),
            'syncState'    => (new SyncStateModel())->where('mailbox_address', $settings->mailbox())->findAll(),
            'secretMask'   => MailDispatchSettings::SECRET_MASK,
        ]);
    }

    public function saveSettings(): ResponseInterface
    {
        $result = service('mailDispatchSettings')->save($this->request->getPost());
        return redirect()->to(route_to('dispatch.settings') . '#conexion')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    /**
     * Live connection test (AJAX). Uses the posted credentials, falling back to
     * the stored secret/password when the field is left masked/empty, so the
     * admin can test before saving. Branches by the selected provider. Returns
     * JSON; never echoes any secret.
     */
    public function testConnection(): ResponseInterface
    {
        /** @var MailDispatchSettings $settings */
        $settings = service('mailDispatchSettings');
        $post     = $this->request->getPost();

        $provider = strtolower(trim((string) ($post['provider'] ?? $settings->provider())));
        $result   = $provider === 'imap'
            ? $this->testImap($settings, $post)
            : $this->testGraph($settings, $post);

        return $this->response->setJSON([
            'status'  => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }

    private function testGraph(MailDispatchSettings $settings, array $post): \App\Modules\Core\Services\ServiceResult
    {
        $tenant  = trim((string) ($post['graph_tenant_id'] ?? '')) ?: $settings->tenantId();
        $client  = trim((string) ($post['graph_client_id'] ?? '')) ?: $settings->clientId();
        $mailbox = trim((string) ($post['mailbox_address'] ?? '')) ?: $settings->mailbox();

        $secret = (string) ($post['graph_client_secret'] ?? '');
        if ($secret === '' || $secret === MailDispatchSettings::SECRET_MASK) {
            $secret = $settings->clientSecret();
        }

        return (new GraphMailService($tenant, $client, $secret, $mailbox, new MailDispatchConfig()))->testConnection();
    }

    private function testImap(MailDispatchSettings $settings, array $post): \App\Modules\Core\Services\ServiceResult
    {
        $host       = trim((string) ($post['imap_host'] ?? '')) ?: $settings->imapHost();
        $port       = (int) ($post['imap_port'] ?? 0) ?: $settings->imapPort();
        $encryption = trim((string) ($post['imap_encryption'] ?? '')) ?: $settings->imapEncryption();
        $validate   = array_key_exists('imap_validate_cert', $post) ? true : $settings->imapValidateCert();
        $username   = trim((string) ($post['imap_username'] ?? '')) ?: $settings->imapUsername();
        $folder     = trim((string) ($post['imap_folder'] ?? '')) ?: $settings->imapFolder();
        $mailbox    = trim((string) ($post['mailbox_address'] ?? '')) ?: $settings->mailbox();

        $password = (string) ($post['imap_password'] ?? '');
        if ($password === '' || $password === MailDispatchSettings::SECRET_MASK) {
            $password = $settings->imapPassword();
        }

        return (new \App\Modules\MailDispatch\Services\ImapMailService(
            $host, $port, $encryption, $validate, $username, $password, $folder, $mailbox
        ))->testConnection();
    }

    public function saveAgents(): ResponseInterface
    {
        $result = service('mailDispatchSettings')->saveAgents($this->request->getPost());
        return redirect()->to(route_to('dispatch.settings') . '#agentes')
            ->with($result->success ? 'success' : 'error', $result->message);
    }

    public function saveDispositions(): ResponseInterface
    {
        $result = service('mailDispatchSettings')->saveDispositions($this->request->getPost());
        return redirect()->to(route_to('dispatch.settings') . '#disposiciones')
            ->with($result->success ? 'success' : 'error', $result->message);
    }
}
