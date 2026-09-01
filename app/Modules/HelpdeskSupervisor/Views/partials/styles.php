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
</style>
