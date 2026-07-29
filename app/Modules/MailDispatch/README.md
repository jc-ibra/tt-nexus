# MailDispatch

Capa de despacho (dispatcher) sobre el buzón compartido de Microsoft 365 de la
mesa de ayuda. Mantiene en Nexus una copia **viva de solo lectura** del buzón,
agrupada por **conversaciones** (hilos), donde cada conversación es un elemento
de trabajo asignable con estados, bitácora, disposición de cierre y referencia
opcional a un folio GLPI.

- **Key del módulo:** `mail_dispatch`
- **Rutas web:** `/dispatch/*` · **API:** `/api/v1/dispatch/*` · **Admin:** `/admin/dispatch/*`
- **UI:** "Despacho de Correo" · **Prefijo de tablas:** `maildispatch_`

Principio rector (Fase 1–2): **Nexus lee el buzón, nunca lo modifica.** Los
agentes responden desde Outlook. Si Nexus falla, la operación sigue igual que
hoy. En Fase 3 se habilita responder al hilo desde Nexus.

---

## Arquitectura

| Pieza | Rol |
|---|---|
| `GraphMailService` | Token client-credentials (cacheado), delta queries sobre Inbox/Sent, prueba de conexión y reply (F3). No lee BD ni `.env`; recibe credenciales inyectadas. |
| `MailboxSyncService` | Orquesta la sincronización delta por carpeta (Inbox + Enviados), idempotente y resumible. Registra estado y corridas. |
| `ConversationService` | Núcleo: ingesta con hilado (conversationId + fallback In-Reply-To/References), dirección in/out, máquina de estados, **claim atómico**, asignación, cierre, reapertura y bitácora. |
| `MailDispatchMetrics` | Analítica de solo lectura para el tablero (F2) y export CSV. |
| `ReplyService` | Envío de respuesta al hilo vía Graph (F3), gated por el toggle de admin. |
| `MailDispatchSettings` | Accesor tipado sobre `maildispatch_settings`; el secret se cifra con `CredentialCipher`. |

Comando de sincronización (cron, cada 1–2 min sugerido):

```bash
php spark maildispatch:sync-mailbox           # incremental (delta)
php spark maildispatch:sync-mailbox --full     # resincronización completa
php spark maildispatch:sync-mailbox --debug    # progreso por carpeta
```

```cron
*/2 * * * * cd /ruta/al/proyecto && php spark maildispatch:sync-mailbox >> /dev/null 2>&1
```

---

## Máquina de estados

```
nueva → asignada → en_atencion → respondida ⇄ esperando_agente → cerrada
```

- Un mensaje **entrante** con `conversationId` nuevo crea una conversación en `nueva`.
- Un mensaje con `conversationId` ya registrado se **anexa** al hilo (nunca crea un elemento nuevo).
- Fallback de hilado: `In-Reply-To`/`References` → un `internetMessageId` ya almacenado.
- Si el remitente es el **propio buzón** (respuesta desde Outlook, carpeta Enviados) → la conversación pasa sola a `respondida` y se registra la primera respuesta.
- Un mensaje entrante sobre una conversación `respondida`/`en_atencion` → `esperando_agente`.
- Una conversación `cerrada` que recibe un entrante se **reabre** en `esperando_agente` conservando su asignación.
- **Claim atómico:** tomar una conversación es un `UPDATE ... WHERE agent_id IS NULL`; si dos agentes la toman a la vez, solo uno gana y el otro ve "ya fue tomada por X".

---

## Prerrequisito de infraestructura (Microsoft 365 / Entra ID)

MailDispatch usa **permisos de aplicación** (client credentials). El
administrador de M365 debe:

1. **Crear una App Registration** en Entra ID y un **client secret**.
2. Conceder permisos de aplicación de Microsoft Graph y otorgar consentimiento de administrador:
   - `Mail.Read` (Fase 1–2)
   - `Mail.Send` (Fase 3, para responder desde Nexus)
3. **Restringir el acceso al buzón de la mesa de ayuda** con una *Application Access Policy* en Exchange Online, para que la app **no** pueda leer otros buzones.

Luego, en Nexus → Administración → Configuración → **Despacho de Correo**, capturar
Tenant ID, Client ID, Client Secret (se guarda cifrado) y la dirección del buzón,
y usar **Probar conexión** antes de habilitar la sincronización.

### Application Access Policy (PowerShell de referencia)

```powershell
# Conéctate a Exchange Online
Connect-ExchangeOnline

# 1) Grupo de seguridad con correo que contiene SOLO el buzón de mesa de ayuda
New-DistributionGroup -Name "MailDispatch-Scope" -Type Security `
  -PrimarySmtpAddress maildispatch-scope@tudominio.com
Add-DistributionGroupMember -Identity "MailDispatch-Scope" `
  -Member mesadeayuda@tudominio.com

# 2) Restringe la App (usa el Application/Client ID de la App Registration)
New-ApplicationAccessPolicy `
  -AppId "<CLIENT_ID>" `
  -PolicyScopeGroupId "maildispatch-scope@tudominio.com" `
  -AccessRight RestrictAccess `
  -Description "Nexus MailDispatch: solo buzón mesa de ayuda"

# 3) Verifica el alcance
Test-ApplicationAccessPolicy -Identity mesadeayuda@tudominio.com -AppId "<CLIENT_ID>"
Test-ApplicationAccessPolicy -Identity otrousuario@tudominio.com  -AppId "<CLIENT_ID>"  # debe DENEGAR
```

> La política puede tardar hasta ~30 min en propagarse.

---

## Fases

- **Fase 1 — Sincronización y despacho:** bandeja con filtros (Sin asignar / Mías / Todas / Cerradas), detalle de hilo, claim/asignación/reasignación, cambio de estado, cierre con disposición + folio, notas internas, bitácora, y API espejo. **Implementada.**
- **Fase 2 — Métricas:** tablero (backlog, tiempos promedio, volumen por agente, disposiciones, volumen diario), alertas de SLA en la bandeja, export CSV, API espejo. **Implementada.**
- **Fase 3 — Respuesta desde Nexus:** reply al hilo vía Graph desde el buzón compartido, catálogo de plantillas, toggle global en admin. **Implementada** (deshabilitada por defecto; requiere `Mail.Send`).

### Nota sobre Fase 3

El envío usa la acción `/reply` de Graph (mantiene el hilo). El mensaje saliente
se registra de inmediato en la conversación; la copia real en *Enviados* llega en
el siguiente sync con su propio id de Graph (registro duplicado benigno,
diferenciable porque el inmediato lleva `graph_id` con prefijo `nexus:`).

---

## Seguridad

- Ninguna credencial de Graph vive en `.env`: todo se edita desde la UI y el secret se guarda **cifrado**.
- El secret nunca se muestra en claro ni se registra en logs.
- Toda la configuración está restringida a **SuperAdmin**; el área operativa exige acceso al módulo `mail_dispatch` y estar registrado como agente para poder tomar/asignar.
