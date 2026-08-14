<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('asset_url')) {
    /**
     * URL de un archivo estático de `public/` con la fecha de modificación del
     * archivo como número de versión: `css/app.css?v=1754380675`.
     *
     * Es lo que hace que un despliegue se vea de inmediato. Mientras la URL no
     * cambie, tanto la caché del navegador como la del service worker
     * (`public/sw.js`) tienen derecho a seguir sirviendo la copia vieja: el
     * servidor no manda `Cache-Control`, así que el navegador aplica su
     * heurística y puede dar por buena la copia guardada durante horas. Con la
     * marca de tiempo, un archivo nuevo es una URL nueva y no hay nada viejo que
     * servir.
     *
     * Si el archivo no existe se devuelve la URL sin marca, para no romper nada.
     */
    function asset_url(string $path): string
    {
        $file  = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/');
        $stamp = is_file($file) ? filemtime($file) : false;

        return base_url($path) . ($stamp !== false ? '?v=' . $stamp : '');
    }
}
