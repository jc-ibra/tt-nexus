<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Controllers;

use App\Controllers\BaseController;
use App\Modules\KPIsOperativos\Models\GlpiIdcAliasModel;
use App\Modules\KPIsOperativos\Models\GlpiIdcCanonicalModel;
use App\Modules\KPIsOperativos\Services\GlpiIdcHomologator;
use App\Modules\KPIsOperativos\Services\GlpiKpiCalculator;

class IdcCanonical extends BaseController
{
    public function index(): string
    {
        $canonicals = (new GlpiIdcCanonicalModel())->listWithStats();
        $reviewCount = (new GlpiIdcAliasModel())->where('needs_review', 1)->countAllResults();

        return view('App\Modules\KPIsOperativos\Views\idc\index', [
            'pageTitle'    => 'Catálogo IDC',
            'canonicals'   => $canonicals,
            'reviewCount'  => $reviewCount,
        ]);
    }

    public function review(): string
    {
        $aliases = (new GlpiIdcAliasModel())->listNeedsReview();
        $canonicals = (new GlpiIdcCanonicalModel())->allOrdered();

        return view('App\Modules\KPIsOperativos\Views\idc\review', [
            'pageTitle'  => 'Revisión de aliases',
            'aliases'    => $aliases,
            'canonicals' => $canonicals,
        ]);
    }

    public function show(int $id): string
    {
        $canon = (new GlpiIdcCanonicalModel())->find($id);
        if (! $canon) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $aliases       = (new GlpiIdcAliasModel())->listForCanonical($id);
        $allCanonicals = (new GlpiIdcCanonicalModel())->where('id !=', $id)->orderBy('canonical_name', 'ASC')->findAll();

        $db = db_connect();
        $ticketsCount = (int) $db->table('kpi_glpi_tickets')->where('idc_canonical_id', $id)->countAllResults();

        return view('App\Modules\KPIsOperativos\Views\idc\show', [
            'pageTitle'      => $canon['canonical_name'],
            'canonical'      => $canon,
            'aliases'        => $aliases,
            'allCanonicals'  => $allCanonicals,
            'ticketsCount'   => $ticketsCount,
        ]);
    }

    public function verify(int $id)
    {
        $model = new GlpiIdcCanonicalModel();
        $canon = $model->find($id);
        if (! $canon) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->update($id, ['is_verified' => 1]);
        session()->setFlashdata('success', 'Marcado como verificado.');
        return redirect()->to(route_to('kpi.idc.show', $id));
    }

    public function rename(int $id)
    {
        $model = new GlpiIdcCanonicalModel();
        $canon = $model->find($id);
        if (! $canon) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newName = trim((string) $this->request->getPost('canonical_name'));
        if ($newName === '') {
            session()->setFlashdata('errors', ['El nombre canónico no puede estar vacío.']);
            return redirect()->back();
        }

        $homologator = new GlpiIdcHomologator();
        $newNormalized = $homologator->normalize($newName);

        // Asegurar que no choca con otro canonical
        $existing = $model->where('normalized_form', $newNormalized)->where('id !=', $id)->first();
        if ($existing) {
            session()->setFlashdata('errors', ['Ya existe otro canonical con esa forma normalizada (' . esc($existing['canonical_name']) . ').']);
            return redirect()->back();
        }

        $model->update($id, [
            'canonical_name'  => $newName,
            'normalized_form' => $newNormalized,
        ]);

        session()->setFlashdata('success', 'Nombre actualizado.');
        return redirect()->to(route_to('kpi.idc.show', $id));
    }

    public function merge(int $id)
    {
        $targetId = (int) $this->request->getPost('target_id');
        if ($targetId <= 0 || $targetId === $id) {
            session()->setFlashdata('errors', ['Selecciona un canonical destino distinto.']);
            return redirect()->back();
        }

        $model = new GlpiIdcCanonicalModel();
        $source = $model->find($id);
        $target = $model->find($targetId);
        if (! $source || ! $target) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        (new GlpiIdcHomologator())->mergeCanonical($id, $targetId);

        // Recalcular snapshots de todos los reportes con tickets de este canonical
        $this->recomputeAffectedReports($targetId);

        session()->setFlashdata('success', "«{$source['canonical_name']}» mergeado en «{$target['canonical_name']}».");
        return redirect()->to(route_to('kpi.idc.show', $targetId));
    }

    public function destroy(int $id)
    {
        $model = new GlpiIdcCanonicalModel();
        $canon = $model->find($id);
        if (! $canon) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Solo se permite borrar si no tiene tickets asociados
        $db = db_connect();
        $tickets = (int) $db->table('kpi_glpi_tickets')->where('idc_canonical_id', $id)->countAllResults();
        if ($tickets > 0) {
            session()->setFlashdata('errors', ['No se puede borrar: ' . $tickets . ' tickets aún apuntan a este canonical. Usa "mergear" en su lugar.']);
            return redirect()->to(route_to('kpi.idc.show', $id));
        }

        $model->delete($id);
        session()->setFlashdata('success', "«{$canon['canonical_name']}» eliminado.");
        return redirect()->to(route_to('kpi.idc.index'));
    }

    public function confirmAlias(int $aliasId)
    {
        $aliasModel = new GlpiIdcAliasModel();
        $alias = $aliasModel->find($aliasId);
        if (! $alias) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $aliasModel->clearReviewFlag($aliasId);
        session()->setFlashdata('success', 'Alias confirmado.');
        return redirect()->to(route_to('kpi.idc.review'));
    }

    public function reassignAlias(int $aliasId)
    {
        $aliasModel = new GlpiIdcAliasModel();
        $alias = $aliasModel->find($aliasId);
        if (! $alias) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $action = (string) $this->request->getPost('action');

        if ($action === 'new_canonical') {
            // Promueve este alias a un canonical nuevo
            $canonModel = new GlpiIdcCanonicalModel();
            $homologator = new GlpiIdcHomologator();
            $normalized = $homologator->normalize($alias['alias_raw']);

            $existing = $canonModel->where('normalized_form', $normalized)->first();
            if ($existing && (int) $existing['id'] !== (int) $alias['canonical_id']) {
                // Ya hay otro canonical con esa forma — reasigna allí
                $newCanonicalId = (int) $existing['id'];
            } else {
                $newCanonicalId = (int) $canonModel->insert([
                    'canonical_name'  => $alias['alias_raw'],
                    'normalized_form' => $normalized,
                    'is_verified'     => 1,
                ], true);

                // También mueve los tickets que tenían este raw exacto al nuevo canonical
                db_connect()->table('kpi_glpi_tickets')
                    ->where('idc', $alias['alias_raw'])
                    ->where('idc_canonical_id', (int) $alias['canonical_id'])
                    ->update(['idc_canonical_id' => $newCanonicalId]);
            }

            $aliasModel->reassign($aliasId, $newCanonicalId);
            $this->recomputeAffectedReports((int) $alias['canonical_id']);
            $this->recomputeAffectedReports($newCanonicalId);

            session()->setFlashdata('success', 'Alias promovido a un canonical nuevo.');
            return redirect()->to(route_to('kpi.idc.review'));
        }

        // Reasignar a un canonical existente
        $targetId = (int) $this->request->getPost('target_canonical_id');
        if ($targetId <= 0) {
            session()->setFlashdata('errors', ['Selecciona un canonical destino.']);
            return redirect()->back();
        }

        $oldCanonicalId = (int) $alias['canonical_id'];

        // Mover tickets que tenían este raw exacto
        db_connect()->table('kpi_glpi_tickets')
            ->where('idc', $alias['alias_raw'])
            ->where('idc_canonical_id', $oldCanonicalId)
            ->update(['idc_canonical_id' => $targetId]);

        $aliasModel->reassign($aliasId, $targetId);

        $this->recomputeAffectedReports($oldCanonicalId);
        $this->recomputeAffectedReports($targetId);

        session()->setFlashdata('success', 'Alias reasignado.');
        return redirect()->to(route_to('kpi.idc.review'));
    }

    /**
     * Recalcula los snapshots de los reportes que tienen tickets bajo este canonical_id.
     */
    private function recomputeAffectedReports(int $canonicalId): void
    {
        $db = db_connect();
        $reportIds = $db->table('kpi_glpi_tickets')
            ->select('report_id')
            ->distinct()
            ->where('idc_canonical_id', $canonicalId)
            ->get()
            ->getResultArray();

        $calc = new GlpiKpiCalculator();
        foreach ($reportIds as $r) {
            $calc->computeAndSave((int) $r['report_id']);
        }
    }
}
