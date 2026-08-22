# TechBot - Especificacion del modulo

> Modulo nuevo para tt-nexus.
> Canal de Telegram para que los tecnicos de campo documenten y solucionen tickets de GLPI.
> Este documento es la especificacion funcional y tecnica completa. Consultar `CLAUDE.md` y `docs/referencia/CONVENTIONS.md` para convenciones de codigo, estructura de modulo y patrones de CI4.

---

## 1. Identidad del modulo

| Atributo | Valor |
|---|---|
| Folder | `app/Modules/TechBot` |
| Namespace | `App\Modules\TechBot` |
| DB key (tabla `modules`) | `techbot` |
| DB table prefix | `techbot_` |
| route_base | `techbot` |
| URL prefix (web admin) | `/techbot/*` |
| UI display name (spanish) | "TechBot" |
| Sidebar key | `techbot` |

---

## 2. Proposito y alcance

### Que es

Un bot de Telegram que permite a los tecnicos de campo documentar y solucionar los tickets que tienen asignados en GLPI. El backend vive integramente en tt-nexus como un modulo mas.

### Que NO es

- No crea tickets. La creacion es exclusiva de Mesa de Ayuda central.
- No reasigna tickets, no cambia prioridades, no cancela.
- No permite operar sobre tickets de otros tecnicos; cada tecnico solo ve y actua sobre los suyos.
- No es una interfaz web para tecnicos; la interfaz es Telegram exclusivamente. La parte web es solo el panel de administracion para supervisores.

### Actores

| Actor | Interaccion |
|---|---|
| Tecnico de campo | Usa Telegram para documentar y solucionar sus tickets asignados |
| Supervisor / Mesa de Ayuda | Panel web en Nexus: configuracion del bot, vinculacion de tecnicos, logs de actividad |
| SuperAdmin | Acceso completo al modulo + configuracion de integraciones |

---

## 3. Dependencias de otros modulos (solo reuso via servicios)

| Modulo | Que se reutiliza | Para que |
|---|---|---|
| Provisioning | `GlpiConnector` (API REST de GLPI), config de `provisioning_systems` y `provisioning_system_credentials` | Consultar tickets asignados, crear followups, cambiar estados |
| Provisioning | `provisioning_external_accounts` (lectura) | Obtener el `external_id` del tecnico en GLPI a partir de su `employee_id` |
| Employees | Tabla `employees` (lectura) | Validar numero de empleado durante el registro del tecnico en Telegram |
| ServiceDesk | Config de Claude API en `servicedesk_settings` (lectura, solo `ai_api_key` y `ai_model`) | Estructurar texto libre de diagnosticos y resoluciones con IA (opcional, habilitado via toggle) |

> No se modifica ni extiende ningun servicio de estos modulos. TechBot los consume como cliente.

---

## 4. Conexion con Telegram

### 4.1. Creacion del bot (manual, una sola vez)

1. El administrador crea el bot con `@BotFather` en Telegram.
2. BotFather devuelve un token (ejemplo: `7123456789:AAHdqTcvXYZ...`).
3. El administrador registra el token en el panel de configuracion de TechBot en Nexus.
4. El administrador configura en BotFather: nombre visible, descripcion, foto de perfil, y los comandos (`/start`, `/tickets`, `/ayuda`).
5. El administrador distribuye el link `https://t.me/<bot_username>` a los tecnicos.

### 4.2. Webhook

- Telegram envia un POST JSON a `https://<nexus_domain>/api/v1/techbot/webhook` cada vez que un tecnico escribe al bot.
- Nexus valida el request (token secreto en header o en URL, configurable).
- El webhook NO requiere AuthFilter ni ModuleAccessFilter; es un endpoint publico autenticado por el secreto de Telegram.
- La ruta del webhook se registra en Telegram con una llamada unica a `https://api.telegram.org/bot<token>/setWebhook`.

### 4.3. Envio de mensajes

Nexus envia mensajes de vuelta al tecnico via `https://api.telegram.org/bot<token>/sendMessage` (y `sendPhoto`, `sendDocument`).
Soportar envio de:
- Texto plano y con formato Markdown v2
- Botones inline (InlineKeyboardMarkup) para menus y seleccion de opciones
- Fotos (cuando se envia resumen de ticket)

### 4.4. Recepcion de mensajes

El webhook recibe updates de Telegram que pueden contener:
- Mensajes de texto (comandos como `/start`, `/tickets`, o texto libre)
- Fotos (evidencias del tecnico)
- Callbacks de botones inline (cuando el tecnico selecciona una opcion)
- Ubicacion (opcional, para registro de llegada a sitio)

---

## 5. Modelo de datos

### 5.1. `techbot_settings` - Configuracion del bot

Tabla tipo llave-valor (mismo patron que `mailboxes_settings`, `servicedesk_settings`).

| key | Tipo valor | Cifrado | Descripcion |
|---|---|---|---|
| `telegram_bot_token` | string | Si (`encryption.key`) | Token del bot de BotFather |
| `telegram_bot_username` | string | No | Username del bot (sin @) para generar links |
| `telegram_webhook_secret` | string | Si (`encryption.key`) | Secreto para validar que los requests vienen de Telegram |
| `bot_enabled` | bool | No | Toggle maestro para activar/desactivar el bot |
| `ai_formatting_enabled` | bool | No | Toggle para activar el formateo con Claude en diagnosticos y resoluciones |
| `ai_max_tokens` | int | No | Tope de tokens por peticion de formateo (default: 1024) |
| `welcome_message` | text | No | Mensaje de bienvenida al tecnico tras vincular su cuenta |
| `require_photo_on_resolution` | bool | No | Forzar al menos una foto al documentar resolucion |
| `require_visto_bueno_on_resolution` | bool | No | Forzar campo de visto bueno al resolver |

### 5.2. `techbot_telegram_links` - Vinculacion Telegram a empleado

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | INT PK AUTO | |
| `telegram_chat_id` | BIGINT UNIQUE NOT NULL | ID del chat de Telegram (identifica al usuario) |
| `telegram_username` | VARCHAR(100) NULL | Username de Telegram (informativo, puede no existir) |
| `telegram_first_name` | VARCHAR(100) NULL | Nombre que tiene el usuario en Telegram |
| `employee_id` | INT NOT NULL FK(employees.id) | Referencia al empleado en Nexus |
| `glpi_user_id` | INT NOT NULL | ID del usuario en GLPI (copiado de `provisioning_external_accounts.external_id` al vincular, para evitar joins en cada consulta) |
| `status` | ENUM('active','inactive') DEFAULT 'active' | Para desvinculaciones sin borrar el registro |
| `verified_at` | DATETIME NULL | Momento en que se completo la vinculacion |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Indices:
- UNIQUE en `telegram_chat_id`
- UNIQUE en `employee_id` (un empleado = una cuenta de Telegram vinculada)
- INDEX en `glpi_user_id`

### 5.3. `techbot_conversation_states` - Estado de conversacion

Cada tecnico tiene a lo sumo un estado activo. Controla en que paso del flujo conversacional esta (seleccionando ticket, documentando diagnostico, esperando foto, etc.).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | INT PK AUTO | |
| `telegram_chat_id` | BIGINT NOT NULL FK(techbot_telegram_links.telegram_chat_id) | |
| `state` | VARCHAR(50) NOT NULL | Identificador del estado actual (ver seccion 7) |
| `context` | JSON NULL | Datos acumulados del flujo en curso (ticket_id, textos parciales, fotos pendientes, etc.) |
| `current_ticket_id` | INT NULL | ID del ticket en GLPI sobre el que esta operando |
| `expires_at` | DATETIME NULL | Timeout de inactividad (30 minutos sugerido); si expira, el estado se resetea |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Indice UNIQUE en `telegram_chat_id` (solo un estado activo a la vez).

### 5.4. `techbot_activity_log` - Registro de actividad

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | INT PK AUTO | |
| `telegram_chat_id` | BIGINT NOT NULL | |
| `employee_id` | INT NOT NULL | |
| `glpi_ticket_id` | INT NOT NULL | ID del ticket en GLPI |
| `action` | VARCHAR(50) NOT NULL | Accion ejecutada (ver tabla en seccion 6) |
| `template_key` | VARCHAR(50) NULL | Clave de la plantilla usada |
| `glpi_followup_id` | INT NULL | ID del followup/task creado en GLPI (si aplica) |
| `glpi_status_before` | INT NULL | Status del ticket antes de la accion |
| `glpi_status_after` | INT NULL | Status del ticket despues de la accion |
| `payload` | JSON NULL | Datos enviados a GLPI (para auditoria) |
| `ai_used` | TINYINT(1) DEFAULT 0 | Si se uso Claude para formatear |
| `ai_tokens_used` | INT NULL | Tokens consumidos en la peticion de formateo |
| `result` | ENUM('success','error') NOT NULL | |
| `error_message` | TEXT NULL | Mensaje de error si fallo |
| `created_at` | DATETIME | |

Indices: INDEX en (`employee_id`, `created_at`), INDEX en (`glpi_ticket_id`).

---

## 6. Plantillas de GLPI mapeadas

Las siguientes plantillas ya existen en GLPI y definen los textos estandar para documentar tickets. El bot guia al tecnico para llenar los campos variables (`[__]`) y arma el texto final con la plantilla.

### 6.1. Seguimientos (tipo: followup en GLPI)

Estas acciones crean un seguimiento (followup) en el ticket y mantienen o cambian el estado a "En curso" (status 3).

**TEMPLATE_EN_CAMINO** (Plantilla #3)
```
Se inicia traslado hacia el sitio de atencion. Arribo estimado a las {hora_estimada} hrs.
Tecnico asignado: {nombre_tecnico}
```
Campos que pide el bot: `hora_estimada` (texto, formato HH:MM)
Campos automaticos: `nombre_tecnico` (del perfil del empleado vinculado)
Status GLPI resultante: 3 (En curso)

**TEMPLATE_EN_SITIO** (Plantilla #4)
```
{nombre_tecnico} se ha presentado en sitio a las {hora_llegada} hrs. Se da inicio a la atencion del ticket {ticket_ref}.
```
Campos que pide el bot: confirmacion (si/no); `hora_llegada` se toma del timestamp actual
Campos automaticos: `nombre_tecnico`, `ticket_ref`
Status GLPI resultante: 3 (En curso)
Regla especial: si el ticket estaba en status 4 (En espera), cambiarlo a 3 (SLA reanuda)

**TEMPLATE_REPROGRAMACION** (Plantilla #5)
```
El ticket {ticket_ref} ha sido reprogramado.
Nueva fecha y hora de atencion: {nueva_fecha_hora} hrs.

Se agradece la comprension.
```
Campos que pide el bot: `nueva_fecha_hora` (texto, formato libre o DD/MM/YYYY HH:MM)
Status GLPI resultante: 3 (En curso)

**TEMPLATE_DIAGNOSTICO** (Plantilla #6)
```
Se ha concluido el diagnostico del ticket {ticket_ref}

Diagnostico realizado:

{diagnostico}
```
Campos que pide el bot: `diagnostico` (texto libre, acepta multiples mensajes; acepta fotos como adjuntos al followup)
Candidato a formateo con Claude: SI
Status GLPI resultante: 3 (En curso)

### 6.2. Pendientes (tipo: followup en GLPI + cambio de estado)

Estas acciones crean un followup y cambian el estado del ticket a "En espera" (status 4), lo que pausa el contador de SLA en GLPI.

**TEMPLATE_PENDIENTE_CLIENTE** (Plantilla #7)
```
El ticket {ticket_ref} se encuentra en espera de respuesta o accion del solicitante para continuar con la atencion.

Detalle: {detalle}

Favor de contactar al ejecutivo asignado o responder a la brevedad.
```
Campos que pide el bot: `detalle` (texto libre, breve)
Status GLPI resultante: 4 (En espera) -- PAUSA SLA

**TEMPLATE_PENDIENTE_REFACCION** (Plantilla #8)
```
El ticket {ticket_ref} se encuentra en espera de las refacciones requeridas para su resolucion.

Refacciones requeridas: {refacciones}

Se notificara en cuanto esten disponibles.
```
Campos que pide el bot: `refacciones` (texto libre, lista de refacciones)
Status GLPI resultante: 4 (En espera) -- PAUSA SLA

### 6.3. Resoluciones (tipo: solution en GLPI)

Estas acciones crean una solucion (solution, no followup) en el ticket y cambian el estado a "Resuelto" (status 5). En la API de GLPI se usa el endpoint de ITILSolution.

**TEMPLATE_RESOLUCION_SIN_REFACCION** (Plantilla #9)
```
El ticket {ticket_ref} ha sido resuelto satisfactoriamente.

Pasos realizados:
{pasos_realizados}

Fecha y hora de inicio: {fecha_inicio}
Fecha y hora de termino: {fecha_termino}
Visto Bueno: {visto_bueno}

El ticket queda en estado Resuelto. Se solicita confirmar la conformidad del servicio.
De no recibirse respuesta en un plazo de 2 dias habiles, el ticket pasara automaticamente al estado Cerrado.
```
Campos que pide el bot: `pasos_realizados` (texto libre, multiples mensajes), `fecha_inicio` (HH:MM o timestamp), `fecha_termino` (HH:MM o timestamp), `visto_bueno` (nombre de la persona que dio conformidad)
Fotos: al menos una si `require_photo_on_resolution` esta activo
Candidato a formateo con Claude: SI (para `pasos_realizados`)
Status GLPI resultante: 5 (Resuelto) -- SLA detiene

**TEMPLATE_RESOLUCION_CON_REFACCION** (Plantilla #10)
```
El ticket {ticket_ref} ha sido resuelto satisfactoriamente con uso de refacciones.

Pasos realizados:
{pasos_realizados}

Refacciones utilizadas: {refacciones_utilizadas}

Fecha y hora de inicio: {fecha_inicio}
Fecha y hora de termino: {fecha_termino}
Visto Bueno: {visto_bueno}

El ticket queda en estado Resuelto. Se solicita confirmar la conformidad del servicio.
De no recibirse respuesta en un plazo de 2 dias habiles, el ticket pasara automaticamente al estado Cerrado.
```
Campos que pide el bot: todos los de la plantilla anterior + `refacciones_utilizadas`
Candidato a formateo con Claude: SI
Status GLPI resultante: 5 (Resuelto)

**TEMPLATE_RESOLUCION_REMOTA** (Plantilla #12)
```
El ticket {ticket_ref} ha sido resuelto satisfactoriamente de forma remota.

Modalidad de atencion: {modalidad}

Pasos realizados:
{pasos_realizados}

Fecha y hora de inicio: {fecha_inicio}
Fecha y hora de termino: {fecha_termino}
Visto Bueno: {visto_bueno}

El ticket queda en estado Resuelto. Se solicita confirmar la conformidad del servicio.
De no recibirse respuesta en un plazo de 2 dias habiles, el ticket pasara automaticamente al estado Cerrado.
```
Campos que pide el bot: `modalidad` (seleccion: "Llamada telefonica" o "Conexion remota"), mas los demas campos
Candidato a formateo con Claude: SI
Status GLPI resultante: 5 (Resuelto)

**TEMPLATE_RESOLUCION_ARBITRARIA** (Plantilla #11)
```
El ticket {ticket_ref} ha sido cerrado.

Motivo del cierre: {motivo}
Persona que solicita cierre: {persona_solicita}

De considerar que el caso no fue resuelto, es posible reabrirlo o contactar a MAC.
```
Campos que pide el bot: `motivo` (texto libre), `persona_solicita` (texto)
Restriccion: este tipo de resolucion podria requerir un flag en settings para habilitar/deshabilitar su uso por tecnicos. Si esta deshabilitado, el bot no lo muestra como opcion.
Status GLPI resultante: 5 (Resuelto)

### 6.4. Acciones fuera de alcance del bot

Las siguientes plantillas NO se implementan en el bot porque son responsabilidad de Mesa Central:
- Plantilla #1: IDS Asignado
- Plantilla #2: IDS Reasignado
- Plantilla #14: Cancelacion de ticket

---

## 7. Maquina de estados de la conversacion

Cada tecnico tiene un estado de conversacion que determina como el bot interpreta el siguiente mensaje. Los estados posibles:

| State | Descripcion | Siguiente |
|---|---|---|
| `idle` | Sin flujo activo. Acepta comandos (`/start`, `/tickets`, `/ayuda`) | `selecting_ticket` |
| `awaiting_employee_id` | Registro: esperando numero de empleado | `idle` (si exito) |
| `selecting_ticket` | Se mostro la lista de tickets, esperando seleccion | `ticket_menu` |
| `ticket_menu` | Se muestra el menu de acciones para un ticket | Segun accion seleccionada |
| `action_en_camino` | Esperando hora estimada de arribo | `idle` (tras confirmar) |
| `action_en_sitio` | Esperando confirmacion de llegada | `idle` |
| `action_reprogramacion` | Esperando nueva fecha/hora | `idle` |
| `action_diagnostico` | Recibiendo texto/fotos del diagnostico (multi-mensaje) | `confirm_diagnostico` |
| `confirm_diagnostico` | Mostrando resumen del diagnostico, esperando confirmacion | `idle` |
| `action_pendiente_tipo` | Seleccionando tipo de pendiente (cliente / refaccion) | `action_pendiente_detalle` |
| `action_pendiente_detalle` | Recibiendo detalle del pendiente | `confirm_pendiente` |
| `confirm_pendiente` | Confirmando antes de pausar SLA | `idle` |
| `action_resolucion_tipo` | Seleccionando tipo de resolucion (sin refaccion / con refaccion / remota / arbitraria) | `resolucion_pasos` |
| `resolucion_pasos` | Recibiendo pasos realizados (multi-mensaje, fotos) | `resolucion_refacciones` o `resolucion_datos` |
| `resolucion_refacciones` | Recibiendo lista de refacciones (solo si es "con refaccion") | `resolucion_datos` |
| `resolucion_modalidad` | Seleccionando modalidad remota (solo si es "remota") | `resolucion_datos` |
| `resolucion_datos` | Recibiendo fecha inicio, fecha termino, visto bueno | `confirm_resolucion` |
| `resolucion_motivo` | Recibiendo motivo y persona (solo si es "arbitraria") | `confirm_resolucion` |
| `confirm_resolucion` | Mostrando resumen completo, esperando confirmacion final | `idle` |

Reglas generales:
- En cualquier momento, el tecnico puede mandar `/cancelar` para abortar el flujo y volver a `idle`.
- En cualquier momento, el tecnico puede mandar `/tickets` para volver a la lista de tickets (resetea el estado).
- Si el estado expira (`expires_at`), se resetea a `idle` y si el tecnico manda un mensaje, el bot le dice que la sesion expiro y le ofrece reiniciar.
- Los estados que aceptan multi-mensaje (diagnostico, pasos de resolucion) deben tener un boton "Listo" o "Enviar" para indicar que termino de escribir.

---

## 8. Interaccion con GLPI via API REST

Todas las operaciones se hacen a traves de la API REST de GLPI, reutilizando el patron de autenticacion de `GlpiConnector` de Provisioning (initSession / Session-Token / killSession).

### 8.1. Consultar tickets asignados al tecnico

Endpoint GLPI: `GET /apirest.php/Ticket`
Filtros:
- `searchText[status]`: valores 1 (Nuevo), 2 (Asignado), 3 (En curso), 4 (En espera) -- no mostrar Resueltos (5) ni Cerrados (6)
- Asociados al usuario tecnico: usar el criteria de busqueda de GLPI para filtrar por `assigned to` = `glpi_user_id` del tecnico

Datos a mostrar por ticket en Telegram:
- Numero de ticket (`id`)
- Titulo (`name`)
- Estado actual (traducido a texto)
- Prioridad
- Fecha de apertura
- Cliente/Entidad (si disponible)

Ordenar por prioridad descendente, luego por fecha de apertura ascendente.

### 8.2. Crear followup en ticket

Endpoint GLPI: `POST /apirest.php/Ticket/{id}/ITILFollowup`
Body:
```json
{
  "input": {
    "content": "<texto del followup con la plantilla llenada>",
    "is_private": 0
  }
}
```

Si hay fotos: subirlas como documentos adjuntos al ticket (`POST /apirest.php/Document` + vinculacion).

### 8.3. Crear solucion en ticket (resoluciones)

Endpoint GLPI: `POST /apirest.php/Ticket/{id}/ITILSolution`
Body:
```json
{
  "input": {
    "content": "<texto de la resolucion con la plantilla llenada>"
  }
}
```
Esto automaticamente cambia el estado del ticket a Resuelto (5) en GLPI.

### 8.4. Cambiar estado del ticket

Endpoint GLPI: `PUT /apirest.php/Ticket/{id}`
Body:
```json
{
  "input": {
    "status": 4
  }
}
```
Valores:
- 3 = En curso (Processing)
- 4 = En espera (Waiting) -- pausa SLA
- 5 = Resuelto (Solved) -- se maneja via ITILSolution, no via PUT directo

### 8.5. Logica de transicion de estados y SLA

Reglas criticas que el servicio debe aplicar automaticamente:

1. **Cualquier accion de seguimiento (en camino, en sitio, diagnostico, reprogramacion):** si el ticket estaba en status 4 (En espera), cambiarlo a 3 (En curso) ANTES de crear el followup. Esto reanuda el SLA.

2. **Acciones de pendiente:** crear el followup PRIMERO, luego cambiar el status a 4. Orden importante para que GLPI registre el followup con el status correcto.

3. **Resoluciones:** crear la ITILSolution. GLPI cambia automaticamente el status a 5 al crear la solucion; no es necesario un PUT adicional.

4. **Validacion pre-accion:** antes de ejecutar cualquier accion, verificar que el ticket sigue asignado al tecnico y que su status permite la accion (no intentar resolver un ticket ya cerrado, por ejemplo).

---

## 9. Formateo con Claude (opcional)

Cuando `ai_formatting_enabled` esta activo, el bot usa Claude para estructurar el texto libre del tecnico en los campos de diagnostico y pasos de resolucion.

### 9.1. Configuracion

Reutilizar la API key y modelo de `servicedesk_settings`. No duplicar la configuracion de credenciales.
El toggle y el max_tokens son propios de TechBot (`techbot_settings`).

### 9.2. Prompt de formateo

System prompt sugerido (almacenar en `techbot_settings` como `ai_system_prompt`):

```
Eres un asistente que estructura notas tecnicas de soporte en campo.
Recibes texto libre de un tecnico de campo y lo organizas en un formato limpio y profesional.

Reglas:
- Mantener TODA la informacion que el tecnico proporciono, no omitir nada.
- No inventar informacion que el tecnico no menciono.
- Organizar en secciones claras: problema encontrado, accion realizada, resultado.
- Si el tecnico menciona refacciones, listarlas por separado.
- Si el tecnico menciona equipos (marca, modelo, serie), destacarlos.
- Usar lenguaje profesional pero sin alterar los hechos.
- Responder SOLO con el texto formateado, sin explicaciones adicionales.
- Maximo 500 palabras.
```

### 9.3. Flujo

1. El tecnico manda su texto libre (uno o varios mensajes).
2. El bot concatena todo el texto y lo envia a Claude con el system prompt.
3. Claude devuelve el texto estructurado.
4. El bot muestra al tecnico el texto original y el formateado, y le pregunta cual quiere usar.
5. Si el tecnico elige el formateado, se usa ese. Si no, se usa el original.
6. Registrar tokens consumidos en `techbot_activity_log`.

> Si la API de Claude falla o el timeout se excede, usar el texto original sin formateo. No bloquear el flujo.

---

## 10. Flujo de registro (vinculacion)

```
Tecnico abre el bot y manda /start
    |
    v
Bot: "Bienvenido al Bot de Soporte Trantor. Para vincular tu cuenta, envia tu numero de empleado."
    |
    v
Tecnico: "3002"
    |
    v
Bot busca en tabla employees al empleado #3002
    |-- No existe --> "No se encontro un empleado con ese numero. Verifica e intenta de nuevo."
    |
    v
Bot busca en provisioning_external_accounts la cuenta GLPI del empleado
    |-- No tiene cuenta GLPI activa --> "Tu cuenta no tiene acceso a GLPI. Contacta a Mesa de Ayuda."
    |
    v
Bot verifica que no haya otro telegram_chat_id vinculado a este empleado
    |-- Ya vinculado --> "Este empleado ya esta vinculado a otra cuenta de Telegram. Contacta a tu supervisor."
    |
    v
Bot crea registro en techbot_telegram_links:
    telegram_chat_id = chat.id del update
    telegram_username = from.username (si existe)
    telegram_first_name = from.first_name
    employee_id = employees.id
    glpi_user_id = provisioning_external_accounts.external_id (donde system = 'glpi')
    status = 'active'
    verified_at = NOW()
    |
    v
Bot: "{welcome_message de settings}. Ya puedes consultar tus tickets con /tickets."
```

---

## 11. Flujo principal (dia a dia)

```
Tecnico: /tickets
    |
    v
Bot consulta GLPI API: tickets asignados al glpi_user_id del tecnico,
con status IN (1,2,3,4)
    |
    v
Bot muestra lista con botones inline:
    [#12345] Impresora no enciende - En curso
    [#12350] Cableado piso 3 - En espera
    [#12351] Servidor no responde - Asignado
    |
    v
Tecnico selecciona #12345 (callback_data: "ticket:12345")
    |
    v
Bot muestra resumen del ticket + menu de acciones (botones inline):
    Ticket #12345 - Impresora no enciende
    Estado: En curso | Prioridad: Alta
    Cliente: Oficina Monterrey
    Abierto: 28/07/2026 09:15

    [En camino]  [En sitio]
    [Diagnostico]  [Reprogramar]
    [Pendiente]  [Resolver]
    [<- Volver a lista]
    |
    v
Tecnico selecciona una accion --> inicia el flujo correspondiente (seccion 6-7)
    |
    v
Al completar la accion:
    1. Bot arma el texto con la plantilla
    2. (Opcional) Claude formatea si aplica
    3. Bot muestra resumen y pide confirmacion
    4. Tecnico confirma
    5. Bot ejecuta en GLPI (followup/solucion + cambio de status)
    6. Bot registra en techbot_activity_log
    7. Bot confirma: "Followup registrado en ticket #12345" o "Ticket #12345 resuelto"
    8. Estado vuelve a idle
```

---

## 12. Panel de administracion (web en Nexus)

Accesible en `/techbot/*` con AuthFilter + ModuleAccessFilter.

### 12.1. Vistas

**Dashboard (`/techbot`)**
- Total de tecnicos vinculados (activos/inactivos)
- Acciones hoy / esta semana / este mes
- Ultimo acceso por tecnico
- Errores recientes

**Tecnicos vinculados (`/techbot/links`)**
- Tabla: nombre del empleado, numero de empleado, username de Telegram, fecha de vinculacion, status, ultima actividad
- Acciones: desactivar/reactivar vinculacion

**Log de actividad (`/techbot/activity`)**
- Tabla con filtros: tecnico, ticket, accion, rango de fechas, resultado
- Detalle de cada accion: payload enviado a GLPI, respuesta, si uso IA

**Configuracion (`/techbot/settings`)**
- Formulario para los valores de `techbot_settings`
- Boton "Probar conexion" que hace un `getMe` a la API de Telegram para validar el token
- Boton "Registrar webhook" que llama a `setWebhook` de Telegram con la URL configurada
- Los secretos (token, webhook_secret) se manejan como en los demas modulos: indicador "(definida)" si ya tiene valor, campo vacio = conservar

### 12.2. API endpoints (bajo `/api/v1/techbot/`)

| Metodo | Ruta | Descripcion |
|---|---|---|
| POST | `/api/v1/techbot/webhook` | Recibe updates de Telegram (endpoint publico, autenticado por secreto) |
| GET | `/api/v1/techbot/links` | Lista tecnicos vinculados |
| GET | `/api/v1/techbot/links/{id}` | Detalle de una vinculacion |
| PUT | `/api/v1/techbot/links/{id}/deactivate` | Desactiva una vinculacion |
| PUT | `/api/v1/techbot/links/{id}/activate` | Reactiva una vinculacion |
| GET | `/api/v1/techbot/activity` | Log de actividad con filtros |
| GET | `/api/v1/techbot/settings` | Obtiene configuracion (sin secretos) |
| PUT | `/api/v1/techbot/settings` | Actualiza configuracion |
| POST | `/api/v1/techbot/settings/test-connection` | Probar conexion con Telegram API |
| POST | `/api/v1/techbot/settings/register-webhook` | Registrar webhook en Telegram |

---

## 13. Estructura de carpetas del modulo

```
app/Modules/TechBot/
  Config/
  Controllers/
    TechBot.php                    # Web: dashboard, links, activity, settings
    Api/
      TechBotApiController.php     # API: links, activity, settings
      TelegramWebhookController.php # Webhook de Telegram (endpoint publico)
  Database/
    Migrations/
      YYYY-MM-DD-HHMMSS_create_techbot_settings.php
      YYYY-MM-DD-HHMMSS_create_techbot_telegram_links.php
      YYYY-MM-DD-HHMMSS_create_techbot_conversation_states.php
      YYYY-MM-DD-HHMMSS_create_techbot_activity_log.php
    Seeders/
      TechBotModuleSeeder.php      # Registro en tabla modules + asignacion a SuperAdmin
  Models/
    TechBotSettingsModel.php
    TelegramLinkModel.php
    ConversationStateModel.php
    ActivityLogModel.php
  Services/
    TelegramApiService.php         # Wrapper para enviar mensajes/fotos/botones a Telegram
    TelegramWebhookService.php     # Procesa updates entrantes, rutea por tipo
    ConversationService.php        # Maquina de estados: lee estado, determina siguiente, actualiza
    TemplateService.php            # Genera texto de followup/solucion con la plantilla y los datos
    GlpiFieldService.php           # Consulta tickets, crea followups, crea soluciones, cambia estados
    AiFormatterService.php         # Llamada a Claude para formatear texto (reutiliza config de ServiceDesk)
    TechBotSettingsService.php     # Lee/escribe settings con cifrado
  Views/
    dashboard.php
    links/
      index.php
      show.php
    activity/
      index.php
    settings/
      index.php
  Routes.php
```

---

## 14. Seeder

`TechBotModuleSeeder` debe:
1. Insertar registro en tabla `modules`:
   - key: `techbot`
   - name: `TechBot`
   - description: `Canal de Telegram para documentacion de tickets por tecnicos de campo`
   - route_base: `techbot`
   - is_active: 1
2. Asignar el modulo al rol SuperAdmin en `role_module`
3. Insertar settings por defecto en `techbot_settings`:
   - `bot_enabled`: `0` (desactivado hasta que se configure el token)
   - `ai_formatting_enabled`: `0`
   - `ai_max_tokens`: `1024`
   - `require_photo_on_resolution`: `0`
   - `require_visto_bueno_on_resolution`: `1`
   - `welcome_message`: `Tu cuenta ha sido vinculada exitosamente. Ya puedes consultar y documentar tus tickets desde aqui.`

Agregar el seeder a `setup.sh` y `public/setup.php`.

---

## 15. Seguridad

- El webhook de Telegram se valida con el `telegram_webhook_secret` (se envia como query param secreto en la URL del webhook, patron estandar de Telegram).
- El token del bot se cifra en BD con `encryption.key`.
- El tecnico solo puede operar sobre tickets asignados a su `glpi_user_id`. Verificar en cada accion, no solo al listar.
- Si una vinculacion esta en status `inactive`, rechazar todos los mensajes.
- Limitar intentos de registro: maximo 5 intentos fallidos de `/start` por `telegram_chat_id` antes de bloquear temporalmente (15 minutos).
- No loggear contenido sensible (passwords, tokens) en `techbot_activity_log`.
- Validar que el `telegram_chat_id` es un entero positivo valido.

---

## 16. Consideraciones de infraestructura

- El servidor de Nexus debe tener HTTPS con certificado valido para recibir webhooks de Telegram.
- Telegram requiere que el webhook responda en menos de 60 segundos. Si la operacion en GLPI tarda mas, responder 200 al webhook inmediatamente y procesar en background (o aceptar que GLPI responde rapido y procesar sincrono).
- Las fotos enviadas por el tecnico se descargan de los servidores de Telegram (via `getFile` + download URL) y se suben a GLPI como documentos. No se almacenan permanentemente en Nexus.
- El dominio del servidor de Nexus debe estar en la lista de dominios permitidos en la configuracion de red del proyecto si hay un proxy de egreso.

---

## 17. Criterios de aceptacion (resumen)

1. Un tecnico puede vincular su cuenta de Telegram con su numero de empleado y queda asociado a su usuario de GLPI.
2. Un tecnico vinculado puede ver la lista de sus tickets asignados (status 1-4) desde Telegram.
3. Un tecnico puede ejecutar cada una de las 9 acciones definidas (4 seguimientos, 2 pendientes, 4 resoluciones) y el resultado se refleja correctamente como followup o solucion en GLPI.
4. Las acciones de pendiente cambian el status del ticket a 4 (En espera) y pausan el SLA.
5. Las acciones de seguimiento sobre un ticket en espera lo devuelven a status 3 (En curso) automaticamente.
6. Las resoluciones crean una ITILSolution y dejan el ticket en status 5 (Resuelto).
7. Las fotos enviadas por el tecnico se adjuntan al ticket en GLPI.
8. El formateo con Claude es opcional (toggle), no bloquea el flujo si falla, y el tecnico puede elegir entre texto original o formateado.
9. El panel web muestra tecnicos vinculados, log de actividad y configuracion del bot.
10. El webhook de Telegram esta protegido por secreto y el token del bot esta cifrado en BD.
11. Toda accion queda registrada en `techbot_activity_log` con el payload enviado a GLPI.
12. Un tecnico NO puede actuar sobre tickets que no tiene asignados.
