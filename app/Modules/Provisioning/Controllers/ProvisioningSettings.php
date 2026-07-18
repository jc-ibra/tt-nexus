<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Controllers\BaseController;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use App\Modules\Provisioning\Services\WelcomeMailService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SuperAdmin settings for the provisioning welcome email: the on/off toggle
 * and the editable copy blocks. The system login URLs are managed on the
 * systems page (base_url), not here.
 */
class ProvisioningSettings extends BaseController
{
    /** Editable text fields (everything except the boolean toggle). */
    private const TEXT_KEYS = [
        'welcome_email_subject',
        'welcome_email_org_name',
        'welcome_email_help_desk_email',
        'welcome_email_hero_eyebrow',
        'welcome_email_hero_title',
        'welcome_email_hero_intro',
        'welcome_email_security_notice',
        'welcome_email_support_intro',
        'welcome_email_footer',
    ];

    public function index(): string
    {
        $stored   = service('provisioningSettings')->getAll();
        $defaults = WelcomeMailService::defaults();

        // Effective values: stored when set, otherwise the shared default.
        $values = [];
        foreach ($defaults as $key => $default) {
            $v          = trim((string) ($stored[$key] ?? ''));
            $values[$key] = $v !== '' ? $v : $default;
        }

        return view('App\Modules\Provisioning\Views\settings\index', [
            'pageTitle' => 'Correo de bienvenida',
            'values'    => $values,
            'systems'   => (new ProvisioningSystemModel())->listActive(),
        ]);
    }

    public function update(): ResponseInterface
    {
        $data = [
            'welcome_email_enabled' => $this->request->getPost('welcome_email_enabled') ? '1' : '0',
        ];
        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = trim((string) $this->request->getPost($key));
        }

        service('provisioningSettings')->setMany($data);
        log_message('info', '[Provisioning] Welcome email settings updated by user_id=' . session()->get('user_id'));
        session()->setFlashdata('success', 'Configuracion del correo de bienvenida guardada.');

        return redirect()->to(route_to('provisioning.settings'));
    }

    public function sendTest(): ResponseInterface
    {
        $to = trim((string) $this->request->getPost('test_email'));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            session()->setFlashdata('error', 'Indica un correo valido para la prueba.');
            return redirect()->to(route_to('provisioning.settings'));
        }

        $res = (new WelcomeMailService())->sendTest($to);
        $res['success']
            ? session()->setFlashdata('success', 'Correo de prueba enviado a ' . $to . '.')
            : session()->setFlashdata('error', 'No se pudo enviar la prueba: ' . ($res['error'] ?? ''));

        return redirect()->to(route_to('provisioning.settings'));
    }
}
