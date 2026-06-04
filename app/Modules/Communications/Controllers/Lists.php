<?php

declare(strict_types=1);

namespace App\Modules\Communications\Controllers;

use App\Controllers\BaseController;
use App\Modules\Communications\Models\RecipientModel;

class Lists extends BaseController
{
    public function index(): string
    {
        $data = service('listService')->paginate(20);

        return view('App\Modules\Communications\Views\lists\index', array_merge($data, [
            'pageTitle' => 'Listas de destinatarios',
        ]));
    }

    public function new(): string
    {
        return view('App\Modules\Communications\Views\lists\form', [
            'pageTitle'  => 'Nueva lista',
            'list'       => null,
            'allRecipients' => (new RecipientModel())->getActive(),
        ]);
    }

    public function store(): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'description', 'recipient_ids']);
        $result = service('listService')->create($data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('comms.lists.index'))->with('success', 'Lista creada.');
    }

    public function edit(int $id): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $list = service('listService')->findWithRecipients($id);

        if ($list === null) {
            return redirect()->to(route_to('comms.lists.index'))->with('error', 'Lista no encontrada.');
        }

        return view('App\Modules\Communications\Views\lists\form', [
            'pageTitle'     => 'Editar lista',
            'list'          => $list,
            'allRecipients' => (new RecipientModel())->getActive(),
        ]);
    }

    public function update(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $data   = $this->request->getPost(['name', 'description', 'recipient_ids']);
        $result = service('listService')->update($id, $data);

        if (! $result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }

        return redirect()->to(route_to('comms.lists.index'))->with('success', 'Lista actualizada.');
    }

    public function destroy(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = service('listService')->destroy($id);

        if (! $result->success) {
            return redirect()->back()->with('error', $result->message);
        }

        return redirect()->to(route_to('comms.lists.index'))->with('success', 'Lista eliminada.');
    }
}
