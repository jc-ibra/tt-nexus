<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Registro de actividad</h1>
    <p class="page-subtitle">Todas las acciones ejecutadas por los técnicos a través del bot.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('techbot.index') ?>" class="btn btn-secondary">‹ Panel</a>
  </div>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-body">
    <form method="get" action="<?= route_to('techbot.activity') ?>" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:var(--space-3); align-items:end;">
      <div class="field">
        <label class="field-label" for="f-ticket">Ticket #</label>
        <input class="input" type="number" id="f-ticket" name="ticket" value="<?= esc($filters['glpi_ticket_id'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label" for="f-action">Acción</label>
        <select class="input" id="f-action" name="action">
          <option value="">Todas</option>
          <?php foreach ($actions as $key => $meta): ?>
            <option value="<?= esc($key) ?>" <?= ($filters['action'] ?? '') === $key ? 'selected' : '' ?>><?= esc($meta['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="field-label" for="f-result">Resultado</label>
        <select class="input" id="f-result" name="result">
          <option value="">Todos</option>
          <option value="success" <?= ($filters['result'] ?? '') === 'success' ? 'selected' : '' ?>>Éxito</option>
          <option value="error" <?= ($filters['result'] ?? '') === 'error' ? 'selected' : '' ?>>Error</option>
        </select>
      </div>
      <div class="field">
        <label class="field-label" for="f-from">Desde</label>
        <input class="input" type="date" id="f-from" name="from" value="<?= esc($filters['from'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="field-label" for="f-to">Hasta</label>
        <input class="input" type="date" id="f-to" name="to" value="<?= esc($filters['to'] ?? '') ?>">
      </div>
      <div class="field">
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <?php if (empty($rows)): ?>
      <p class="text-muted" style="padding: var(--space-4);">No hay registros para los filtros seleccionados.</p>
    <?php else: ?>
      <table class="table" style="width:100%;">
        <thead><tr><th>Fecha</th><th>Técnico</th><th>Ticket</th><th>Acción</th><th>Estado GLPI</th><th>IA</th><th>Resultado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="text-muted text-sm"><?= esc($r['created_at']) ?></td>
              <td class="text-sm"><?= esc(trim(($r['employee_name'] ?? '') . ' ' . ($r['employee_lastname'] ?? '')) ?: '—') ?></td>
              <td class="text-sm">#<?= (int) $r['glpi_ticket_id'] ?></td>
              <td class="text-sm"><?= esc($r['action']) ?></td>
              <td class="text-sm text-muted"><?= (int) ($r['glpi_status_before'] ?? 0) ?> → <?= (int) ($r['glpi_status_after'] ?? 0) ?></td>
              <td class="text-sm"><?= ! empty($r['ai_used']) ? (int) ($r['ai_tokens_used'] ?? 0) . ' tok' : '—' ?></td>
              <td><?= ($r['result'] ?? '') === 'error' ? '<span class="badge badge-critical">Error</span>' : '<span class="badge badge-success">OK</span>' ?></td>
              <td style="text-align:right;"><a href="<?= route_to('techbot.activity.show', (int) $r['id']) ?>" class="text-sm">Detalle ›</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
