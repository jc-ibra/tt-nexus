<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\EscalationModel;
use CodeIgniter\HTTP\RedirectResponse;

class Escalations extends BaseController
{
    private EscalationModel $model;

    public function __construct()
    {
        $this->model = new EscalationModel();
    }

    public function index(): string
    {
        return view('App\Modules\HelpdeskSupervisor\Views\escalations\index', [
            'pageTitle'   => 'Escalaciones',
            'escalations' => $this->model->orderBy('escalation_date', 'DESC')->findAll(200),
        ]);
    }

    public function create(): string
    {
        return view('App\Modules\HelpdeskSupervisor\Views\escalations\create', [
            'pageTitle'   => 'Nueva escalación',
            'escalation'  => null,
            'agents'      => $this->mappedAgents(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(route_to('helpdesk.escalations.index'))->with('success', 'Escalación registrada.');
    }

    public function edit(int $id): string
    {
        $escalation = $this->model->find($id);
        if ($escalation === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return view('App\Modules\HelpdeskSupervisor\Views\escalations\create', [
            'pageTitle'  => 'Editar escalación',
            'escalation' => $escalation,
            'agents'     => $this->mappedAgents(),
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        if ($this->model->find($id) === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $data = $this->collect();
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(route_to('helpdesk.escalations.index'))->with('success', 'Escalación actualizada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->model->delete($id);
        return redirect()->to(route_to('helpdesk.escalations.index'))->with('success', 'Escalación eliminada.');
    }

    /** Builds the row from the form, resolving the Nexus agent and snapshot name. */
    private function collect(): array
    {
        $glpiUserId = (int) $this->request->getPost('glpi_user_id');
        $agent      = $this->agentByGlpiId($glpiUserId);
        $date       = (string) $this->request->getPost('escalation_date');

        return [
            'glpi_ticket_id'       => (int) $this->request->getPost('glpi_ticket_id'),
            'glpi_user_id'         => $glpiUserId,
            'nexus_user_id'        => $agent['nexus_user_id'] ?? null,
            'agent_name'           => $agent['name'] ?? '',
            'escalation_date'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d'),
            'reason'               => trim((string) $this->request->getPost('reason')),
            'reported_by'          => trim((string) $this->request->getPost('reported_by')) ?: null,
            'validated_by_user_id' => session()->get('user_id'),
            'period_year'          => (int) ($this->request->getPost('period_year') ?: date('Y')),
            'period_month'         => (int) ($this->request->getPost('period_month') ?: date('n')),
            'is_valid'             => $this->request->getPost('is_valid') !== null ? 1 : 0,
        ];
    }

    /** @return array<int,array{glpi_user_id:int,nexus_user_id:int,name:string}> */
    private function mappedAgents(): array
    {
        $rows = \Config\Database::connect()->table('core_users')
            ->select('id, name, glpi_user_id')
            ->where('glpi_user_id IS NOT NULL')
            ->where('glpi_user_id >', 0)
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn($r) => [
            'glpi_user_id'  => (int) $r['glpi_user_id'],
            'nexus_user_id' => (int) $r['id'],
            'name'          => (string) $r['name'],
        ], $rows);
    }

    private function agentByGlpiId(int $glpiUserId): array
    {
        foreach ($this->mappedAgents() as $a) {
            if ($a['glpi_user_id'] === $glpiUserId) {
                return $a;
            }
        }
        return [];
    }
}
