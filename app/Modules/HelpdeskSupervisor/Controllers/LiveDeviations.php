<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers;

use App\Controllers\BaseController;
use App\Modules\HelpdeskSupervisor\Models\LiveDeviationModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Real-time deviation feed from GLPI webhooks (not period audit runs).
 */
class LiveDeviations extends BaseController
{
    private const PER_PAGE = 50;

    public function index(): string
    {
        $model    = new LiveDeviationModel();
        $page     = max(1, (int) $this->request->getGet('page'));
        $perPage  = max(1, min(200, (int) ($this->request->getGet('per_page') ?: self::PER_PAGE)));
        $ruleKey  = trim((string) $this->request->getGet('rule'));
        $ruleKey  = ($ruleKey !== '' && preg_match('/^[a-z_]+$/', $ruleKey)) ? $ruleKey : null;

        $total    = $model->countOpen($ruleKey);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $offset   = ($page - 1) * $perPage;

        $ruleTotals = [];
        foreach ($model->openRuleSummary() as $r) {
            $key = (string) $r['rule_key'];
            $ruleTotals[$key]['rule_name'] = (string) $r['rule_name'];
            $ruleTotals[$key]['count']     = ($ruleTotals[$key]['count'] ?? 0) + (int) $r['count'];
        }
        uasort($ruleTotals, static fn(array $a, array $b): int => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        return view('App\Modules\HelpdeskSupervisor\Views\live_deviations', [
            'pageTitle'   => 'Desviaciones en vivo',
            'deviations'  => $model->openList($perPage, $offset, $ruleKey),
            'ruleTotals'  => $ruleTotals,
            'ruleFilter'  => $ruleKey,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'lastPage'    => $lastPage,
            'webhookOn'   => service('helpdeskSupervisorSettings')->webhookEnabled(),
            'glpiBaseUrl' => $this->glpiBaseUrl(),
        ]);
    }

    public function resolve(int $id): RedirectResponse
    {
        $model = new LiveDeviationModel();
        $row   = $model->find($id);
        $back  = route_to('helpdesk.live');

        if ($row === null) {
            return redirect()->to($back)->with('error', 'Desviación no encontrada.');
        }

        $model->update($id, [
            'status'      => 'resolved',
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to($back)->with('success', 'Desviación marcada como resuelta.');
    }

    private function glpiBaseUrl(): string
    {
        try {
            $s = service('provisioningSettings')->getAll();
            $url = trim((string) ($s['glpi_url'] ?? $s['glpi_base_url'] ?? ''));
            if ($url !== '') {
                return rtrim($url, '/');
            }
        } catch (\Throwable) {
            // fall through
        }

        return 'https://helpdesk.trantortechnologies.mx';
    }
}
