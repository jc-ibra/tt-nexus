<?php
$b = $bootstrap;
$identity = $identity ?? ['nombre' => '', 'correo' => '', 'numero_empleado' => ''];
$cfg = [
    'chatUrl'   => $chatUrl,
    'ticketUrl' => $ticketUrl,
    'welcome'   => $b['welcome'],
    'identity'  => [
        'nombre'          => (string) ($identity['nombre'] ?? ''),
        'correo'          => (string) ($identity['correo'] ?? ''),
        'numero_empleado' => (string) ($identity['numero_empleado'] ?? ''),
    ],
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= esc($b['title']) ?></title>
<style>
  :root{
    --primary:#1773C8; --primary-d:#125aa0;
    --bg:#f6f8fb; --card:#ffffff; --ink:#1f2733; --muted:#66707d;
    --line:#e4e8ee; --user:#1773C8; --bot:#eef1f5; --ok:#0f8a4f; --danger:#c0362c;
    --radius:12px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;height:100%}
  body{
    font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    color:var(--ink);background:var(--bg);display:flex;flex-direction:column;height:100%;
  }
  header{
    background:var(--primary);color:#fff;padding:14px 16px;display:flex;align-items:center;gap:10px;flex:0 0 auto;
  }
  header .dot{width:9px;height:9px;border-radius:50%;background:#7fe0a5;box-shadow:0 0 0 3px rgba(127,224,165,.25)}
  header h1{font-size:15px;font-weight:600;margin:0}
  header p{font-size:12px;margin:0;opacity:.85}
  #log{flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden;padding:16px;display:flex;flex-direction:column;gap:10px}
  /* Keep bubbles/cards at natural height so #log overflows and scrolls,
     instead of flex-shrinking children to fit (which hid the summary card). */
  #log>*{flex-shrink:0}
  .msg{max-width:85%;padding:9px 12px;border-radius:var(--radius);white-space:pre-wrap;word-wrap:break-word;overflow-wrap:anywhere}
  .msg.bot{background:var(--bot);color:var(--ink);align-self:flex-start;border-bottom-left-radius:4px}
  .msg.user{background:var(--user);color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
  .msg.sys{align-self:center;background:transparent;color:var(--muted);font-size:12.5px;text-align:center;max-width:95%}
  .chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start;max-width:90%}
  .chip{border:1px solid var(--primary);color:var(--primary);background:#fff;border-radius:16px;padding:6px 12px;font-size:13px;cursor:pointer}
  .chip:hover{background:var(--primary);color:#fff}
  .card{align-self:stretch;background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:12px;overflow:hidden}
  .card h2{font-size:13px;margin:0 0 8px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.03em}
  .card dl{margin:0;display:grid;grid-template-columns:auto minmax(0,1fr);gap:4px 10px;font-size:13px}
  .card dt{color:var(--muted);min-width:0;overflow-wrap:anywhere}
  .card dd{margin:0;font-weight:500;min-width:0;overflow-wrap:anywhere;word-break:break-word}
  .card .actions{display:flex;gap:8px;margin-top:12px}
  .typing{align-self:flex-start;color:var(--muted);font-size:13px;padding:4px 2px}
  .ticket-ok{align-self:stretch;background:#eaf7ef;border:1px solid #bfe6cd;color:#0f5c33;border-radius:var(--radius);padding:14px;text-align:center}
  .ticket-ok strong{font-size:20px;display:block;margin-top:4px}
  footer{flex:0 0 auto;border-top:1px solid var(--line);background:#fff;padding:10px;display:flex;gap:8px}
  #text{flex:1;min-height:44px;max-height:120px;resize:none;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font:inherit;color:var(--ink)}
  #text:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(23,115,200,.15)}
  button.send,button.act{border:none;border-radius:10px;padding:0 16px;min-height:44px;font:600 14px/1 inherit;cursor:pointer}
  button.send{background:var(--primary);color:#fff}
  button.send:disabled{background:#a9c6e4;cursor:not-allowed}
  button.act.primary{background:var(--primary);color:#fff}
  button.act.ghost{background:#fff;border:1px solid var(--line);color:var(--ink)}
  .disabled-note{padding:24px 16px;text-align:center;color:var(--muted)}
</style>
</head>
<body>

<?php if (empty($b['ready'])): ?>
  <header><span class="dot" style="background:#f0c674;box-shadow:none"></span><div><h1><?= esc($b['title']) ?></h1></div></header>
  <div class="disabled-note">El asistente no está disponible en este momento. Intenta más tarde o contacta a la mesa de ayuda.</div>
<?php else: ?>
  <header>
    <span class="dot"></span>
    <div>
      <h1><?= esc($b['title']) ?></h1>
      <p>Autoservicio de tickets</p>
    </div>
  </header>

  <div id="log" aria-live="polite"></div>

  <footer>
    <textarea id="text" rows="1" placeholder="Escribe tu mensaje..." aria-label="Mensaje"></textarea>
    <button class="send" id="send" type="button">Enviar</button>
  </footer>

  <script>
  (function () {
    "use strict";
    var CFG = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var IDENTITY = CFG.identity || { nombre: "", correo: "", numero_empleado: "" };
    var log = document.getElementById("log");
    var text = document.getElementById("text");
    var send = document.getElementById("send");
    var history = [];
    var draft = null;
    var busy = false;

    // Defer to the next frame so newly rendered content (e.g. the summary card,
    // whose height depends on text wrapping) is laid out before we scroll.
    function scroll() {
      var raf = window.requestAnimationFrame || function (cb) { return setTimeout(cb, 16); };
      raf(function () { log.scrollTop = log.scrollHeight; });
    }

    function addMsg(role, content) {
      var d = document.createElement("div");
      d.className = "msg " + role;
      d.textContent = content;
      log.appendChild(d);
      scroll();
      return d;
    }

    function addChips(options, allowFree) {
      if (!options || !options.length) { return; }
      var wrap = document.createElement("div");
      wrap.className = "chips";
      options.forEach(function (opt) {
        var c = document.createElement("button");
        c.type = "button";
        c.className = "chip";
        c.textContent = opt;
        c.addEventListener("click", function () {
          wrap.remove();
          submit(opt);
        });
        wrap.appendChild(c);
      });
      log.appendChild(wrap);
      scroll();
    }

    function fieldRow(dl, label, value) {
      if (!value) { return; }
      var dt = document.createElement("dt"); dt.textContent = label;
      var dd = document.createElement("dd"); dd.textContent = value;
      dl.appendChild(dt); dl.appendChild(dd);
    }

    function showDraft(d) {
      draft = d;
      var card = document.createElement("div");
      card.className = "card";
      var h = document.createElement("h2"); h.textContent = "Resumen del ticket";
      var dl = document.createElement("dl");
      fieldRow(dl, "Asunto", d.titulo);
      fieldRow(dl, "Detalle", d.descripcion);
      fieldRow(dl, "Tipo", d.tipo === "REQUERIMIENTO" ? "Requerimiento" : "Incidencia");
      fieldRow(dl, "Solicitante", d.solicitante);
      fieldRow(dl, "Correo", d.correo);
      fieldRow(dl, "No. de empleado", d.numero_empleado);
      fieldRow(dl, "Ubicación", d.ubicacion);
      if (d.es_hardware) {
        fieldRow(dl, "Equipo", d.equipo);
        fieldRow(dl, "Modelo", d.modelo);
        fieldRow(dl, "Serie", d.serie);
      }
      var actions = document.createElement("div");
      actions.className = "actions";
      var ok = document.createElement("button");
      ok.className = "act primary"; ok.type = "button"; ok.textContent = "Crear ticket";
      var edit = document.createElement("button");
      edit.className = "act ghost"; edit.type = "button"; edit.textContent = "Ajustar";
      ok.addEventListener("click", function () {
        card.remove();
        createTicket();
      });
      edit.addEventListener("click", function () {
        card.remove();
        text.focus();
      });
      actions.appendChild(ok); actions.appendChild(edit);
      card.appendChild(h); card.appendChild(dl); card.appendChild(actions);
      log.appendChild(card);
      scroll();
    }

    function typing(on) {
      var t = document.getElementById("typing");
      if (on) {
        if (!t) {
          t = document.createElement("div");
          t.id = "typing"; t.className = "typing"; t.textContent = "Escribiendo...";
          log.appendChild(t); scroll();
        }
      } else if (t) { t.remove(); }
    }

    function setBusy(on) {
      busy = on;
      send.disabled = on;
      text.disabled = on;
    }

    function post(url, payload) {
      return fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      });
    }

    function submit(message) {
      message = (message || "").trim();
      if (!message || busy) { return; }
      addMsg("user", message);
      setBusy(true); typing(true);
      post(CFG.chatUrl, { history: history, message: message, identity: IDENTITY }).then(function (res) {
        typing(false); setBusy(false);
        if (!res.ok) {
          addMsg("sys", (res.body && res.body.message) || "No se pudo enviar el mensaje.");
          return;
        }
        var d = res.body.data || {};
        history = d.history || history;
        if (d.reply) { addMsg("bot", d.reply); }
        if (d.question && d.question.options) { addChips(d.question.options, d.question.allowFreeText); }
        if (d.draft) { showDraft(d.draft); }
        text.focus();
      }).catch(function () {
        typing(false); setBusy(false);
        addMsg("sys", "Hubo un problema de conexión. Intenta de nuevo.");
      });
    }

    function createTicket() {
      if (!draft || busy) { return; }
      setBusy(true); typing(true);
      post(CFG.ticketUrl, { draft: draft, identity: IDENTITY }).then(function (res) {
        typing(false); setBusy(false);
        if (!res.ok) {
          addMsg("sys", (res.body && res.body.message) || "No se pudo crear el ticket.");
          return;
        }
        var t = (res.body.data && res.body.data.ticketId) || 0;
        var ok = document.createElement("div");
        ok.className = "ticket-ok";
        ok.innerHTML = "Tu ticket se creó correctamente.";
        var n = document.createElement("strong");
        n.textContent = "Ticket #" + t;
        ok.appendChild(n);
        log.appendChild(ok);
        addMsg("sys", "Puedes cerrar esta ventana. Si necesitas reportar algo más, escríbeme.");
        draft = null;
        history = [];
        scroll();
      }).catch(function () {
        typing(false); setBusy(false);
        addMsg("sys", "Hubo un problema al crear el ticket. Intenta de nuevo.");
      });
    }

    send.addEventListener("click", function () {
      var v = text.value; text.value = ""; autoGrow();
      submit(v);
    });
    text.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        var v = text.value; text.value = ""; autoGrow();
        submit(v);
      }
    });
    function autoGrow() {
      text.style.height = "auto";
      text.style.height = Math.min(text.scrollHeight, 120) + "px";
    }
    text.addEventListener("input", autoGrow);

    if (CFG.welcome) {
      var firstName = (IDENTITY.nombre || "").trim().split(/\s+/)[0];
      addMsg("bot", firstName ? ("Hola, " + firstName + ". " + CFG.welcome) : CFG.welcome);
    }
    text.focus();
  })();
  </script>
<?php endif; ?>

</body>
</html>
