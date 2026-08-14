<?php
/**
 * Etiquetas que hacen a Nexus instalable como aplicación de escritorio (PWA).
 *
 * Va en el <head> de TODOS los layouts, incluido el de acceso: al abrir la app
 * instalada sin sesión, la primera pantalla es el login, y si ahí no está el
 * manifiesto el navegador considera que se salió del alcance de la aplicación.
 *
 * El service worker (public/sw.js) solo se registra sobre HTTPS o en localhost;
 * es requisito del navegador y por eso el registro se envuelve en esa condición
 * en vez de fallar en silencio.
 */
?>
<link rel="manifest" href="<?= base_url('manifest.json') ?>">
<meta name="theme-color" content="#1773C8">
<meta name="application-name" content="Nexus">
<meta name="apple-mobile-web-app-title" content="Nexus">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="<?= base_url('img/icons/icon-192.png') ?>">
<script>
  (function () {
    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;

    window.addEventListener('load', function () {
      navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(function () {
        // Que no se registre no debe estropear la página: solo se pierde la
        // instalación y la pantalla sin conexión.
      });
    });
  })();
</script>
