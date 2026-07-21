<?php
/**
 * Public self-service landing (route /soporte). A COMPLETE manual form to open
 * one ticket — identity, base ticket fields, the user-picked ITIL category, and
 * the additional plugin fields of the admin-selected containers — plus a
 * FLOATING chat assistant on top for those who prefer to be guided.
 *
 * No intranet session: identity is captured in the form. The form works without
 * the AI; the floating chat only appears when the AI is configured.
 */
$cfg = [
    'chatUrl'    => $chatUrl,
    'ticketUrl'  => $ticketUrl,
    'submitUrl'  => $submitUrl,
    'chatReady'  => (bool) $chatReady,
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= esc($title) ?></title>
<style>
  :root{
    --primary:#1773C8; --primary-d:#125aa0;
    --bg:#eef2f7; --card:#ffffff; --ink:#1f2733; --muted:#66707d;
    --line:#e4e8ee; --user:#1773C8; --bot:#eef1f5; --ok:#0f8a4f; --danger:#c0362c;
    --radius:14px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;min-height:100%}
  body{
    font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    color:var(--ink);background:var(--bg);padding:24px 16px 96px;
  }
  .wrap{max-width:860px;margin:0 auto}
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 8px 30px rgba(15,23,42,.08);overflow:hidden}
  header{background:var(--primary);color:#fff;padding:20px 22px}
  header h1{font-size:19px;font-weight:600;margin:0}
  header p{font-size:13px;margin:6px 0 0;opacity:.92}
  .body{padding:22px}
  .intro{margin:0 0 20px;color:var(--muted);font-size:13.5px}
  fieldset{border:none;margin:0 0 8px;padding:0}
  legend{font-size:12px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;padding:0}
  .grp{border-top:1px solid var(--line);margin-top:18px;padding-top:18px}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12.5px;font-weight:600;color:var(--muted);margin-bottom:5px}
  .field label .req{color:var(--danger)}
  .field input,.field select,.field textarea{
    width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font:inherit;color:var(--ink);background:#fff;
  }
  .field textarea{resize:vertical;min-height:84px}
  .field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(23,115,200,.15)}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width:480px){.row2{grid-template-columns:1fr}}
  /* Additional fields: 2-column grid, spanning items for wide fields */
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
  .grid2 .span2{grid-column:1 / -1}
  @media (max-width:560px){.grid2{grid-template-columns:1fr}}
  /* Container tabs — prominent selector at the very top (end-user friendly) */
  .tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
  .tab{appearance:none;background:#fff;border:2px solid var(--line);border-radius:12px;
    padding:13px 20px;cursor:pointer;font:600 15px/1.25 inherit;color:var(--ink);
    display:inline-flex;flex-direction:column;gap:3px;min-width:140px;text-align:left;
    transition:border-color .12s,box-shadow .12s,background .12s}
  .tab:hover{border-color:var(--primary)}
  .tab.active{border-color:var(--primary);background:rgba(23,115,200,.08);color:var(--primary-d);
    box-shadow:0 0 0 3px rgba(23,115,200,.14)}
  .tab .count{color:var(--muted);font-weight:500;font-size:12px}
  .tab.active .count{color:var(--primary)}
  .tabpanel{display:none}
  .tabpanel.active{display:block}
  .toplegend{font-size:14px;font-weight:700;color:var(--ink);margin:0 0 4px}
  .tophelp{font-size:12.5px;color:var(--muted);margin:0 0 14px}
  .err{color:var(--danger);font-size:12.5px;margin:8px 0 0;display:none}
  .btn{border:none;border-radius:10px;padding:0 20px;min-height:48px;font:600 15px/1 inherit;cursor:pointer;background:var(--primary);color:#fff;width:100%;margin-top:8px}
  .btn:disabled{background:#a9c6e4;cursor:not-allowed}
  .ticket-ok{background:#eaf7ef;border:1px solid #bfe6cd;color:#0f5c33;border-radius:var(--radius);padding:22px;text-align:center}
  .ticket-ok strong{font-size:26px;display:block;margin-top:6px}
  .ticket-ok .again{margin-top:16px}
  .ticket-ok .again button{background:none;border:none;color:var(--primary);font:600 14px/1 inherit;cursor:pointer}
  .disabled-note{padding:30px 22px;text-align:center;color:var(--muted)}
  .hidden{display:none !important}

  /* Floating chat */
  .fab{position:fixed;right:20px;bottom:20px;z-index:1000;height:54px;min-width:54px;padding:0 20px;border:none;border-radius:27px;
    background:var(--primary);color:#fff;font:600 15px/1 inherit;box-shadow:0 6px 20px rgba(23,115,200,.35);cursor:pointer}
  .chatpanel{position:fixed;right:20px;bottom:86px;z-index:1000;width:380px;max-width:calc(100vw - 32px);
    height:560px;max-height:calc(100vh - 120px);background:#fff;border:1px solid var(--line);border-radius:16px;
    box-shadow:0 12px 40px rgba(15,23,42,.28);display:none;flex-direction:column;overflow:hidden}
  .chatpanel.open{display:flex}
  .chatpanel .ch-head{background:var(--primary);color:#fff;padding:12px 14px;display:flex;align-items:center;gap:8px;flex:0 0 auto}
  .chatpanel .ch-head h2{font-size:14px;margin:0;font-weight:600;flex:1}
  .chatpanel .ch-head button{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1}
  #log{flex:1 1 auto;min-height:0;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px}
  #log>*{flex-shrink:0}
  .msg{max-width:88%;padding:9px 12px;border-radius:12px;white-space:pre-wrap;word-wrap:break-word;overflow-wrap:anywhere}
  .msg.bot{background:var(--bot);color:var(--ink);align-self:flex-start;border-bottom-left-radius:4px}
  .msg.user{background:var(--user);color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
  .msg.sys{align-self:center;background:transparent;color:var(--muted);font-size:12.5px;text-align:center;max-width:95%}
  .chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start;max-width:92%}
  .chip{border:1px solid var(--primary);color:var(--primary);background:#fff;border-radius:16px;padding:6px 12px;font-size:13px;cursor:pointer}
  .chip:hover{background:var(--primary);color:#fff}
  .dcard{align-self:stretch;background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px}
  .dcard h3{font-size:12px;margin:0 0 8px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.03em}
  .dcard dl{margin:0;display:grid;grid-template-columns:auto minmax(0,1fr);gap:4px 10px;font-size:13px}
  .dcard dt{color:var(--muted);overflow-wrap:anywhere}
  .dcard dd{margin:0;font-weight:500;overflow-wrap:anywhere;word-break:break-word}
  .dcard .actions{display:flex;gap:8px;margin-top:12px}
  .dcard .actions button{flex:1;min-height:42px;border-radius:10px;border:none;font:600 14px/1 inherit;cursor:pointer}
  .dcard .actions .primary{background:var(--primary);color:#fff}
  .dcard .actions .ghost{background:#fff;border:1px solid var(--line);color:var(--ink)}
  .typing{align-self:flex-start;color:var(--muted);font-size:13px;padding:2px}
  .ch-foot{flex:0 0 auto;border-top:1px solid var(--line);padding:10px;display:flex;gap:8px}
  #ctext{flex:1;min-height:42px;max-height:110px;resize:none;border:1px solid var(--line);border-radius:10px;padding:10px 12px;font:inherit}
  #ctext:focus{outline:none;border-color:var(--primary)}
  .ch-foot button{border:none;border-radius:10px;padding:0 14px;min-height:42px;font:600 14px/1 inherit;cursor:pointer;background:var(--primary);color:#fff}
</style>
</head>
<body>

<?php if (empty($formReady) || empty($categories)): ?>
  <div class="wrap"><div class="card">
    <header><h1><?= esc($title) ?></h1></header>
    <div class="disabled-note">
      La mesa de ayuda en línea no está disponible en este momento. Intenta más tarde o contacta a soporte por los canales habituales.
    </div>
  </div></div>
<?php else: ?>
  <div class="wrap">
    <div class="card">
      <header>
        <h1><?= esc($title) ?></h1>
        <p>Levanta tu ticket de soporte</p>
      </header>
      <div class="body">
        <p class="intro"><?= esc($intro) ?></p>

        <!-- Success state -->
        <div id="okState" class="hidden">
          <div class="ticket-ok">
            Tu ticket se creó correctamente.
            <strong id="okNum">Ticket #—</strong>
            <div class="again"><button type="button" id="againBtn">Levantar otro ticket</button></div>
          </div>
        </div>

        <form id="ticketForm" novalidate>
          <?php if (! empty($extraGroups)): ?>
            <fieldset>
              <p class="toplegend">¿Sobre qué es tu solicitud?</p>
              <p class="tophelp">Elige el tipo que corresponda. Según lo que elijas, te pediremos algunos datos adicionales.</p>
              <div class="tabs" role="tablist">
                <?php foreach ($extraGroups as $i => $g): ?>
                  <button type="button" class="tab <?= $i === 0 ? 'active' : '' ?>" data-tab="xtab-<?= $i ?>" role="tab">
                    <?= esc($g['label']) ?> <span class="count"><?= count($g['fields']) ?> campos</span>
                  </button>
                <?php endforeach; ?>
              </div>
              <?php foreach ($extraGroups as $i => $g): ?>
                <div class="tabpanel <?= $i === 0 ? 'active' : '' ?>" id="xtab-<?= $i ?>" role="tabpanel">
                  <div class="grid2">
                    <?php foreach ($g['fields'] as $fld): ?>
                      <?php
                        $fid   = 'x_' . md5($g['id'] . '|' . $fld['header']);
                        $type  = $fld['type'];
                        $opts  = $fld['options'] ?? [];
                        // Long free-text fields span both columns for breathing room.
                        $span  = (empty($opts) && ! in_array($type, ['date', 'datetime', 'number'], true)) ? ' span2' : '';
                      ?>
                      <div class="field<?= $span ?>">
                        <label for="<?= $fid ?>">
                          <?= esc($fld['header']) ?>
                          <?php if (! empty($fld['required'])): ?><span class="req">*</span><?php endif; ?>
                        </label>
                        <?php if (! empty($opts)): ?>
                          <select id="<?= $fid ?>" data-header="<?= esc($fld['header'], 'attr') ?>" data-extra <?= ! empty($fld['required']) ? 'data-required' : '' ?>>
                            <option value="">—</option>
                            <?php foreach ($opts as $o): ?>
                              <option value="<?= esc($o, 'attr') ?>"><?= esc($o) ?></option>
                            <?php endforeach; ?>
                          </select>
                        <?php elseif (in_array($type, ['date', 'datetime'], true)): ?>
                          <input type="date" id="<?= $fid ?>" data-header="<?= esc($fld['header'], 'attr') ?>" data-extra <?= ! empty($fld['required']) ? 'data-required' : '' ?>>
                        <?php elseif ($type === 'number'): ?>
                          <input type="number" id="<?= $fid ?>" data-header="<?= esc($fld['header'], 'attr') ?>" data-extra <?= ! empty($fld['required']) ? 'data-required' : '' ?>>
                        <?php else: ?>
                          <input type="text" id="<?= $fid ?>" maxlength="255" data-header="<?= esc($fld['header'], 'attr') ?>" data-extra <?= ! empty($fld['required']) ? 'data-required' : '' ?>>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </fieldset>
          <?php endif; ?>

          <fieldset<?= ! empty($extraGroups) ? ' class="grp"' : '' ?>>
            <legend>Tus datos</legend>
            <div class="field">
              <label for="f_nombre">Nombre completo <span class="req">*</span></label>
              <input type="text" id="f_nombre" maxlength="190" autocomplete="name" required>
            </div>
            <div class="row2">
              <div class="field">
                <label for="f_correo">Correo</label>
                <input type="email" id="f_correo" maxlength="190" autocomplete="email">
              </div>
              <div class="field">
                <label for="f_emp">No. de empleado</label>
                <input type="text" id="f_emp" maxlength="40" inputmode="numeric" autocomplete="off">
              </div>
            </div>
          </fieldset>

          <fieldset class="grp">
            <legend>Tu solicitud</legend>
            <div class="field">
              <label for="f_cat">Categoría <span class="req">*</span></label>
              <select id="f_cat" required>
                <option value="">Selecciona una categoría…</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row2">
              <div class="field">
                <label for="f_titulo">Asunto <span class="req">*</span></label>
                <input type="text" id="f_titulo" maxlength="255" required>
              </div>
              <div class="field">
                <label for="f_tipo">Tipo</label>
                <select id="f_tipo">
                  <option value="INCIDENCIA">Incidencia (algo falla)</option>
                  <option value="REQUERIMIENTO">Requerimiento (una petición)</option>
                </select>
              </div>
            </div>
            <div class="field">
              <label for="f_desc">Descripción <span class="req">*</span></label>
              <textarea id="f_desc" rows="4" required placeholder="Cuéntanos con detalle qué ocurre o qué necesitas."></textarea>
            </div>
            <div class="field">
              <label for="f_ubic">Ubicación <span class="req">*</span></label>
              <input type="text" id="f_ubic" maxlength="190" placeholder="Sitio, área o sucursal" required>
            </div>
          </fieldset>

          <div class="err" id="formErr"></div>
          <button type="submit" class="btn" id="submitBtn">Crear ticket</button>
        </form>
      </div>
    </div>
  </div>

  <?php if (! empty($chatReady)): ?>
    <!-- Floating chat assistant -->
    <button type="button" class="fab" id="fab">Chat de ayuda</button>
    <div class="chatpanel" id="chatpanel" role="dialog" aria-label="Asistente de soporte">
      <div class="ch-head">
        <span aria-hidden="true">💬</span>
        <h2>Asistente</h2>
        <button type="button" id="chatClose" aria-label="Cerrar">&times;</button>
      </div>
      <div id="log" aria-live="polite"></div>
      <div class="ch-foot">
        <textarea id="ctext" rows="1" placeholder="Escribe tu mensaje..." aria-label="Mensaje"></textarea>
        <button id="csend" type="button">Enviar</button>
      </div>
    </div>
  <?php endif; ?>

  <script>
  (function () {
    "use strict";
    var CFG = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    // ---- Shared: read the requester identity + category from the form live ----
    function readIdentity() {
      return {
        nombre: (document.getElementById("f_nombre").value || "").trim(),
        correo: (document.getElementById("f_correo").value || "").trim(),
        numero_empleado: (document.getElementById("f_emp").value || "").trim()
      };
    }
    function readCategory() {
      var sel = document.getElementById("f_cat");
      return { id: parseInt(sel.value, 10) || 0, name: sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : "" };
    }
    function post(url, payload) {
      return fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); });
    }
    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
      });
    }

    // =================== Complete form ===================
    var form = document.getElementById("ticketForm");
    var formErr = document.getElementById("formErr");
    var submitBtn = document.getElementById("submitBtn");
    var okState = document.getElementById("okState");

    // ---- Additional-fields tabs (one per container) ----
    var tabBtns = Array.prototype.slice.call(document.querySelectorAll(".tab"));
    function activateTab(id) {
      tabBtns.forEach(function (b) {
        var on = b.dataset.tab === id;
        b.classList.toggle("active", on);
        var p = document.getElementById(b.dataset.tab);
        if (p) { p.classList.toggle("active", on); }
      });
    }
    tabBtns.forEach(function (b) {
      b.addEventListener("click", function () { activateTab(b.dataset.tab); });
    });
    function revealTabOf(el) {
      var panel = el && el.closest ? el.closest(".tabpanel") : null;
      if (panel) { activateTab(panel.id); }
    }

    function collectExtras() {
      var fields = {}, missing = [];
      document.querySelectorAll("[data-extra]").forEach(function (el) {
        var v = (el.value || "").trim();
        if (v !== "") { fields[el.dataset.header] = v; }
        else if (el.hasAttribute("data-required")) { missing.push(el.dataset.header); }
      });
      return { fields: fields, missing: missing };
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      formErr.style.display = "none";

      var identity = readIdentity();
      var cat = readCategory();
      var titulo = document.getElementById("f_titulo").value.trim();
      var descripcion = document.getElementById("f_desc").value.trim();
      var ubicacion = document.getElementById("f_ubic").value.trim();
      var tipo = document.getElementById("f_tipo").value;

      var missing = [];
      if (!identity.nombre) { missing.push("nombre"); }
      if (cat.id <= 0) { missing.push("categoría"); }
      if (!titulo) { missing.push("asunto"); }
      if (!descripcion) { missing.push("descripción"); }
      if (!ubicacion) { missing.push("ubicación"); }
      var extras = collectExtras();
      missing = missing.concat(extras.missing);
      if (missing.length) {
        formErr.textContent = "Completa: " + missing.join(", ") + ".";
        formErr.style.display = "block";
        // Jump to the tab holding the first empty required additional field.
        var badExtra = null;
        document.querySelectorAll("[data-extra][data-required]").forEach(function (el) {
          if (!badExtra && (el.value || "").trim() === "") { badExtra = el; }
        });
        if (badExtra) { revealTabOf(badExtra); }
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = "Creando...";
      post(CFG.submitUrl, {
        identity: identity,
        categoryId: cat.id,
        titulo: titulo,
        tipo: tipo,
        descripcion: descripcion,
        ubicacion: ubicacion,
        fields: extras.fields
      }).then(function (res) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Crear ticket";
        if (!res.ok) {
          formErr.textContent = (res.body && res.body.message) || "No se pudo crear el ticket.";
          formErr.style.display = "block";
          return;
        }
        var t = (res.body.data && res.body.data.ticketId) || 0;
        document.getElementById("okNum").textContent = "Ticket #" + t;
        form.classList.add("hidden");
        okState.classList.remove("hidden");
        window.scrollTo({ top: 0, behavior: "smooth" });
      }).catch(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = "Crear ticket";
        formErr.textContent = "Hubo un problema de conexión. Intenta de nuevo.";
        formErr.style.display = "block";
      });
    });

    document.getElementById("againBtn").addEventListener("click", function () {
      form.reset();
      form.classList.remove("hidden");
      okState.classList.add("hidden");
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    document.getElementById("f_nombre").focus();

    // =================== Floating chat (optional) ===================
    if (!CFG.chatReady) { return; }

    var fab = document.getElementById("fab");
    var panel = document.getElementById("chatpanel");
    var log = document.getElementById("log");
    var ctext = document.getElementById("ctext");
    var csend = document.getElementById("csend");
    var history = [];
    var draft = null;
    var busy = false;
    var greeted = false;

    fab.addEventListener("click", function () {
      var open = panel.classList.toggle("open");
      fab.textContent = open ? "Cerrar" : "Chat de ayuda";
      if (open && !greeted) {
        greeted = true;
        var n = readIdentity().nombre.split(/\s+/)[0];
        addMsg("bot", (n ? "Hola, " + n + ". " : "Hola. ") + "Cuéntame qué necesitas y te ayudo a levantar tu ticket. Tomaré tus datos y la categoría del formulario.");
      }
      if (open) { ctext.focus(); }
    });
    document.getElementById("chatClose").addEventListener("click", function () {
      panel.classList.remove("open"); fab.textContent = "Chat de ayuda";
    });

    function scroll() {
      var raf = window.requestAnimationFrame || function (cb) { return setTimeout(cb, 16); };
      raf(function () { log.scrollTop = log.scrollHeight; });
    }
    function addMsg(role, content) {
      var d = document.createElement("div"); d.className = "msg " + role; d.textContent = content;
      log.appendChild(d); scroll(); return d;
    }
    function addChips(options) {
      if (!options || !options.length) { return; }
      var wrap = document.createElement("div"); wrap.className = "chips";
      options.forEach(function (opt) {
        var c = document.createElement("button");
        c.type = "button"; c.className = "chip"; c.textContent = opt;
        c.addEventListener("click", function () { wrap.remove(); csubmit(opt); });
        wrap.appendChild(c);
      });
      log.appendChild(wrap); scroll();
    }
    function fieldRow(dl, label, value) {
      if (!value) { return; }
      var dt = document.createElement("dt"); dt.textContent = label;
      var dd = document.createElement("dd"); dd.textContent = value;
      dl.appendChild(dt); dl.appendChild(dd);
    }
    function showDraft(d) {
      draft = d;
      var cat = readCategory();
      var card = document.createElement("div"); card.className = "dcard";
      var h = document.createElement("h3"); h.textContent = "Resumen del ticket";
      var dl = document.createElement("dl");
      fieldRow(dl, "Asunto", d.titulo);
      fieldRow(dl, "Detalle", d.descripcion);
      fieldRow(dl, "Tipo", d.tipo === "REQUERIMIENTO" ? "Requerimiento" : "Incidencia");
      fieldRow(dl, "Categoría", cat.name);
      fieldRow(dl, "Solicitante", readIdentity().nombre);
      fieldRow(dl, "Ubicación", d.ubicacion);
      if (d.es_hardware) { fieldRow(dl, "Equipo", d.equipo); fieldRow(dl, "Modelo", d.modelo); fieldRow(dl, "Serie", d.serie); }
      var actions = document.createElement("div"); actions.className = "actions";
      var ok = document.createElement("button"); ok.className = "primary"; ok.type = "button"; ok.textContent = "Crear ticket";
      var edit = document.createElement("button"); edit.className = "ghost"; edit.type = "button"; edit.textContent = "Ajustar";
      ok.addEventListener("click", function () { card.remove(); ccreate(); });
      edit.addEventListener("click", function () { card.remove(); ctext.focus(); });
      actions.appendChild(ok); actions.appendChild(edit);
      card.appendChild(h); card.appendChild(dl); card.appendChild(actions);
      log.appendChild(card); scroll();
    }
    function typing(on) {
      var t = document.getElementById("ctyping");
      if (on) { if (!t) { t = document.createElement("div"); t.id = "ctyping"; t.className = "typing"; t.textContent = "Escribiendo..."; log.appendChild(t); scroll(); } }
      else if (t) { t.remove(); }
    }
    function setBusy(on) { busy = on; csend.disabled = on; ctext.disabled = on; }

    function csubmit(message) {
      message = (message || "").trim();
      if (!message || busy) { return; }
      addMsg("user", message);
      setBusy(true); typing(true);
      post(CFG.chatUrl, { history: history, message: message, identity: readIdentity() }).then(function (res) {
        typing(false); setBusy(false);
        if (!res.ok) { addMsg("sys", (res.body && res.body.message) || "No se pudo enviar el mensaje."); return; }
        var d = res.body.data || {};
        history = d.history || history;
        if (d.reply) { addMsg("bot", d.reply); }
        if (d.question && d.question.options) { addChips(d.question.options); }
        if (d.draft) { showDraft(d.draft); }
        ctext.focus();
      }).catch(function () { typing(false); setBusy(false); addMsg("sys", "Hubo un problema de conexión. Intenta de nuevo."); });
    }
    function ccreate() {
      if (!draft || busy) { return; }
      var cat = readCategory();
      if (cat.id <= 0) { addMsg("sys", "Elige una categoría en el formulario para poder crear el ticket."); return; }
      setBusy(true); typing(true);
      post(CFG.ticketUrl, { draft: draft, identity: readIdentity(), categoryId: cat.id }).then(function (res) {
        typing(false); setBusy(false);
        if (!res.ok) { addMsg("sys", (res.body && res.body.message) || "No se pudo crear el ticket."); return; }
        var t = (res.body.data && res.body.data.ticketId) || 0;
        addMsg("bot", "Listo, tu ticket #" + t + " se creó correctamente.");
        addMsg("sys", "Si necesitas reportar algo más, escríbeme.");
        draft = null; history = [];
      }).catch(function () { typing(false); setBusy(false); addMsg("sys", "Hubo un problema al crear el ticket. Intenta de nuevo."); });
    }

    csend.addEventListener("click", function () { var v = ctext.value; ctext.value = ""; autoGrow(); csubmit(v); });
    ctext.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); var v = ctext.value; ctext.value = ""; autoGrow(); csubmit(v); }
    });
    function autoGrow() { ctext.style.height = "auto"; ctext.style.height = Math.min(ctext.scrollHeight, 110) + "px"; }
    ctext.addEventListener("input", autoGrow);
  })();
  </script>
<?php endif; ?>

</body>
</html>
