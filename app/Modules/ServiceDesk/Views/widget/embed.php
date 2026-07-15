(function () {
  "use strict";
  if (window.__ttSupportWidget) { return; }
  window.__ttSupportWidget = true;

  var FRAME_URL = <?= json_encode($frameUrl, JSON_UNESCAPED_SLASHES) ?>;
  var PRIMARY = "#1773C8";
  var open = false;

  // Requester identity supplied by the host (intranet session). Set BEFORE this
  // loader runs, e.g.:
  //   window.ttSupportWidgetUser = { nombre: "Ana López", correo: "ana@...", numero_empleado: "1010" };
  // Forwarded to the iframe so the assistant greets by name and no longer asks
  // for these; they are appended to the ticket detail on creation.
  function withIdentity(url) {
    var u = window.ttSupportWidgetUser;
    if (!u || typeof u !== "object") { return url; }
    var parts = [];
    ["nombre", "correo", "numero_empleado"].forEach(function (k) {
      var v = u[k];
      if (v !== undefined && v !== null && String(v) !== "") {
        parts.push(k + "=" + encodeURIComponent(String(v)));
      }
    });
    if (!parts.length) { return url; }
    return url + (url.indexOf("?") === -1 ? "?" : "&") + parts.join("&");
  }

  function el(tag, style) {
    var node = document.createElement(tag);
    if (style) { node.style.cssText = style; }
    return node;
  }

  // Launcher button
  var btn = el("button",
    "position:fixed;right:20px;bottom:20px;z-index:2147483000;" +
    "height:52px;min-width:52px;padding:0 20px;border:none;border-radius:26px;" +
    "background:" + PRIMARY + ";color:#fff;font:600 15px/1 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;" +
    "box-shadow:0 6px 20px rgba(23,115,200,.35);cursor:pointer;transition:transform .12s ease;");
  btn.type = "button";
  btn.setAttribute("aria-label", "Abrir asistente de Mesa de Ayuda");
  btn.textContent = "Mesa de Ayuda";
  btn.onmouseenter = function () { btn.style.transform = "translateY(-1px)"; };
  btn.onmouseleave = function () { btn.style.transform = "none"; };

  // Panel + iframe (lazy: src set on first open)
  var panel = el("div",
    "position:fixed;right:20px;bottom:84px;z-index:2147483000;" +
    "width:390px;max-width:calc(100vw - 32px);height:620px;max-height:calc(100vh - 110px);" +
    "background:#fff;border-radius:16px;overflow:hidden;display:none;" +
    "box-shadow:0 12px 40px rgba(15,23,42,.28);border:1px solid rgba(15,23,42,.08);");

  var iframe = el("iframe",
    "width:100%;height:100%;border:none;display:block;");
  iframe.title = "Asistente de soporte";
  iframe.allow = "";
  panel.appendChild(iframe);

  function setOpen(next) {
    open = next;
    if (open && !iframe.src) { iframe.src = withIdentity(FRAME_URL); }
    panel.style.display = open ? "block" : "none";
    btn.textContent = open ? "Cerrar" : "Soporte";
    btn.setAttribute("aria-label", open ? "Cerrar asistente de soporte" : "Abrir asistente de soporte");
  }

  btn.addEventListener("click", function () { setOpen(!open); });

  // Let the iframe ask to close itself (after a ticket is created, if desired).
  window.addEventListener("message", function (e) {
    if (e && e.data === "tt-widget-close") { setOpen(false); }
  });

  function mount() {
    document.body.appendChild(panel);
    document.body.appendChild(btn);
  }
  if (document.body) { mount(); }
  else { document.addEventListener("DOMContentLoaded", mount); }
})();
