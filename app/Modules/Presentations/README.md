# Presentations

Publica y visualiza el HTML mensual de Operaciones. Una persona (área externa) lo reemplaza cada mes; todos los roles con acceso al módulo lo ven. No se mezcla con otros módulos.

- Rutas web `/presentations/*`, admin `/admin/presentations/permissions`, API `/api/v1/presentations/*`. Key `presentations`, tabla `presentations_versions`.
- **Ver:** cualquier rol con acceso al módulo `presentations`.
- **Reemplazar:** permiso `presentations.upload` (`core_permissions`), asignable por rol en `/admin/presentations/permissions` (SuperAdmin). `AccessService::can()` lo evalúa contra el rol activo.
- **Historial:** cada carga crea una versión nueva; la vigente es la de mayor `id`. Un archivo idéntico (sha256) a la vigente se rechaza.
- Archivos en `writable/uploads/presentations/` (nombre aleatorio), nunca públicos.

## Cómo dar acceso a la persona externa
1. Crear un rol (p. ej. "Operaciones externo") y darle acceso al módulo Presentaciones.
2. En `/admin/presentations/permissions`, marcar ese rol como "Puede reemplazar".
3. Crear su usuario con ese rol (invitación por correo).

## Seguridad
El HTML es de terceros y trae scripts. Se muestra en un `<iframe sandbox>` **sin** `allow-same-origin`, y `/raw` responde con `Content-Security-Policy: sandbox ...`, así que sus scripts corren en un origen opaco y no alcanzan la sesión de Nexus. No agregar `allow-same-origin` en el mismo dominio.

## Operación
- Límite de carga 100 MB: `docker/php/custom.ini` (`upload_max_filesize=100M`, `post_max_size=110M`, `max_execution_time=300`). Requiere `docker compose up -d --build`.
- **Producción:** aplicar los mismos valores en el php.ini / `.user.ini` del servidor (no se configura desde el repo).
