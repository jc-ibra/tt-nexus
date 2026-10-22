# Shares

Cada usuario sube archivos HTML y emite **enlaces públicos** (sin login) para compartirlos. Cada enlace se controla por separado: puede caducar, limitarse, protegerse o desactivarse cuando se quiera.

- Rutas web `/shares/*`, API `/api/v1/shares/*`, URL pública `/s/{token}`. Key `shares` (UI "Enlaces públicos"), tablas `shares_files` y `shares_links`.
- **Propiedad:** cada usuario ve y gestiona solo lo suyo; el SuperAdmin ve todo. Un id ajeno responde 404, no 403. Tener acceso al módulo basta, no hay permisos granulares.
- **Modelo:** 1 archivo → N enlaces. Subir un archivo crea su primer enlace automáticamente.

## Control de cada enlace
| Opción | Efecto |
|---|---|
| Desactivar / reactivar | Deja de abrir al instante; se puede revertir. |
| Fecha de expiración | Pasada esa fecha deja de abrir (zona horaria del servidor). |
| Límite de vistas | Deja de abrir al llegar a N. El conteo es un `UPDATE` atómico, así que no se sirve la vista N+1 ni con concurrencia. Se puede reiniciar el contador. |
| Contraseña | Formulario previo; se guarda con `password_hash`. Máx. 10 intentos / 15 min por IP + enlace. |
| Regenerar dirección | Token nuevo; la URL anterior da 404 de inmediato. |
| Reemplazar el HTML | El archivo cambia, los enlaces se conservan. |

El estado (`active`, `revoked`, `expired`, `exhausted`) se **calcula**, no se guarda; `ShareService::status()`.

## Seguridad
- Token de 256 bits (`random_bytes(32)`, base64url, 43 caracteres), único; no se puede enumerar.
- Token inexistente → 404. Revocado, caducado o agotado → 410 con el mismo mensaje, para no revelar la causa. Throttle de 120 solicitudes/min por IP.
- El HTML es de terceros y trae scripts. Se sirve con `Content-Security-Policy: sandbox allow-scripts ...` **sin** `allow-same-origin`: corre en un origen opaco y no alcanza la sesión de Nexus. No agregar `allow-same-origin` en el mismo dominio.
- Respuestas públicas con `Cache-Control: no-store`, para que cada vista cuente y una revocación aplique de inmediato.
- Archivos en `writable/uploads/shares/` con nombre aleatorio; nunca se sirven directamente.

## Operación
- Límite de carga 100 MB: mismo ajuste que Presentations (`docker/php/custom.ini`; en producción, php.ini / `.user.ini`).
- Si el servidor está detrás de un proxy, `getIPAddress()` debe ver la IP real del visitante o el throttle agrupará a todos en una sola.
