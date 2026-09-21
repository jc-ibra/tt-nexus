<?php
/**
 * Resuelve el tema (claro/oscuro/sistema) antes del primer pintado.
 *
 * Debe incluirse en <head>, ANTES del <link> a app.css, en cualquier layout
 * que cargue app.css (main.php, auth.php, errors/403.php). Escribe dos
 * atributos en <html>:
 *   data-theme          la preferencia guardada: light | dark | system
 *   data-theme-resolved el tema efectivo que el CSS realmente usa: light | dark
 *
 * Sin este script el body pintaría en claro y luego saltaría a oscuro — el
 * mismo problema de parpadeo que ya resuelve nx_sidebar_collapsed para el
 * ancho del sidebar (ver layouts/main.php).
 */
?>
<script>
(function () {
  try {
    var KEY = 'nx_theme';
    var pref = localStorage.getItem(KEY);
    if (pref !== 'light' && pref !== 'dark') pref = 'system';
    var dark = pref === 'dark' ||
      (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    var r = document.documentElement;
    r.setAttribute('data-theme', pref);
    r.setAttribute('data-theme-resolved', dark ? 'dark' : 'light');
  } catch (e) {}
})();
</script>
