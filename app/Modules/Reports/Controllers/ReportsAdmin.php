<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Controllers\BaseController;
use App\Modules\ServiceDesk\Services\GlpiSchemaIntrospector;

/**
 * SuperAdmin config: which plugin container + field backs each logical GLPI
 * field (regional, estado, municipio, sucursal, IDS — each may live in a
 * DIFFERENT container), thresholds, AI, and email recipients. The password
 * field for AI is write-only, same pattern as Provisioning's GLPI connection.
 */
class ReportsAdmin extends BaseController
{
    /** @var array<string,string> logical key => label */
    private const LOGICAL_FIELDS = [
        'regional'   => 'Regional',
        'estado_geo' => 'Estado',
        'municipio'  => 'Municipio',
        'sucursal'   => 'Sucursal',
        'ids'        => 'IDS (técnico)',
    ];

    public function settings()
    {
        $settings     = service('reportsSettings');
        $introspector = service('glpiSchemaIntrospector');

        return view('App\Modules\Reports\Views\settings', [
            'pageTitle'     => 'Configuración de Informes',
            'settings'      => $settings->getAllForDisplay(),
            'logicalFields' => self::LOGICAL_FIELDS,
            'fieldOptions'  => $this->flatFieldOptions($introspector),
            'bindings'      => $settings->glpiFieldBindings(),
            'glpiReady'     => $introspector->isConfigured(),
        ]);
    }

    public function saveSettings()
    {
        $settings = service('reportsSettings');
        $post     = $this->request->getPost();

        $bindings = [];
        foreach (array_keys(self::LOGICAL_FIELDS) as $key) {
            $raw = trim((string) ($post["binding_{$key}"] ?? ''));
            if ($raw === '') {
                continue;
            }
            // Composite value "containerId:fieldName" from the flat select.
            [$containerId, $fieldName] = array_pad(explode(':', $raw, 2), 2, '');
            if ((int) $containerId > 0 && $fieldName !== '') {
                $bindings[$key] = ['container_id' => (int) $containerId, 'field' => $fieldName];
            }
        }

        $data = [
            'glpi_field_bindings'          => json_encode($bindings),
            'sla_hours'                    => (string) max(1, (int) ($post['sla_hours'] ?? 24)),
            'trend_months'                 => (string) max(1, (int) ($post['trend_months'] ?? 12)),
            'ai_enabled'                   => isset($post['ai_enabled']) ? '1' : '0',
            'ai_model'                     => trim((string) ($post['ai_model'] ?? 'claude-sonnet-5')),
            'ai_reuse_helpdesk_supervisor' => isset($post['ai_reuse_helpdesk_supervisor']) ? '1' : '0',
            'email_recipients'             => trim((string) ($post['email_recipients'] ?? '')),
            'email_sender_name'            => trim((string) ($post['email_sender_name'] ?? 'Reportes tt-nexus')),
        ];

        $aiKey = (string) ($post['ai_api_key'] ?? '');
        if ($aiKey !== '') {
            $data['ai_api_key'] = $aiKey;
        }

        $settings->save($data);
        session()->setFlashdata('success', 'Configuración guardada.');
        return redirect()->to(route_to('reports.admin.settings'));
    }

    public function regenerate(int $year, int $month)
    {
        try {
            service('reportSnapshotBuilder')->generate($year, $month, (int) session()->get('user_id'), true);
            session()->setFlashdata('success', 'Informe regenerado.');
        } catch (\Throwable $e) {
            session()->setFlashdata('error', $e->getMessage());
        }
        return redirect()->to(route_to('reports.show', $year, $month));
    }

    /**
     * Every field of every container, flattened into one option list
     * ("containerId:fieldName" => "Field Label (Container Label)") so each
     * logical field can point at ANY container without an admin having to
     * pick a container first and reload — the real deployment already needs
     * this: IDS lives in its own container, separate from the others.
     *
     * @return array<string,string> value => display label
     */
    private function flatFieldOptions(GlpiSchemaIntrospector $introspector): array
    {
        if (! $introspector->isConfigured()) {
            return [];
        }
        $out = [];
        foreach ($introspector->containers() as $c) {
            foreach ($c['fields'] as $f) {
                $out["{$c['id']}:{$f['name']}"] = "{$f['label']} ({$c['label']})";
            }
        }
        return $out;
    }
}
