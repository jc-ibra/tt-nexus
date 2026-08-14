/**
 * Service worker de Nexus.
 *
 * Existe por dos razones: habilitar la instalación en el escritorio (Chrome y
 * Edge no ofrecen "Instalar" sin uno) y mostrar una pantalla propia cuando no
 * hay red, en vez de la del navegador.
 *
 * Reglas que NO se rompen:
 *
 *   1. El HTML nunca se cachea. Nexus es una app con sesión y en una computadora
 *      compartida una página guardada podría mostrarle a un usuario datos de
 *      otro. Toda navegación va a la red; si la red falla, se muestra la
 *      pantalla sin conexión y nada más.
 *   2. Solo se tocan GET del mismo origen bajo css/, js/ e img/. Los POST, la
 *      API, el login y las descargas (exportaciones a CSV/Excel) pasan de largo
 *      sin que este archivo intervenga.
 *   3. Los estáticos se sirven de caché y se revalidan contra la red en segundo
 *      plano. Además las páginas los piden con la fecha del archivo en la URL
 *      (ver asset_url() en app/Common.php), así que tras un despliegue la URL es
 *      otra, aquí no hay nada guardado bajo esa clave y el archivo nuevo se pide
 *      a la red de una vez: el usuario nunca ve una mezcla de HTML nuevo con
 *      CSS viejo.
 *
 * Interruptor de apagado: pon DISABLED en true y despliega. El service worker
 * borrará sus cachés y se desinstalará solo la próxima vez que alguien abra la
 * app, sin necesidad de que los usuarios hagan nada.
 */
const DISABLED = false;

const CACHE       = 'nexus-static-v1';
const OFFLINE_URL = 'offline.html';

/** Base del scope ('/' en un despliegue normal, '/subcarpeta/' si cuelga de una). */
const BASE = new URL('./', self.location).pathname;

/**
 * Lo mínimo para que la pantalla sin conexión se vea bien estando sin red. No
 * incluye app.css a propósito: offline.html trae sus estilos dentro, y las
 * páginas piden el CSS con versión en la URL, así que guardar la versión sin
 * marca solo ocuparía espacio sin que nadie la use.
 */
const PRECACHE = ['img/tt-icon.png'];

self.addEventListener('install', (event) => {
    if (DISABLED) {
        return;
    }

    event.waitUntil((async () => {
        const cache = await caches.open(CACHE);

        // La pantalla offline es la única obligatoria: sin ella este worker no
        // aporta nada, así que se deja fallar la instalación.
        await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));

        // El resto es mejora, no requisito: si algo no está, se sigue adelante.
        await Promise.all(PRECACHE.map((url) => cache.add(url).catch(() => null)));

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        if (DISABLED) {
            const keys = await caches.keys();
            await Promise.all(keys.map((k) => caches.delete(k)));
            await self.registration.unregister();
            return;
        }

        // Fuera cachés de versiones anteriores.
        const keys = await caches.keys();
        await Promise.all(
            keys.filter((k) => k.startsWith('nexus-') && k !== CACHE).map((k) => caches.delete(k))
        );

        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    if (DISABLED) {
        return;
    }

    const request = event.request;

    // Solo lecturas: cualquier envío de formulario o llamada a la API sigue su
    // camino normal, con sus cookies y su token CSRF intactos.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkOnly(request));
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(staleWhileRevalidate(event, request));
    }
});

/** Páginas: siempre de la red; sin red, la pantalla sin conexión. */
async function networkOnly(request) {
    try {
        return await fetch(request);
    } catch (e) {
        const cached = await caches.match(OFFLINE_URL);
        if (cached) {
            return cached;
        }
        return new Response(
            '<h1>Sin conexión</h1><p>No se pudo contactar al servidor de Nexus.</p>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=UTF-8' } }
        );
    }
}

/** ¿Es un estático nuestro (css/, js/, img/)? */
function isStaticAsset(url) {
    if (!url.pathname.startsWith(BASE)) {
        return false;
    }
    const path = url.pathname.slice(BASE.length);

    return path.startsWith('css/') || path.startsWith('js/') || path.startsWith('img/');
}

/**
 * Responde con lo que haya en caché y, en paralelo, revalida contra el servidor
 * para la próxima vez.
 *
 * La revalidación va con `cache: 'no-cache'` para que la pida al servidor de
 * verdad (condicional, con el ETag) en vez de que se la resuelva la caché del
 * navegador: los estáticos se sirven sin `Cache-Control`, así que el navegador
 * aplica su heurística y podría devolver la copia vieja, dejándola guardada aquí
 * más tiempo del debido.
 */
async function staleWhileRevalidate(event, request) {
    const cache  = await caches.open(CACHE);
    const cached = await cache.match(request);

    const network = fetch(request, { cache: 'no-cache' })
        .then(async (response) => {
            // 'basic' = mismo origen y respuesta completa: nada opaco ni parcial.
            if (response && response.ok && response.type === 'basic') {
                await cache.put(request, response.clone());
                await dropOtherVersions(cache, request);
            }
            return response;
        })
        .catch(() => null);

    if (cached) {
        event.waitUntil(network);
        return cached;
    }

    return (await network) || fetch(request);
}

/**
 * Borra las versiones anteriores del mismo archivo. Como la URL lleva la fecha
 * del archivo (`app.css?v=…`), cada despliegue crea una entrada nueva; sin esto
 * la caché acumularía una copia por cada versión que haya pasado.
 */
async function dropOtherVersions(cache, request) {
    const url  = new URL(request.url);
    const keys = await cache.keys();

    await Promise.all(keys.map((key) => {
        const keyUrl = new URL(key.url);
        if (keyUrl.pathname === url.pathname && keyUrl.search !== url.search) {
            return cache.delete(key);
        }
        return null;
    }));
}
