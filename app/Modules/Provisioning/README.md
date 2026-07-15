# Módulo Provisioning (Aprovisionamiento)

Orquestador del ciclo de vida de identidades de Nexus hacia los sistemas externos:

| Sistema    | Conector              | Alta | Baja | Cambio de contraseña | Update |
|------------|-----------------------|:----:|:----:|:--------------------:|:------:|
| GLPI       | `GlpiConnector`       | ✓    | ✓    | ✓                    | ✓      |
| Mailcow    | `MailcowConnector`    | ✓    | ✓    | ✓                    | ✓      |
| Intranet   | `IntranetConnector`   | ✓    | ✓    | ✓                    | ✓      |

> **Microsoft 365 está fuera del alcance** de este módulo (se maneja manualmente y nunca se elimina, sólo se bloquea, por historial).

Los empleados **no son usuarios de Nexus**: no inician sesión aquí ni tienen rol; son registros de datos que Nexus administra y propaga.

---

## Arquitectura rápida

```
┌──────────────────────────────────────────────────────────┐
│   UI Empleados (panel embebido) / API / CLI              │
└────────────────────────┬─────────────────────────────────┘
                         ▼
              ┌──────────────────────┐
              │ AccessOrchestrator   │  ← decide a qué sistemas propaga
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
              │ ConnectorFactory     │  ← construye el conector y descifra creds
              └──────────┬───────────┘
                         ▼
   ┌────────────────┬─────────────────┬─────────────────┐
   │ GlpiConnector  │ MailcowConnector│ IntranetConnector│   (SystemConnector)
   └────────────────┴─────────────────┴─────────────────┘
```

- Cada operación deja registro en `provisioning_log` (éxito / error / pendiente).
- Los pasos fallidos se encolan en `provisioning_retry_queue` y se reprocesan via cron.
- **No hay transacción distribuida**: una falla en un sistema NO revierte los demás; queda visible y reintentable.

---

## Instalación y configuración

### 1. Migraciones y seeder

```bash
php spark migrate -n "App\Modules\Provisioning"
php spark db:seed App\\Modules\\Provisioning\\Database\\Seeders\\ProvisioningModuleSeeder
```

### 2. Clave de cifrado (obligatoria)

Las credenciales de Nexus hacia los sistemas externos se almacenan cifradas. La clave maestra vive en `.env` (nunca en el repositorio):

```ini
encryption.key = hex2bin:tu_llave_de_64_bytes
```

Para generar una nueva:

```bash
php spark key:generate
```

> Si la clave no está configurada, el formulario de credenciales devuelve un error y rehúsa guardar valores. **No** persiste credenciales en claro.

### 3. Configurar los sistemas destino

UI: `Aprovisionamiento → Sistemas destino → Editar`.

| Sistema   | Campos obligatorios                                          |
|-----------|--------------------------------------------------------------|
| GLPI      | `base_url` + `app_token` + (`user_token` o `api_username` + `api_password`) |
| Mailcow   | Reutiliza la configuración del módulo Buzones (URL + API key). |
| Intranet  | `base_url` + `api_key`                                       |

Después de capturar credenciales, oprime **Probar conexión** para validar.

### 4. Cron para reintentos

```cron
*/5 * * * * cd /ruta/al/proyecto && php spark provisioning:process-retries --limit 50
```

---

## Contrato de la API que la Intranet debe exponer

Nexus es el cliente; la Intranet implementa los endpoints siguientes. Las llamadas viajan por **HTTPS**, en **JSON**, con header:

```
Authorization: Bearer <API_KEY>
```

La API key vive cifrada en `provisioning_system_credentials`, fuera del repositorio.

> **API v1 (usuarios de login).** La Intranet separó **empleado** (nodo del
> organigrama, no inicia sesión, lo administra la Intranet) de **usuario** (cuenta
> de login, la administra Nexus). Nexus **ya no** envía datos de organigrama
> (`puesto`, `area`, `departamento`, `jefe_directo`); envía sólo la información
> mínima de la cuenta. El identificador de recurso es **`nexus_id`** (derivado del
> id de Nexus, p. ej. `NX-42`), **no** `numero_empleado`. Aun así Nexus envía el
> **`numero_empleado`** como atributo del usuario (para reutilizarlo en la
> Intranet); no es la llave de recurso, sólo un dato que se persiste. El
> `id_usuario` (`INT-<n>`) que devuelve la Intranet es informativo: todas las
> operaciones posteriores usan `nexus_id`.

### `POST /api/v1/usuarios` — Crear usuario

Request:
```json
{
  "nexus_id": "NX-42",
  "numero_empleado": "1024",
  "nombre": "Ana",
  "apellidos": "Lopez Garcia",
  "correo": "ana.lopez@trantortechnologies.mx",
  "password": "<texto_plano_temporal>",
  "estado": "activo"
}
```

Respuesta esperada:
```json
{ "exito": true, "id_usuario": "INT-5567", "mensaje": "Usuario creado" }
```

### `POST /api/v1/usuarios/{nexus_id}/desactivar`

```json
{ "exito": true, "mensaje": "Usuario desactivado" }
```

> La baja **no debe borrar** el registro; sólo marcarlo inactivo.

### `POST /api/v1/usuarios/{nexus_id}/password`

Request:
```json
{ "password": "<texto_plano_nuevo>" }
```

Respuesta:
```json
{ "exito": true, "mensaje": "Contrasena actualizada" }
```

### `PUT /api/v1/usuarios/{nexus_id}` — Actualizar (parcial)

Request: sólo los campos que cambian (`numero_empleado`, `nombre`, `apellidos`, `correo`, `estado`, `password`). Respuesta: mismo formato.

### `GET /api/v1/ping` (opcional, recomendado)

Sirve para que el botón **Probar conexión** del panel de Nexus tenga algo concreto que llamar:

```json
{ "exito": true, "mensaje": "pong" }
```

### Reglas comunes de respuesta

- `200` éxito · `4xx` validación/autenticación · `5xx` error interno.
- Siempre regresa `exito` (bool) y `mensaje` (string), incluso en error.
- En error, agrega `error_codigo`:
  ```json
  { "exito": false, "error_codigo": "USUARIO_DUPLICADO", "mensaje": "Ya existe un usuario con ese nexus_id" }
  ```

---

## Uso del orquestador

### Desde la ficha del empleado

`/empleados/{id}` muestra el panel **Aprovisionamiento**: estado por sistema, botones de **Crear**, **Desactivar**, **Reintentar alta** y, en el pie, **Alta en todos**, **Baja en todos** y **Cambiar contraseña**.

### Desde la API

```http
POST /api/v1/provisioning/employees/{id}/provision
POST /api/v1/provisioning/employees/{id}/deprovision
POST /api/v1/provisioning/employees/{id}/password
POST /api/v1/provisioning/employees/{id}/systems/{systemId}/provision
POST /api/v1/provisioning/employees/{id}/systems/{systemId}/deprovision

GET  /api/v1/provisioning/systems
PUT  /api/v1/provisioning/systems/{id}
POST /api/v1/provisioning/systems/{id}/test
POST /api/v1/provisioning/systems/{id}/toggle

GET  /api/v1/provisioning/employees/{id}/status
GET  /api/v1/provisioning/employees/{id}/log

GET  /api/v1/provisioning/log
GET  /api/v1/provisioning/retries
POST /api/v1/provisioning/retries/run
POST /api/v1/provisioning/retries/{id}
```

Todas las rutas requieren `api_auth` y `module_access:provisioning`.

### Desde CLI

```bash
php spark provisioning:process-retries --limit 50
```

---

## Seguridad (resumen del módulo)

- Todas las llamadas salientes a GLPI, Mailcow e Intranet por **HTTPS** (verificación SSL activa).
- **Credenciales y tokens** hacia los sistemas: cifrados en reposo. Clave maestra en `.env`, **fuera del repositorio**.
- **Contraseña del empleado en texto plano**: sólo en memoria durante la propagación, **nunca persistida**. Nexus no guarda hash ni texto del empleado porque el empleado no inicia sesión aquí.
- **CSRF activo** en todos los formularios del módulo (filtros globales de CI4).
- Cada operación queda en `provisioning_log` con usuario ejecutor, IP y timestamp.
- La cola de reintentos **no** lleva contraseña adjunta: si un cambio de contraseña falla en un sistema, el operador debe reintentarlo manualmente capturando la contraseña otra vez.

---

## Convención: empleados ≠ usuarios

| Concepto    | Vive en        | Inicia sesión en Nexus | Tiene rol Nexus |
|-------------|----------------|:----------------------:|:---------------:|
| Usuario     | `users`        | ✓                      | ✓               |
| Empleado    | `employees`    | ✗                      | ✗               |

Este módulo **administra empleados** (los propaga a sistemas externos), no usuarios internos. Las cuentas de los empleados existen en **GLPI, Mailcow e Intranet**, no en Nexus.

---

## Confirmación

**El módulo Buzones (Mailboxes) no fue modificado.** El conector de Mailcow reutiliza `App\Modules\Mailboxes\Libraries\MailcowApi` y `App\Modules\Mailboxes\Models\MailboxesSettingsModel` sin tocar su código ni alterar su comportamiento.
