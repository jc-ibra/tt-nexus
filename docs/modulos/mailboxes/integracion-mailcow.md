# Módulo Mailboxes (Buzones): integración Mailcow

> **Nota de naming.** El módulo se llama `Mailboxes` en código (folder, namespace, URL, key y tabla DB). El nombre **Buzones** es solo el display en UI (sidebar y headings). Ver `docs/referencia/CONVENTIONS.md §2.1`.

## Configuración de la API Key en Mailcow

1. Inicia sesión en tu panel Mailcow como administrador.
2. Ve a **Configuración → API** (o directamente a `https://mail.tudominio.com/admin#tabs-config`).
3. En la sección **API**, genera o copia la clave de **Read-Write API**.
4. Asegúrate de que la IP del servidor que corre tt-nexus esté en la lista de IPs permitidas (o usa `0.0.0.0/0` en desarrollo).
5. Copia la API Key.

En tt-nexus, ve a **Buzones → Configuración** y pega:
- **URL base**: `https://mail.tudominio.com` (sin barra final)
- **API Key**: la clave copiada

Usa el botón **Probar conexión** para verificar que todo funciona.

## Permisos requeridos

La API Key debe ser de tipo **Read-Write** para que todas las operaciones funcionen:

| Operación | Método | Requiere escritura |
|-----------|--------|-------------------|
| Listar buzones | GET | No |
| Ver detalle | GET | No |
| Listar dominios | GET | No |
| Crear buzón | POST | Sí |
| Editar buzón | POST | Sí |
| Eliminar buzón | POST | Sí |
| Activar/desactivar | POST | Sí |

Si solo necesitas visualización, puedes usar una API Key de solo lectura, pero las acciones de escritura fallarán con HTTP 401.

## Endpoints Mailcow consumidos

Base URL: `{MAILCOW_URL}/api/v1/`

| Endpoint | Método | Uso |
|----------|--------|-----|
| `get/mailbox/all` | GET | Listar todos los buzones |
| `get/mailbox/{email}` | GET | Detalle de un buzón |
| `get/domain/all` | GET | Listar dominios disponibles |
| `get/status/version` | GET | Probar conexión |
| `add/mailbox` | POST | Crear buzón |
| `edit/mailbox` | POST | Editar buzón (nombre, cuota, password, estado) |
| `delete/mailbox` | POST | Eliminar uno o varios buzones |

## Permisos de acceso en tt-nexus

El módulo `mailboxes` requiere que el rol del usuario esté vinculado al módulo en la tabla `role_module`. Por defecto, solo **SuperAdmin** tiene acceso tras ejecutar el seeder.

Para dar acceso a otro rol:
```sql
INSERT INTO role_module (role_id, module_id)
SELECT r.id, m.id
FROM roles r, modules m
WHERE r.name = 'NombreRol' AND m.key = 'mailboxes';
```

## Ejecutar migración y seeder

```bash
# Migración (crea tabla mailboxes_settings)
php spark migrate -n "App\Modules\Mailboxes"

# Seeder (registra el módulo y otorga acceso a SuperAdmin)
php spark db:seed App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder
```

> Si ya habías corrido la migración anterior con el nombre `buzones_settings`, primero debes:
> ```sql
> DROP TABLE IF EXISTS buzones_settings;
> DELETE FROM modules WHERE `key` = 'buzones';
> ```
> Luego vuelve a correr `php spark migrate` y el seeder `MailboxesModuleSeeder`.

## Troubleshooting

### Error: "Mailcow no está configurado"
- Ve a **Buzones → Configuración** y completa la URL y la API Key.

### Error: "API Key inválida o sin permisos (HTTP 401)"
- Verifica que la API Key sea de tipo Read-Write.
- Verifica que la IP del servidor esté en la lista de IPs permitidas en Mailcow.

### Error: "Error de conexión con Mailcow: ..."
- Verifica que la URL sea accesible desde el servidor (no solo desde tu navegador).
- Si usas HTTPS con certificado autofirmado, desactiva "Verificar certificado SSL" en la configuración.
- Revisa los logs de la aplicación: `tail -f writable/logs/log-*.log`

### La tabla mailboxes_settings no existe
- Ejecuta la migración: `php spark migrate -n "App\Modules\Mailboxes"`

### El módulo no aparece en el menú
- Ejecuta el seeder: `php spark db:seed App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder`
- Verifica que el usuario tenga un rol con acceso al módulo (tabla `role_module`).

### Las operaciones de escritura fallan pero el listado funciona
- Confirma que usas una API Key Read-Write (no solo lectura).
- Revisa los logs de Mailcow para detalles adicionales.
