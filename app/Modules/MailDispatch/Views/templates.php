<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$variables = \App\Modules\MailDispatch\Services\TemplateRenderer::VARIABLES;

// One-line preview of the body for the table: line breaks collapsed so a long
// template does not stretch the row.
$preview = static function (string $body): string {
    $flat = trim((string) preg_replace('/\s+/u', ' ', $body));
    if ($flat === '') {
        return '';
    }
    return mb_strlen($flat) > 120 ? mb_substr($flat, 0, 120) . '…' : $flat;
};
?>

<style>
  .md-tpl-name { font-weight:var(--weight-semibold); color:var(--text-primary); }
  .md-tpl-subject { color:var(--text-muted); font-size:var(--text-sm); margin-top:2px; }
  .md-tpl-preview { color:var(--text-secondary); font-size:var(--text-sm); max-width:52ch; }
  .md-vars { display:flex; flex-wrap:wrap; gap:var(--space-2); margin:0; padding:0; list-style:none; }
  .md-vars li { display:flex; align-items:center; gap:var(--space-2); font-size:var(--text-sm); color:var(--text-muted); }
  .md-var-chip { font-family:var(--font-mono); font-size:var(--text-xs); background:var(--bg-surface-alt);
                 border:1px solid var(--border-default); border-radius:var(--radius-sm); padding:2px var(--space-2);
                 color:var(--text-primary); cursor:pointer; }
  button.md-var-chip:hover { border-color:var(--border-focus); color:var(--text-link); }
  .md-modal-hint { font-size:var(--text-xs); color:var(--text-muted); margin:4px 0 0; }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Plantillas de respuesta</h1>
    <p class="page-subtitle">Textos reutilizables para responder desde Nexus. Solo las plantillas activas aparecen en el compositor del hilo.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" id="btn-new">Nueva plantilla</button>
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Bandeja</a>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap:var(--space-3);">
    <h2 class="card-title">Plantillas</h2>
    <span class="text-muted text-sm"><?= count($templates) ?> en total</span>
  </div>

  <?php if (empty($templates)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
      </div>
      <h2 class="empty-state-title">Aún no hay plantillas</h2>
      <p class="empty-state-message">Crea textos reutilizables para responder los hilos más frecuentes sin escribirlos cada vez.</p>
      <button type="button" class="btn btn-primary" id="btn-new-empty">Nueva plantilla</button>
    </div>
  <?php else: ?>
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Vista previa</th>
            <th style="width:110px;">Estado</th>
            <th style="width:150px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($templates as $t): ?>
            <tr>
              <td>
                <div class="md-tpl-name"><?= esc($t['name']) ?></div>
                <?php if (trim((string) $t['subject']) !== ''): ?>
                  <div class="md-tpl-subject">Asunto: <?= esc($t['subject']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php $p = $preview((string) $t['body']); ?>
                <div class="md-tpl-preview"><?= $p !== '' ? esc($p) : '<span class="text-muted">Sin cuerpo</span>' ?></div>
              </td>
              <td>
                <?php if ((int) $t['is_active'] === 1): ?>
                  <span class="badge badge-success">Activa</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Inactiva</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="table-actions">
                  <button type="button" class="btn btn-secondary btn-sm btn-edit"
                          data-id="<?= (int) $t['id'] ?>"
                          data-name="<?= esc($t['name'], 'attr') ?>"
                          data-subject="<?= esc((string) $t['subject'], 'attr') ?>"
                          data-body="<?= esc((string) $t['body'], 'attr') ?>"
                          data-active="<?= (int) $t['is_active'] ?>"
                          data-action="<?= route_to('dispatch.templates.update', $t['id']) ?>">Editar</button>
                  <form action="<?= route_to('dispatch.templates.delete', $t['id']) ?>" method="post"
                        onsubmit="return confirm('¿Eliminar la plantilla <?= esc($t['name'], 'js') ?>?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:var(--space-5);">
  <div class="card-header"><h2 class="card-title">Variables disponibles</h2></div>
  <div class="card-body">
    <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
      Escríbelas dentro del cuerpo: al insertar la plantilla en un hilo se reemplazan por los datos de esa conversación.
      Si no hay dato, la variable se sustituye por texto vacío.
    </p>
    <ul class="md-vars">
      <?php foreach ($variables as $var => $desc): ?>
        <li><code class="md-var-chip" style="cursor:default;"><?= esc($var) ?></code> <?= esc($desc) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<!-- ================================================================
     MODAL: Nueva plantilla
     ================================================================ -->
<div id="modal-create" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-create-title">
  <div class="modal">
    <form action="<?= route_to('dispatch.templates.store') ?>" method="post">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h2 class="modal-title" id="modal-create-title">Nueva plantilla</h2>
        <button type="button" class="btn btn-tertiary btn-sm modal-close" data-modal="modal-create" aria-label="Cerrar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body">
        <div class="field" style="margin-bottom:var(--space-4);">
          <label class="field-label" for="create-name">Nombre</label>
          <input type="text" id="create-name" name="name" class="input" required>
          <p class="md-modal-hint">Como aparecerá en el compositor del hilo.</p>
        </div>
        <div class="field" style="margin-bottom:var(--space-4);">
          <label class="field-label" for="create-subject">Asunto (opcional)</label>
          <input type="text" id="create-subject" name="subject" class="input">
          <p class="md-modal-hint">Referencia interna: al responder un hilo se conserva el asunto original del correo.</p>
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="create-body">Cuerpo</label>
          <textarea id="create-body" name="body" class="input" rows="8"></textarea>
          <p class="md-modal-hint">Insertar variable:</p>
          <div style="display:flex; flex-wrap:wrap; gap:var(--space-1); margin-top:4px;">
            <?php foreach ($variables as $var => $desc): ?>
              <button type="button" class="md-var-chip" data-var="<?= esc($var, 'attr') ?>" data-target="create-body" title="<?= esc($desc, 'attr') ?>"><?= esc($var) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="field-check">
          <input type="checkbox" name="is_active" value="1" checked><span>Activa</span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close" data-modal="modal-create">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear plantilla</button>
      </div>
    </form>
  </div>
</div>

<!-- ================================================================
     MODAL: Editar plantilla
     ================================================================ -->
<div id="modal-edit" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-edit-title">
  <div class="modal">
    <form id="form-edit" method="post">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h2 class="modal-title" id="modal-edit-title">Editar plantilla</h2>
        <button type="button" class="btn btn-tertiary btn-sm modal-close" data-modal="modal-edit" aria-label="Cerrar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body">
        <div class="field" style="margin-bottom:var(--space-4);">
          <label class="field-label" for="edit-name">Nombre</label>
          <input type="text" id="edit-name" name="name" class="input" required>
        </div>
        <div class="field" style="margin-bottom:var(--space-4);">
          <label class="field-label" for="edit-subject">Asunto (opcional)</label>
          <input type="text" id="edit-subject" name="subject" class="input">
          <p class="md-modal-hint">Referencia interna: al responder un hilo se conserva el asunto original del correo.</p>
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="edit-body">Cuerpo</label>
          <textarea id="edit-body" name="body" class="input" rows="8"></textarea>
          <p class="md-modal-hint">Insertar variable:</p>
          <div style="display:flex; flex-wrap:wrap; gap:var(--space-1); margin-top:4px;">
            <?php foreach ($variables as $var => $desc): ?>
              <button type="button" class="md-var-chip" data-var="<?= esc($var, 'attr') ?>" data-target="edit-body" title="<?= esc($desc, 'attr') ?>"><?= esc($var) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="field-check">
          <input type="checkbox" id="edit-active" name="is_active" value="1"><span>Activa</span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close" data-modal="modal-edit">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';

  function openModal(id) {
    const el = document.getElementById(id);
    el.style.display = 'flex';
    el.addEventListener('click', backdropClose);
  }

  function closeModal(id) {
    const el = document.getElementById(id);
    el.style.display = 'none';
    el.removeEventListener('click', backdropClose);
  }

  function backdropClose(e) {
    if (e.target === e.currentTarget) closeModal(e.currentTarget.id);
  }

  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.modal));
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    ['modal-create', 'modal-edit'].forEach(id => {
      const el = document.getElementById(id);
      if (el && el.style.display !== 'none') closeModal(id);
    });
  });

  ['btn-new', 'btn-new-empty'].forEach(id => {
    const btn = document.getElementById(id);
    if (btn) btn.addEventListener('click', () => {
      openModal('modal-create');
      document.getElementById('create-name').focus();
    });
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('form-edit').action = btn.dataset.action;
      document.getElementById('edit-name').value    = btn.dataset.name;
      document.getElementById('edit-subject').value = btn.dataset.subject;
      document.getElementById('edit-body').value    = btn.dataset.body;
      document.getElementById('edit-active').checked = btn.dataset.active === '1';
      openModal('modal-edit');
      document.getElementById('edit-name').focus();
    });
  });

  // Variable chips: insert at the caret so the agent does not have to type the
  // braces by hand.
  document.querySelectorAll('.md-var-chip[data-var]').forEach(chip => {
    chip.addEventListener('click', () => {
      const ta = document.getElementById(chip.dataset.target);
      const start = ta.selectionStart ?? ta.value.length;
      const end   = ta.selectionEnd ?? ta.value.length;
      ta.value = ta.value.slice(0, start) + chip.dataset.var + ta.value.slice(end);
      const caret = start + chip.dataset.var.length;
      ta.focus();
      ta.setSelectionRange(caret, caret);
    });
  });
})();
</script>

<?= $this->endSection() ?>
