<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Controllers;

use App\Controllers\BaseController;
use App\Modules\MailDispatch\Models\TemplateModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Phase 3: reply-template catalog. Operational CRUD (lives in the module, not
 * Core admin, because it is used day-to-day by agents). Bodies support simple
 * variables like {{requester_name}} expanded at compose time.
 */
class Templates extends BaseController
{
    public function index(): string
    {
        return view('App\Modules\MailDispatch\Views\templates', [
            'pageTitle' => 'Plantillas de respuesta · Despacho de Correo',
            'templates' => (new TemplateModel())->allOrdered(),
        ]);
    }

    public function store(): ResponseInterface
    {
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to(route_to('dispatch.templates'))->with('error', 'El nombre es obligatorio.');
        }
        (new TemplateModel())->insert([
            'name'       => $name,
            'subject'    => trim((string) $this->request->getPost('subject')),
            'body'       => (string) $this->request->getPost('body'),
            'is_active'  => $this->request->getPost('is_active') ? 1 : 0,
            'created_by' => (int) session()->get('user_id'),
        ]);
        return redirect()->to(route_to('dispatch.templates'))->with('success', 'Plantilla creada.');
    }

    public function update(int $id): ResponseInterface
    {
        $model = new TemplateModel();
        if ($model->find($id) === null) {
            return redirect()->to(route_to('dispatch.templates'))->with('error', 'La plantilla no existe.');
        }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to(route_to('dispatch.templates'))->with('error', 'El nombre es obligatorio.');
        }
        $model->update($id, [
            'name'      => $name,
            'subject'   => trim((string) $this->request->getPost('subject')),
            'body'      => (string) $this->request->getPost('body'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ]);
        return redirect()->to(route_to('dispatch.templates'))->with('success', 'Plantilla actualizada.');
    }

    public function delete(int $id): ResponseInterface
    {
        (new TemplateModel())->delete($id);
        return redirect()->to(route_to('dispatch.templates'))->with('success', 'Plantilla eliminada.');
    }
}
