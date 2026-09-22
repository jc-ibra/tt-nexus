<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Catálogos</h1>
    <p class="page-subtitle">Áreas, departamentos, puestos y catálogos de origen usados en los expedientes de empleados.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Volver a empleados</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Catálogo</th>
          <th style="width:120px; text-align:center;">Registros</th>
          <th style="width:240px; text-align:right;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($catalogs as $c): ?>
          <tr>
            <td>
              <strong><?= esc($c['name']) ?></strong>
              <div class="text-muted text-sm"><?= esc($c['description']) ?></div>
            </td>
            <td style="text-align:center;">
              <span class="badge badge-neutral"><?= (int) $c['count'] ?></span>
            </td>
            <td style="text-align:right;">
              <a href="<?= esc($c['newRoute']) ?>" class="btn btn-tertiary btn-sm">Nuevo</a>
              <a href="<?= esc($c['indexRoute']) ?>" class="btn btn-secondary btn-sm">Ver catálogo</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
