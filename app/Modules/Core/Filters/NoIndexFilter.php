<?php

namespace App\Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Añade la cabecera X-Robots-Tag a todas las respuestas.
 *
 * Nexus es una plataforma interna y no debe aparecer en buscadores.
 * La cabecera cubre cualquier respuesta (HTML, JSON, descargas), incluso
 * las que no pasan por una vista con <meta name="robots">.
 */
class NoIndexFilter implements FilterInterface
{
    private const DIRECTIVES = 'noindex, nofollow, noarchive, nosnippet, noimageindex';

    public function before(RequestInterface $request, $arguments = null)
    {
        // Nada que hacer antes de la petición.
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Robots-Tag', self::DIRECTIVES);

        return $response;
    }
}
