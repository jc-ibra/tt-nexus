/**
 * Shared by show.php and the reading-pane partial (preview.php, hosted inside
 * inbox.php's #gm-reader). Etapa 3: a thread page ships only metadata; a
 * message's body is fetched on demand from Dispatch::messageBody() and
 * assigned to the iframe as a PROPERTY (`frame.srcdoc = …`), never as an HTML
 * attribute — that's what avoids re-escaping a body that was already escaped
 * once building the page, which is what made a long thread's HTML balloon.
 *
 * Call MDThread.init(container) once per container that holds a `[data-thread]`
 * (show.php calls it on the whole document; inbox.php calls it on #gm-reader
 * right after each AJAX load, since that content didn't exist at page-load
 * time).
 */
(function (global) {
  'use strict';

  // Auto-ajusta la altura del iframe del correo a su contenido real, sin
  // scroll interno. Requiere sandbox="allow-same-origin" (sin allow-scripts)
  // para poder leer el documento embebido; el HTML del correo sigue sin poder
  // ejecutar JS.
  function fitFrame(f) {
    try {
      var d = f.contentWindow.document;
      var fit = function () {
        var h = Math.max(d.body ? d.body.scrollHeight : 0, d.documentElement ? d.documentElement.scrollHeight : 0);
        if (h > 0) {
          var avail = window.innerHeight - f.getBoundingClientRect().top - 24;
          var maxH = Math.max(280, avail);
          f.style.height = Math.min(h + 28, maxH) + 'px';
        }
      };
      fit();
      Array.prototype.forEach.call(d.images || [], function (img) {
        if (!img.complete) { img.addEventListener('load', fit); img.addEventListener('error', fit); }
      });
      setTimeout(fit, 300);
    } catch (e) { /* cross-origin u otro: se queda con min-height */ }
  }

  function buildFrame() {
    var frame = document.createElement('iframe');
    frame.className = 'md-msg-body-frame';
    // Mismo sandbox que antes, sin allow-scripts: allow-same-origin es lo que
    // permite a fitFrame() leer el documento embebido para ajustar la altura;
    // el correo sigue sin poder ejecutar JS.
    frame.setAttribute('sandbox', 'allow-same-origin');
    frame.addEventListener('load', function () { fitFrame(frame); });
    return frame;
  }

  function loading(slot) {
    slot.innerHTML = '<p class="md-body-loading text-muted text-sm" style="padding:var(--space-3) 0;">Cargando mensaje…</p>';
  }

  function failed(slot) {
    slot.innerHTML = '<p class="md-body-error text-sm" style="padding:var(--space-3) 0; color:var(--status-critical-text);">'
      + 'No se pudo cargar el mensaje. '
      + '<button type="button" class="btn btn-secondary md-body-retry">Reintentar</button></p>';
  }

  function loadBody(slot) {
    if (!slot || slot.dataset.loaded === '1' || slot.dataset.loading === '1') return;
    var url = slot.dataset.bodyUrl;
    if (!url) return;

    slot.dataset.loading = '1';
    loading(slot);

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (r.status === 304) throw new Error('304 sin cuerpo en caché local'); // no debería pasar vía fetch normal
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
      })
      .then(function (json) {
        if (!json || json.status !== 'success' || !json.data) throw new Error('bad payload');
        var data = json.data;

        slot.innerHTML = '';
        // Mismo orden que antes: destinatarios, luego adjuntos, luego el cuerpo.
        [data.recipients_html, data.files_html].forEach(function (fragmentHtml) {
          if (!fragmentHtml) return;
          var wrap = document.createElement('div');
          wrap.innerHTML = fragmentHtml;
          while (wrap.firstChild) slot.appendChild(wrap.firstChild);
        });

        if (data.is_html) {
          var frame = buildFrame();
          slot.appendChild(frame);
          frame.srcdoc = data.body || ''; // propiedad, no atributo: sin re-escapar
        } else {
          var pre = document.createElement('pre');
          pre.className = 'md-msg-pre';
          pre.textContent = data.body || '';
          slot.appendChild(pre);
        }

        delete slot.dataset.loading;
        slot.dataset.loaded = '1';
      })
      .catch(function () {
        delete slot.dataset.loading;
        failed(slot);
      });
  }

  function loadOlder(button) {
    if (button.disabled) return;
    var url = button.dataset.loadOlder;
    var thread = button.closest('[data-thread]');
    if (!url || !thread) return;

    button.disabled = true;
    var original = button.textContent;
    button.textContent = 'Cargando…';

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (json) {
        if (!json || json.status !== 'success' || !json.data) throw new Error('bad payload');
        var data = json.data;

        var wrap = document.createElement('div');
        wrap.innerHTML = data.html || '';
        var anchor = button; // el bloque anterior se inserta justo después del botón, dentro de [data-thread]
        Array.prototype.slice.call(wrap.querySelectorAll('.md-msg')).forEach(function (node) {
          var id = node.getAttribute('data-msg-id');
          // Red de seguridad: un mensaje nuevo llegado entre cargas no debe
          // provocar un duplicado si el cursor ya lo hubiera incluido.
          if (id && thread.querySelector('.md-msg[data-msg-id="' + id + '"]')) return;
          anchor.parentNode.insertBefore(node, anchor.nextSibling);
          anchor = node;
        });

        if (data.next_url) {
          button.dataset.loadOlder = data.next_url;
          button.textContent = 'Ver ' + data.remaining + ' mensajes anteriores';
          button.disabled = false;
        } else {
          button.parentNode.removeChild(button);
        }
      })
      .catch(function () {
        button.disabled = false;
        button.textContent = 'Reintentar';
      });
  }

  // El mensaje más reciente arranca expandido (marcado por el servidor sin
  // .is-collapsed): se hidrata de una vez, mismo camino que al expandir.
  // Se llama tanto desde init() (primera carga) como después de cada AJAX que
  // reemplace el contenido de un contenedor ya inicializado (inbox.php).
  function autoOpen(root) {
    if (!root) return;
    var openSlot = root.querySelector('.md-msg:not(.is-collapsed) .md-msg-body-slot');
    if (openSlot) loadBody(openSlot);
  }

  function init(root) {
    if (!root) return;
    autoOpen(root);
    if (root.dataset.mdThreadInit === '1') return; // los listeners de abajo ya están puestos
    root.dataset.mdThreadInit = '1';

    root.addEventListener('click', function (e) {
      var retry = e.target.closest('.md-body-retry');
      if (retry) {
        var slot = retry.closest('.md-msg-body-slot');
        if (slot) loadBody(slot);
        return;
      }

      var older = e.target.closest('[data-load-older]');
      if (older) {
        e.preventDefault();
        loadOlder(older);
        return;
      }

      var head = e.target.closest('.md-msg-head');
      if (!head || !root.contains(head)) return;
      var msg = head.closest('.md-msg');
      if (!msg) return;
      var collapsed = msg.classList.toggle('is-collapsed');
      head.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      if (!collapsed) {
        loadBody(msg.querySelector('.md-msg-body-slot'));
      }
    });

    root.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var head = e.target.closest('.md-msg-head');
      if (!head || !root.contains(head)) return;
      e.preventDefault();
      head.click();
    });
  }

  global.mdFitFrame = fitFrame; // compat: algún onload inline podría seguir llamándolo
  global.MDThread = { init: init, autoOpen: autoOpen, loadBody: loadBody };
})(window);
