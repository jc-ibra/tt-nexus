<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  /* Horizontal scroller: the category column stays put while the agents scroll. */
  .sda-scroll { overflow: auto; max-height: 72vh; border: 1px solid var(--border-default); border-radius: var(--radius-md); background: var(--bg-surface); }
  .sda-table { border-collapse: separate; border-spacing: 0; width: 100%; font-size: var(--text-sm); }
  .sda-table th, .sda-table td { padding: var(--space-2) var(--space-3); border-bottom: 1px solid var(--border-default); text-align: left; vertical-align: top; }
  .sda-table thead th { position: sticky; top: 0; z-index: 3; background: var(--bg-surface-alt); font-weight: var(--weight-semibold); white-space: nowrap; }
  .sda-table tbody tr:hover td { background: var(--bg-surface-alt); }
  .sda-cat { position: sticky; left: 0; z-index: 2; background: var(--bg-surface); min-width: 260px; max-width: 380px;
    border-right: 1px solid var(--border-default); font-weight: var(--weight-medium); }
  .sda-table thead .sda-cat { z-index: 4; background: var(--bg-surface-alt); }
  .sda-table tbody tr:hover .sda-cat { background: var(--bg-surface-alt); }
  .sda-cat-lead { color: var(--text-muted); font-weight: var(--weight-regular); }
  .sda-agent-col { min-width: 128px; }
  .sda-is-mine { background: var(--color-blue-50) !important; }
  .sda-table thead th.sda-is-mine { background: var(--color-blue-100) !important; }

  /* One chip per stage the agent covers in that category. */
  .sda-chips { display: flex; flex-wrap: wrap; gap: var(--space-1); }
  /* Flat by design: a single surface per chip. A pill inside a pill reads as an
     embossed button and fights the text it is supposed to carry. */
  .sda-chip { display: inline-flex; align-items: baseline; gap: 5px; padding: 2px 7px;
    border-radius: var(--radius-sm); border: 1px solid transparent; background: transparent;
    font-size: var(--text-xs); line-height: 1.5; white-space: nowrap; }
  .sda-chip .sda-stage { font-weight: var(--weight-bold); letter-spacing: .02em; }
  .sda-chip .sda-sep   { opacity: .4; }
  .sda-chip .sda-chan  { font-weight: var(--weight-medium); }
  /* The tint carries the channel; the border only separates chips that touch. */
  .sda-ch-E  { background: var(--color-blue-50);         border-color: #D9E8F6; color: var(--color-blue-800); }
  .sda-ch-W  { background: var(--color-success-surface);  border-color: #D3EAE0; color: var(--color-success-strong); }
  .sda-ch-I  { background: var(--color-warning-surface);  border-color: #F2E2C4; color: var(--color-warning-strong); }
  .sda-ch-EW { background: var(--color-blue-50);         border-color: #D3EAE0; color: var(--color-blue-800); }
  .sda-ch-NA { background: var(--bg-surface-alt);        border-color: var(--border-default); color: var(--text-muted); }
  .sda-ch-x  { background: var(--bg-surface-alt);        border-color: var(--border-default); color: var(--text-secondary); }

  .sda-empty { color: var(--text-disabled); }
  .sda-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--space-3); }
  .sda-toolbar .field { margin: 0; }
  .sda-count { margin-left: auto; color: var(--text-muted); font-size: var(--text-sm); white-space: nowrap; }
  .sda-legend { display: flex; flex-wrap: wrap; gap: var(--space-2) var(--space-5); }
  .sda-legend div { font-size: var(--text-sm); color: var(--text-secondary); }
  .sda-legend code { font-family: var(--font-mono); font-weight: var(--weight-bold); color: var(--text-primary); }
  .sda-hidden { display: none !important; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
/**
 * Renders one agent's cell: a chip per stage, in the sheet's reading order.
 * The channel code drives the color so a whole column can be scanned at a glance.
 *
 * @param array<string,string> $cells  stage => channel
 * @param list<string>         $stages stage codes in order
 * @param array<string,string> $legend code => human label
 */
$renderCell = static function (array $cells, array $stages, array $legend): string {
    if ($cells === []) {
        return '<span class="sda-empty">·</span>';
    }
    $html = '<div class="sda-chips">';
    foreach ($stages as $stage) {
        if (! isset($cells[$stage])) {
            continue;
        }
        $channel = $cells[$stage];
        $slug    = preg_replace('/[^A-Z]/', '', strtoupper($channel)) ?: 'x';
        $class   = in_array($slug, ['E', 'W', 'I', 'EW', 'NA'], true) ? $slug : 'x';

        // Tooltip spells both codes out, so nobody has to memorize the legend.
        $chanLabel = [];
        foreach (explode('/', str_replace('N/A', 'N|A', $channel)) as $code) {
            $code = str_replace('N|A', 'N/A', trim($code));
            if ($code !== '') {
                $chanLabel[] = $legend[$code] ?? $code;
            }
        }
        $title = ($legend[$stage] ?? $stage) . ' por ' . implode(' / ', $chanLabel);

        $html .= '<span class="sda-chip sda-ch-' . $class . '" title="' . esc($title, 'attr') . '">'
              . '<span class="sda-stage">' . esc($stage) . '</span>'
              . '<span class="sda-sep" aria-hidden="true">·</span>'
              . '<span class="sda-chan">' . esc($channel) . '</span></span>';
    }
    return $html . '</div>';
};

// The legend only lists codes that are actually in use.
$stagesInUse   = [];
$channelsInUse = [];
foreach ($matrix as $row) {
    foreach ($row['cells'] as $byStage) {
        foreach ($byStage as $stage => $channel) {
            $stagesInUse[$stage] = true;
            foreach (explode('/', str_replace('N/A', 'N|A', $channel)) as $code) {
                $code = str_replace('N|A', 'N/A', trim($code));
                if ($code !== '') {
                    $channelsInUse[$code] = true;
                }
            }
        }
    }
}

/**
 * Search key for a category: lowercase and accent-folded, so typing "almacen"
 * finds "Almacén". The browser side folds the typed term the same way.
 */
$searchKey = static function (string $text): string {
    return strtr(mb_strtolower($text), [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i',
        'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e', 'î' => 'i',
        'ô' => 'o', 'û' => 'u',
    ]);
};

$myCategories = 0;
if ($myAgentId !== null) {
    foreach ($matrix as $row) {
        if (! empty($row['cells'][$myAgentId])) {
            $myCategories++;
        }
    }
}
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Asignaciones</h1>
    <p class="page-subtitle">Quién atiende cada categoría, en qué etapa del ticket y por qué canal llega.</p>
  </div>
</div>

<?php if (empty($matrix)): ?>

  <div class="banner banner-info" role="status">
    <div class="banner-body">
      Todavía no hay una matriz de asignaciones cargada. Un administrador la sube en
      Configuración · Service Desk, en la pestaña «Asignaciones».
    </div>
  </div>

<?php else: ?>

  <?php if ($myAgentId !== null): ?>
    <div class="banner banner-info" role="status" style="margin-bottom: var(--space-4);">
      <div class="banner-body">
        Apareces en la matriz como <strong><?= esc($myAgentName) ?></strong>, con
        <strong><?= $myCategories ?></strong> <?= $myCategories === 1 ? 'categoría asignada' : 'categorías asignadas' ?>.
        Usa «Solo lo mío» para ver únicamente esas filas.
      </div>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-body sda-toolbar">
      <div class="field">
        <label class="field-label" for="sda-search">Buscar categoría</label>
        <input type="search" id="sda-search" class="input" style="min-width: 260px;"
               placeholder="Ej. Banorte, Sellcom, Almacén" autocomplete="off">
      </div>
      <div class="field">
        <label class="field-label" for="sda-agent">Persona</label>
        <select id="sda-agent" class="input">
          <option value="">Todas</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= (int) $a['id'] ?>">
              <?= esc($a['name']) ?><?= $a['id'] === $myAgentId ? ' (tú)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($myAgentId !== null): ?>
        <div class="field">
          <label class="field-check" style="margin-bottom: var(--space-2);">
            <input type="checkbox" id="sda-mine" value="1">
            <span>Solo lo mío</span>
          </label>
        </div>
      <?php endif; ?>
      <p class="sda-count" id="sda-count"></p>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-body">
      <details>
        <summary style="cursor:pointer; font-weight: var(--weight-semibold);">Qué significa cada letra</summary>
        <div style="margin-top: var(--space-3); display:grid; gap: var(--space-3);">
          <div>
            <p class="text-muted text-sm" style="margin:0 0 var(--space-1);">Etapa del ticket</p>
            <div class="sda-legend">
              <?php foreach ($stages as $code): ?>
                <?php if (isset($stagesInUse[$code])): ?>
                  <div><code><?= esc($code) ?></code> · <?= esc($legend[$code] ?? $code) ?></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <p class="text-muted text-sm" style="margin:0 0 var(--space-1);">Canal por el que llega</p>
            <div class="sda-legend">
              <?php foreach (array_keys($channelsInUse) as $code): ?>
                <div><code><?= esc($code) ?></code> · <?= esc($legend[$code] ?? $code) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </details>
    </div>
  </div>

  <div class="sda-scroll">
    <table class="sda-table" id="sda-table">
      <thead>
        <tr>
          <th scope="col" class="sda-cat">Categoría</th>
          <?php foreach ($agents as $a): ?>
            <th scope="col" class="sda-agent-col<?= $a['id'] === $myAgentId ? ' sda-is-mine' : '' ?>"
                data-agent="<?= (int) $a['id'] ?>">
              <?= esc($a['name']) ?><?= $a['id'] === $myAgentId ? ' <span class="text-muted text-xs">(tú)</span>' : '' ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matrix as $row): ?>
          <?php
            $withCells = array_keys(array_filter($row['cells'], static fn($c): bool => $c !== []));
            // "AD > Almacén > Control de Activos" reads better with the leaf apart.
            $parts = explode(' > ', $row['category_name']);
            $leaf  = array_pop($parts);
          ?>
          <tr data-cat="<?= esc($searchKey($row['category_name']), 'attr') ?>"
              data-agents="<?= esc(implode(' ', $withCells), 'attr') ?>">
            <th scope="row" class="sda-cat">
              <?php if ($parts !== []): ?>
                <span class="sda-cat-lead"><?= esc(implode(' > ', $parts)) ?> &gt; </span>
              <?php endif; ?><?= esc($leaf) ?>
            </th>
            <?php foreach ($agents as $a): ?>
              <td class="sda-agent-col<?= $a['id'] === $myAgentId ? ' sda-is-mine' : '' ?>"
                  data-agent="<?= (int) $a['id'] ?>">
                <?= $renderCell($row['cells'][$a['id']] ?? [], $stages, $legend) ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($updatedAt !== ''): ?>
    <p class="text-muted text-sm" style="margin-top: var(--space-3);">
      Matriz actualizada el <?= esc(date('d/m/Y H:i', strtotime($updatedAt))) ?>.
    </p>
  <?php endif; ?>

  <script>
  (function () {
    'use strict';
    const table  = document.getElementById('sda-table');
    if (!table) return;
    const rows   = [...table.tBodies[0].rows];
    const search = document.getElementById('sda-search');
    const agent  = document.getElementById('sda-agent');
    const mine   = document.getElementById('sda-mine');
    const count  = document.getElementById('sda-count');
    const myId   = <?= $myAgentId !== null ? (int) $myAgentId : 'null' ?>;

    // Mirrors the PHP side: lowercase, accents stripped.
    const fold = t => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    function apply() {
      const term    = fold((search.value || '').trim());
      const onlyMe  = !!(mine && mine.checked);
      // "Solo lo mío" wins over the picker, so both controls can never disagree.
      const agentId = onlyMe ? String(myId) : (agent.value || '');
      let shown = 0;

      rows.forEach(row => {
        const matchText  = !term || row.dataset.cat.includes(term);
        const matchAgent = !agentId || (' ' + row.dataset.agents + ' ').includes(' ' + agentId + ' ');
        const visible    = matchText && matchAgent;
        row.classList.toggle('sda-hidden', !visible);
        if (visible) shown++;
      });

      // Narrow the table to the chosen person instead of leaving empty columns.
      table.querySelectorAll('[data-agent]').forEach(cell => {
        cell.classList.toggle('sda-hidden', !!agentId && cell.dataset.agent !== agentId);
      });

      if (agent) agent.disabled = onlyMe;
      count.textContent = shown + (shown === 1 ? ' categoría' : ' categorías');
    }

    search.addEventListener('input', apply);
    agent.addEventListener('change', apply);
    if (mine) mine.addEventListener('change', apply);
    apply();
  }());
  </script>

<?php endif; ?>

<?= $this->endSection() ?>
