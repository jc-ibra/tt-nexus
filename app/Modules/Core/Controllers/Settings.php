<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;

class Settings extends BaseController
{
    public function smtp(): string
    {
        return view('App\Modules\Core\Views\admin\settings\smtp', [
            'pageTitle' => 'Configuración SMTP',
            'settings'  => service('appSettings')->getSmtp(),
        ]);
    }

    public function saveSMTP(): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost();
        $result = service('appSettings')->saveSmtp($data);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('admin.settings.smtp'));
    }

    public function testSMTP(): \CodeIgniter\HTTP\ResponseInterface
    {
        $svc = service('appSettings');

        if (! $svc->isSmtpConfigured()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Configura el servidor SMTP primero.',
            ]);
        }

        try {
            $smtp   = $svc->getSmtp();
            $config = new \Config\Email();
            $config->protocol   = 'smtp';
            $config->SMTPHost   = $smtp['smtp_host'];
            $config->SMTPUser   = $smtp['smtp_user'];
            $config->SMTPPass   = $smtp['smtp_password'];
            $config->SMTPPort   = (int) ($smtp['smtp_port'] ?: 587);
            $config->SMTPCrypto = $smtp['smtp_crypto'] ?: 'tls';
            $config->SMTPTimeout = 30;

            $mailer = \Config\Services::email($config, false);
            $mailer->clear();
            $mailer->setFrom($smtp['smtp_from_email'] ?: $smtp['smtp_user'], $smtp['smtp_from_name'] ?: 'Test');
            $mailer->setTo($smtp['smtp_from_email'] ?: $smtp['smtp_user']);
            $mailer->setSubject('[Nexus] Prueba de conexión SMTP');
            $mailer->setMessage('Este mensaje confirma que la configuración SMTP de Nexus es correcta.');
            $mailer->setMailType('text');

            if ($mailer->send(false)) {
                return $this->response->setJSON(['success' => true, 'message' => 'Email de prueba enviado correctamente.']);
            }

            $debug = strip_tags((string) $mailer->printDebugger(['headers']));
            return $this->response->setJSON(['success' => false, 'message' => mb_substr(trim($debug), 0, 400)]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
