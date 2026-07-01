<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SuperAdmin config for the GLPI external database connection used by the
 * additional-fields catalog manager. The connection form is embedded in the
 * GLPI provisioning system detail page (systems/(:num)); this controller only
 * handles the save + test actions. The password is write-only: a stored value
 * is shown as "(definida)" and left untouched when the field is blank.
 */
class GlpiConnection extends BaseController
{
    private function settings(): \App\Modules\Provisioning\Models\ProvisioningSettingsModel
    {
        return service('provisioningSettings');
    }

    private function systemUrl(): string
    {
        $glpi = (new \App\Modules\Provisioning\Models\ProvisioningSystemModel())->findByKey('glpi');
        return $glpi
            ? route_to('provisioning.systems.show', $glpi['id'])
            : route_to('provisioning.systems.index');
    }

    public function update(): ResponseInterface
    {
        $post = $this->request->getPost();

        $data = [
            'glpi_db_host'    => trim((string) ($post['glpi_db_host'] ?? '')),
            'glpi_db_port'    => trim((string) ($post['glpi_db_port'] ?? '3306')),
            'glpi_db_name'    => trim((string) ($post['glpi_db_name'] ?? '')),
            'glpi_db_user'    => trim((string) ($post['glpi_db_user'] ?? '')),
            'glpi_db_enabled' => isset($post['glpi_db_enabled']) ? '1' : '0',
        ];

        // Only overwrite the password when a new value is provided.
        $password = (string) ($post['glpi_db_password'] ?? '');
        if ($password !== '') {
            $data['glpi_db_password'] = $password;
        }

        $this->settings()->setMany($data);

        session()->setFlashdata('success', 'Configuración de conexión GLPI guardada.');
        return redirect()->to($this->systemUrl());
    }

    /**
     * AJAX: tests the connection using the values currently in the form
     * (falling back to the stored password when the field is left blank).
     */
    public function test(): ResponseInterface
    {
        $post     = $this->request->getPost();
        $override = [
            'glpi_db_host' => trim((string) ($post['glpi_db_host'] ?? '')),
            'glpi_db_port' => trim((string) ($post['glpi_db_port'] ?? '3306')),
            'glpi_db_name' => trim((string) ($post['glpi_db_name'] ?? '')),
            'glpi_db_user' => trim((string) ($post['glpi_db_user'] ?? '')),
        ];
        $password = (string) ($post['glpi_db_password'] ?? '');
        if ($password !== '') {
            $override['glpi_db_password'] = $password;
        }

        $result = service('glpiDbConnection')->testWith($override);

        return $this->response->setJSON([
            'status'  => $result->success ? 'success' : 'error',
            'message' => $result->message,
            'data'    => $result->data,
        ]);
    }
}
