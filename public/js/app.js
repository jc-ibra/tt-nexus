/* tt-apps — global JS */

/**
 * Tema claro/oscuro/sistema. La preferencia se guarda en localStorage
 * ('nx_theme') y se resuelve pre-pintado en partials/theme_boot.php; este
 * bloque solo atiende cambios en vivo: el clic en el selector, y el cambio
 * de apariencia del SO mientras la preferencia sea "sistema".
 *
 * Dispara 'nx:themechange' en document para que otros scripts (gráficas)
 * puedan reaccionar sin sondear el DOM.
 */
(function () {
  var KEY  = 'nx_theme';
  var root = document.documentElement;

  function resolve(pref) {
    return pref === 'dark' ||
      (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function setTheme(pref, opts) {
    opts = opts || {};
    var dark = resolve(pref);
    root.classList.add('nx-theme-switching');
    root.setAttribute('data-theme', pref);
    root.setAttribute('data-theme-resolved', dark ? 'dark' : 'light');
    if (!opts.silent) {
      try { localStorage.setItem(KEY, pref); } catch (e) {}
    }
    requestAnimationFrame(function () {
      root.classList.remove('nx-theme-switching');
    });
    document.dispatchEvent(new CustomEvent('nx:themechange', {
      detail: { pref: pref, resolved: dark ? 'dark' : 'light' },
    }));
    document.querySelectorAll('.theme-switcher-btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.dataset.theme === pref);
      btn.setAttribute('aria-checked', btn.dataset.theme === pref ? 'true' : 'false');
    });
    syncThemeColorMeta(pref, dark);
  }

  // Las dos <meta name="theme-color"> de pwa.php siguen al SO por su atributo
  // media. Ante una elección explícita (claro u oscuro) esa condición debe
  // dejar de importar: se fuerza una de las dos a "all" y se apaga la otra.
  function syncThemeColorMeta(pref, dark) {
    var light = document.getElementById('theme-color-light');
    var darkMeta = document.getElementById('theme-color-dark');
    if (!light || !darkMeta) return;
    if (pref === 'system') {
      light.media = '(prefers-color-scheme: light)';
      darkMeta.media = '(prefers-color-scheme: dark)';
    } else {
      light.media = dark ? 'not all' : 'all';
      darkMeta.media = dark ? 'all' : 'not all';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var pref = root.getAttribute('data-theme') || 'system';
    document.querySelectorAll('.theme-switcher-btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.dataset.theme === pref);
      btn.setAttribute('aria-checked', btn.dataset.theme === pref ? 'true' : 'false');
      btn.addEventListener('click', function () { setTheme(btn.dataset.theme); });
    });
    // Ya aplicado en <head> por theme_boot.php; solo faltan las <meta theme-color>,
    // que no pueden resolverse pre-pintado porque dependen de nodos del <head>
    // que el propio boot script no toca (para no acoplar los dos scripts).
    syncThemeColorMeta(pref, root.getAttribute('data-theme-resolved') === 'dark');
  });

  // El SO cambia de apariencia mientras la preferencia siga en "sistema".
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
    if ((root.getAttribute('data-theme') || 'system') === 'system') setTheme('system', { silent: true });
  });

  window.NxTheme = { set: setTheme, resolved: function () { return root.getAttribute('data-theme-resolved'); } };
})();

document.addEventListener('DOMContentLoaded', () => {
    // Disable submit buttons on form submit to prevent double-submission
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            // Don't lock buttons if the submit was cancelled (e.g. a confirm()
            // dialog was dismissed, or a listener called preventDefault). This
            // handler runs last, so defaultPrevented reflects those decisions.
            if (e.defaultPrevented) return;
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
                btn.disabled = true;
                if (btn.dataset.loadingText) btn.textContent = btn.dataset.loadingText;
            });
        });
    });

    // Expandable nav groups
    document.querySelectorAll('[data-nav-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const group = btn.closest('[data-nav-group]');
            const open  = group.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    // Auto-dismiss flash banners after 5 seconds
    document.querySelectorAll('.banner[role="status"]').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.3s ease';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 300);
        }, 5000);
    });
});
