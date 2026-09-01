<style>
/* HelpdeskSupervisor — shared UI (Resumen GLPI overview is the reference). */
.hs-tabs { display:flex; gap:var(--space-1); margin-bottom:var(--space-4); border-bottom:1px solid var(--color-neutral-200); flex-wrap:wrap; }
.hs-tab {
  appearance:none; background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-1px;
  padding:var(--space-3) var(--space-4); cursor:pointer; text-decoration:none;
  font-size:var(--text-sm); font-weight:var(--weight-medium); color:var(--text-secondary);
  transition:color var(--duration-base), border-color var(--duration-base);
}
.hs-tab:link, .hs-tab:visited, .hs-tab:hover, .hs-tab:focus, .hs-tab:active { text-decoration:none; }
.hs-tab:hover:not(.is-active) { color:var(--text-primary); border-bottom-color:var(--color-neutral-300); }
.hs-tab.is-active,
.hs-tab.is-active:link, .hs-tab.is-active:visited, .hs-tab.is-active:hover,
.hs-tab.is-active:focus, .hs-tab.is-active:active {
  color:var(--color-primary); text-decoration:none;
}
.hs-tab.is-active { font-weight:var(--weight-semibold); border-bottom-color:var(--color-primary); }
.hs-tab:focus-visible { outline:2px solid var(--color-primary); outline-offset:-2px; border-radius:var(--radius-sm); }

.hs-toolbar { display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end; }
.hs-toolbar .field { gap:var(--space-2); margin:0; }
.hs-toolbar .field-label { margin-bottom:0; }
.hs-field-row { display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end; }

.hs-stat-grid {
  display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
  gap:var(--space-3); margin-bottom:var(--space-4);
}
.hs-stat-link {
  text-decoration:none; color:inherit; display:block;
  transition:box-shadow var(--duration-base), border-color var(--duration-base), transform var(--duration-base);
  border:1px solid transparent; border-radius:var(--radius-2);
}
.hs-stat-link:link, .hs-stat-link:visited, .hs-stat-link:hover,
.hs-stat-link:focus, .hs-stat-link:active { text-decoration:none; color:inherit; }
.hs-stat-link:hover {
  box-shadow:var(--shadow-sm); border-color:var(--color-primary); transform:translateY(-1px);
}
.hs-stat-link:focus-visible { outline:2px solid var(--color-primary); outline-offset:2px; }
.hs-stat-link.is-disabled { pointer-events:none; opacity:0.55; }
.hs-stat-card { border:1px solid transparent; border-radius:var(--radius-2); }
.hs-stat { padding:var(--space-3) var(--space-4); }
.hs-stat-label { margin:0 0 var(--space-1); color:var(--text-secondary); font-size:var(--text-sm); }
.hs-stat-value { margin:0; font-size:2rem; line-height:1.15; font-weight:700; letter-spacing:-0.02em; }

.hs-types {
  display:flex; flex-wrap:wrap; gap:var(--space-2); align-items:center;
  margin:0 0 var(--space-4); padding:var(--space-2) 0;
}
.hs-type-chip {
  display:inline-flex; align-items:baseline; gap:var(--space-2);
  padding:var(--space-1) var(--space-3);
  background:var(--color-neutral-100); border-radius:var(--radius-2);
  font-size:var(--text-sm);
}
.hs-type-chip a {
  display:inline-flex; align-items:baseline; gap:var(--space-2);
  color:inherit; text-decoration:none; transition:color var(--duration-base);
}
.hs-type-chip a:link, .hs-type-chip a:visited, .hs-type-chip a:hover,
.hs-type-chip a:focus, .hs-type-chip a:active { text-decoration:none; color:inherit; }
.hs-type-chip a:hover strong { color:var(--color-primary); }
.hs-type-chip.is-disabled { opacity:0.55; }
.hs-type-chip strong { font-size:1.125rem; }

.hs-lists { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:var(--space-4); }
.hs-bar { height:4px; background:var(--color-neutral-100); border-radius:2px; margin-top:4px; }
.hs-bar > span { display:block; height:4px; background:var(--color-primary); border-radius:2px; }

.hs-drill { cursor:pointer; transition:background var(--duration-base); }
.hs-drill:hover { background:var(--color-neutral-50); }
.hs-drill:focus-visible { outline:2px solid var(--color-primary); outline-offset:-2px; }
.hs-drill a { color:inherit; text-decoration:none; display:block; }
.hs-drill td:last-child a { font-weight:600; color:var(--color-primary); }

/* Settings tabs (button elements share .hs-tab) */
.hs-panel { display:none; }
.hs-panel.is-active { display:block; }
.hs-panel .card-body {
  display:flex; flex-direction:column; gap:var(--space-4);
}
.hs-panel .field { gap:var(--space-2); }
.hs-panel .field-label { margin-bottom:0; }
.hs-panel fieldset {
  display:flex; flex-direction:column; gap:var(--space-3);
}
.hs-panel fieldset legend {
  margin-bottom:var(--space-2); padding:0 var(--space-1);
}

.hs-table-scroll { overflow-x:auto; }
.hs-table-wide { table-layout:auto; width:100%; }
.hs-table-wide th, .hs-table-wide td { vertical-align:top; }
.hs-cell-wrap {
  white-space:pre-wrap;
  word-break:break-word;
  min-width:12rem;
  max-width:28rem;
}

.hs-period-filter {
  display:flex;
  gap:var(--space-3);
  flex-wrap:wrap;
  align-items:flex-end;
}
.hs-period-divider {
  align-self:stretch;
  width:1px;
  background:var(--color-neutral-200);
  margin:var(--space-1) 0;
}
@media (max-width:720px) {
  .hs-period-divider { display:none; }
}

.hs-agent-summary-top {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:var(--space-4);
  align-items:start;
}
@media (max-width:960px) {
  .hs-agent-summary-top { grid-template-columns:1fr; }
}
.hs-agent-kpi-grid {
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:var(--space-2);
  align-content:start;
}
.hs-agent-kpi-card {
  border:1px solid var(--color-neutral-200);
  border-radius:var(--radius-2);
  background:var(--surface-secondary, var(--color-neutral-50));
  box-shadow:none;
}
.hs-agent-kpi-card-body { padding:var(--space-2) var(--space-3); }
.hs-agent-kpi-label { margin:0 0 var(--space-1); color:var(--text-secondary); font-size:var(--text-xs); }
.hs-agent-kpi-value { margin:0; font-size:1.25rem; font-weight:var(--weight-semibold); line-height:1.2; letter-spacing:-0.01em; }
.hs-agent-kpi-meta { font-size:0.8em; font-weight:var(--weight-medium); color:var(--text-secondary); }
.hs-agent-kpi-value--critical { color:var(--color-critical-strong, #b42318); }
.hs-rule-panel {
  min-width:0;
  max-width:100%;
  justify-self:stretch;
}
@media (min-width:961px) {
  .hs-rule-panel { width:100%; max-width:100%; }
}
.hs-rule-table { width:100%; font-size:var(--text-sm); table-layout:fixed; }
.hs-rule-table th:nth-child(1),
.hs-rule-table td:nth-child(1) { width:auto; }
.hs-rule-table th:nth-child(2),
.hs-rule-table td:nth-child(2),
.hs-rule-table th:nth-child(3),
.hs-rule-table td:nth-child(3) { width:3.5rem; }
.hs-rule-table th { font-size:var(--text-xs); color:var(--text-secondary); font-weight:var(--weight-medium); }
.hs-rule-row.is-active { background:var(--color-primary-50, #eef6fd); }
.hs-rule-row.is-active td { font-weight:var(--weight-semibold); }
.hs-rule-row a { color:inherit; text-decoration:none; display:block; }
.hs-rule-row:hover { background:var(--color-neutral-50); }
.hs-filter-chip {
  display:inline-flex; align-items:center; gap:var(--space-2);
  padding:var(--space-1) var(--space-2);
  background:var(--color-primary-50, #eef6fd);
  border-radius:var(--radius-2);
  font-size:var(--text-sm);
}
.hs-filter-chip a { color:var(--color-primary); text-decoration:none; font-weight:var(--weight-medium); }

.hs-row-actions {
  display:flex;
  flex-wrap:nowrap;
  gap:var(--space-2);
  align-items:center;
  justify-content:flex-end;
}
.hs-row-actions-form {
  display:inline-flex;
  margin:0;
  flex:0 0 auto;
}
/* hs-drill makes row links block; action buttons must stay inline. */
.hs-drill .hs-row-actions a.btn,
.hs-drill .hs-row-actions .btn {
  display:inline-flex;
}
</style>
