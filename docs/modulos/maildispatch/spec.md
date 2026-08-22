# Implementación del módulo MailDispatch en tt-nexus

Lee CLAUDE.md, docs/referencia/ARCHITECTURE.md y docs/referencia/CONVENTIONS.md antes de empezar. Este módulo debe cumplir todas las convenciones existentes del proyecto (estructura modular, controladores delgados, servicios, API espejo, prefijos de tablas, tokens de diseño, filtros de acceso). No repito aquí esas convenciones porque ya las tienes en contexto: este documento define QUÉ construir, no cómo acomodarlo.

---

## 1. Contexto y problema a resolver

La mesa de ayuda opera un buzón compartido de Microsoft 365 (mesadeayuda@...). Hoy los agentes lo leen directamente en Outlook y eso genera: correos perdidos sin seguimiento, duplicidad de esfuerzo (dos agentes atienden el mismo correo sin saberlo), cero visibilidad de quién atiende qué, y ningún registro de si una solicitud fue respondida.

No se quiere conectar GLPI al correo de forma nativa porque no todos los correos generan ticket, y cuando sí lo generan, el agente debe capturar categorización manual en GLPI. GLPI queda fuera del alcance de integración directa: solo se referencia por número de folio.

## 2. Solución

Crear un nuevo módulo **MailDispatch**: una capa de despacho (dispatcher) que mantiene una copia viva de solo lectura del buzón compartido dentro de nexus, agrupada por conversaciones (hilos), donde cada conversación es un elemento de trabajo asignable a un agente, con estados, seguimiento, disposición obligatoria al cierre y referencia opcional a ticket GLPI.

Principio rector de fase 1 y 2: **nexus lee el buzón, nunca lo modifica**. Los agentes responden desde Outlook. Si nexus falla, la operación sigue funcionando como hoy. En fase 3 se agrega el envío de respuestas desde nexus.

## 3. Identidad del módulo

- Carpeta y namespace: `MailDispatch` / `App\Modules\MailDispatch`
- Key en tabla `modules`: `mail_dispatch`
- Route base web: `/dispatch/*`
- Rutas API: `/api/v1/dispatch/*`
- Nombre visible en UI (modules.name y sidebar): "Despacho de Correo"
- Prefijo de tablas: `maildispatch_`
- Es un módulo independiente. No modificar el módulo de mesa de ayuda existente. Si en el futuro necesita algo de otro módulo, se consume vía servicios (mismo patrón que Provisioning reutiliza MailcowApi).

## 4. Separación estricta de responsabilidades (requisito del negocio)

**Toda la configuración vive en el área de administración del super administrador (módulo Core), no en el módulo nuevo.** El módulo MailDispatch contiene únicamente la parte operativa (cola, asignación, seguimiento, métricas).

### 4.1 Panel de configuración (solo SuperAdmin, dentro del admin de Core)

Crear una sección de configuración de MailDispatch en el panel de administración con:

- **Credenciales de Microsoft Graph**: Tenant ID, Client ID, Client Secret. El secret se guarda cifrado en base de datos (usar el servicio de cifrado de CI4 con la key de la app) y una vez guardado se muestra enmascarado, nunca en claro. Nada de credenciales de Graph en `.env`: todo editable desde UI porque puede rotar.
- **Cuenta del buzón**: dirección del buzón compartido a sincronizar (ej. mesadeayuda@dominio.com). Diseñar la tabla de settings de forma que en el futuro pueda soportar más de un buzón, aunque la UI de esta versión maneje uno solo.
- **Control de sincronización**: encendido/apagado de la sincronización, tamaño de página, y botón "Probar conexión" que valida en vivo: obtención de token, acceso al buzón y lectura de la carpeta Inbox, mostrando el resultado con detalle del error si falla.
- **Estado de la sincronización**: última corrida, resultado, mensajes procesados, errores recientes (log consultable).
- **Gestión de agentes**: qué usuarios de nexus participan como agentes del despacho y quiénes tienen rol de dispatcher (pueden asignar/reasignar a otros). Esto es adicional al control de acceso por rol/módulo normal.
- **Catálogo de disposiciones de cierre**: editable, con valores semilla: "Ticket GLPI", "No requiere ticket", "Spam / descartado", "Duplicado".
- **Umbrales de SLA**: minutos máximos sin asignar y minutos máximos sin primera respuesta (usados para alertas visuales y métricas).

Todos los settings en una tabla `maildispatch_settings` (clave-valor o estructura equivalente), nunca hardcodeados.

### 4.2 Módulo operativo (usuarios con acceso a `mail_dispatch`)

Solo uso: bandeja de conversaciones, detalle de hilo, asignación, cambio de estado, cierre con disposición, y en fase 2 el tablero de métricas. Ningún dato de configuración ni credenciales visible aquí.

## 5. Integración con Microsoft Graph

- Autenticación **client credentials** (permisos de aplicación). Fase 1 y 2 requieren `Mail.Read`; fase 3 agrega `Mail.Send`.
- Documentar en el README del módulo el prerrequisito de infraestructura: crear la app registration en Entra ID y aplicar una **Application Access Policy** en Exchange Online para restringir el permiso exclusivamente al buzón de mesa de ayuda. Incluir en el README los comandos PowerShell de referencia para que el administrador de M365 la configure. El sistema no debe asumir acceso a otros buzones.
- Servicio `GraphMailService` (o equivalente) encapsula: obtención y cacheo del token, **delta queries** sobre la carpeta Inbox del buzón (persistiendo el deltaLink/token entre corridas en BD), paginación, y manejo de expiración del delta token (resincronización completa controlada si Graph lo invalida).
- Comando spark `maildispatch:sync-mailbox` para cron (frecuencia sugerida cada 1-2 minutos). Debe ser **idempotente**: correrlo dos veces no duplica nada (unicidad por id de mensaje de Graph). Debe tener lock para evitar corridas simultáneas, opción `--full` para resincronización completa, y `--debug`.
- De cada mensaje persistir al menos: id de Graph, `conversationId`, `internetMessageId`, headers `In-Reply-To`/`References` cuando estén disponibles, remitente, destinatarios, asunto, fecha de recepción, preview del cuerpo, cuerpo (para vista de detalle), indicador de adjuntos. Los adjuntos no se descargan en fase 1; solo se indica su existencia y se listan por nombre si es viable.

## 6. Modelo de conversaciones y detección de respuestas (núcleo del diseño)

La unidad de trabajo es la **conversación**, no el correo individual:

- Mensaje con `conversationId` desconocido: se crea una conversación nueva en estado "Nueva" y aparece en la cola sin asignar.
- Mensaje con `conversationId` ya registrado: se anexa como mensaje del hilo existente. **Nunca genera un elemento nuevo en la cola.**
- Fallback de hilado: si el `conversationId` no coincide pero `In-Reply-To`/`References` apuntan a un `internetMessageId` ya almacenado, anexar a esa conversación (clientes de correo externos a veces rompen el hilo de Microsoft).
- **Dirección del mensaje**: si el remitente es la propia cuenta del buzón, es un mensaje saliente (un agente respondió desde Outlook) y la conversación pasa automáticamente a "Respondida", registrando el timestamp de primera respuesta si es la primera. Si el remitente es externo y la conversación ya estaba respondida o en atención, pasa a "Esperando agente" para que el asignado vea que hay réplica pendiente.

### Estados de conversación

`Nueva` → `Asignada` → `En atención` → `Respondida` ⇄ `Esperando agente` → `Cerrada`. Registrar cada transición con timestamp y usuario en una bitácora. Una conversación cerrada que recibe un mensaje entrante nuevo se **reabre** automáticamente en "Esperando agente" conservando su asignación previa.

### Asignación

- **Claim atómico**: el agente toma una conversación sin dueño. La operación debe ser un UPDATE condicionado a que siga sin asignar; si dos agentes la toman a la vez, solo uno gana y el otro recibe mensaje claro de "ya fue tomada por X". Este es el mecanismo que elimina el trabajo duplicado: implementarlo con cuidado.
- Los usuarios con rol dispatcher pueden asignar y reasignar conversaciones a cualquier agente.
- Toda asignación y reasignación queda en bitácora (`quién`, `a quién`, `cuándo`).

### Cierre

- Cerrar exige seleccionar una **disposición** del catálogo. Si la disposición es "Ticket GLPI", exigir el número de folio (campo de texto; sin integración con GLPI en esta versión, solo referencia). Comentario opcional de cierre.

## 7. Tablas (prefijo `maildispatch_`, nombres finales a tu criterio siguiendo convenciones)

- Conversaciones: conversationId de Graph, asunto, solicitante original (nombre/correo), estado, agente asignado, disposición, folio GLPI, timestamps clave (recibido, asignado, primera respuesta, última actividad, cerrado), contador de mensajes.
- Mensajes: id Graph único, FK a conversación, dirección (in/out), remitente, fecha, asunto, preview, cuerpo, flag de adjuntos.
- Bitácora de asignaciones y transiciones de estado.
- Settings (ver sección 4.1).
- Estado de sincronización: deltaLink vigente, última corrida, resultado, contadores.
- Log de corridas de sincronización (para el panel de estado del admin).

## 8. Fases de implementación

Implementa en este orden. Cada fase debe quedar funcional, migrada, sembrada y verificada antes de pasar a la siguiente.

### Fase 1: Sincronización y despacho (el corazón)

1. Registro del módulo (namespace, rutas, migraciones, `MailDispatchModuleSeeder` asignándolo al SuperAdmin).
2. Panel de configuración en el admin de Core (sección 4.1 completa, incluyendo prueba de conexión y cifrado del secret).
3. `GraphMailService` + comando `maildispatch:sync-mailbox` con delta queries.
4. Vista principal del módulo: bandeja con pestañas o filtros por estado (Sin asignar / Mías / Todas / Cerradas), mostrando asunto, solicitante, antigüedad, estado, agente. Las conversaciones sin asignar que exceden el umbral de SLA se resaltan visualmente.
5. Vista de detalle de conversación: hilo completo de mensajes (entrantes y salientes diferenciados), acciones de tomar/asignar/reasignar, cambio de estado, cierre con disposición, campo de folio GLPI, notas internas del agente (visibles solo en nexus, opcional pero recomendado).
6. Claim atómico, bitácora de asignaciones y transiciones.
7. API espejo completa bajo `/api/v1/dispatch/*` y actualización de la colección Postman.
8. Alta del seeder en `setup.sh` y `public/setup.php`; `php spark db:verify-schema` debe pasar.

Criterio de aceptación de fase 1: dos correos nuevos llegan al buzón, aparecen como conversaciones nuevas tras el sync; un agente toma una, otro agente intenta tomarla y recibe el aviso; el agente responde desde Outlook y en el siguiente sync la conversación pasa sola a "Respondida"; el usuario replica y pasa a "Esperando agente"; el agente cierra con disposición "Ticket GLPI" capturando el folio. Ningún hilo existente reaparece como nuevo.

### Fase 2: Métricas y control gerencial

1. Tablero dentro del módulo con: backlog sin asignar actual, tiempo promedio de primera asignación, tiempo promedio de primera respuesta, volumen por agente, conversaciones abiertas por agente, distribución de disposiciones (ticket vs no ticket vs spam), volumen diario recibido. Filtros por rango de fechas y agente.
2. Alertas visuales de envejecimiento en la bandeja según umbrales de SLA configurados en el admin.
3. Endpoints API espejo de las métricas (para consumo futuro de reportes).
4. Exportación CSV de conversaciones con filtros aplicados.

Usar los tokens del design system para el tablero; nada de librerías pesadas si con componentes simples se resuelve.

### Fase 3: Respuesta desde nexus

1. Agregar `Mail.Send` (documentar en README el cambio de permisos y que la Application Access Policy ya lo cubre al ser el mismo buzón).
2. Responder desde la vista de detalle: la respuesta se envía vía Graph **como respuesta al hilo** (reply del mensaje original, no correo nuevo), desde la dirección del buzón compartido. El mensaje enviado se registra de inmediato como mensaje saliente de la conversación y la marca "Respondida" sin esperar al siguiente sync.
3. Plantillas de respuesta: catálogo CRUD administrable (puede vivir en el módulo, no en el admin de Core, porque es operativo), con variables simples como nombre del solicitante.
4. Toggle en la configuración del admin para habilitar/deshabilitar globalmente el envío desde nexus (por si se decide operar solo lectura).

## 9. Requisitos transversales

- Filtros de autenticación y acceso a módulo en todos los grupos de rutas; la sección de configuración además restringida a SuperAdmin.
- Controladores delgados, lógica en servicios, validación centralizada.
- Todo texto de UI en español; identificadores en inglés.
- Manejo de errores de Graph robusto: token inválido, throttling (respetar Retry-After), buzón inaccesible. Los errores se registran y se muestran en el panel de estado del admin, nunca rompen la bandeja operativa.
- Ninguna credencial en logs.
- Al terminar cada fase: migraciones aplicadas con `migrate --all`, `db:verify-schema` en verde, Postman actualizado, y commit siguiendo las convenciones de git del proyecto.
