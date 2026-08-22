# Índice de documentación

Mapa de todo lo que vive en `docs/`. La carpeta está organizada **por propósito**,
no por módulo: primero decides qué tipo de respuesta buscas y luego entras a la
carpeta correspondiente.

| Carpeta | Qué contiene | Vigencia |
|---|---|---|
| [`referencia/`](referencia/) | Reglas canónicas del proyecto | Siempre vigente. Si otro documento la contradice, gana ésta. |
| [`modulos/`](modulos/) | Especificaciones de construcción, una carpeta por módulo | Intención histórica. Refleja lo que se pidió construir, no necesariamente el estado actual del código. |
| [`guias/`](guias/) | Prosa para el usuario final y para agentes de mesa | Vigente. Las pantallas de ayuda in-app son espejo de estos archivos. |
| [`operacion/`](operacion/) | Runbooks: cron, reset de BD, fixes de incidentes | Vigente. |
| [`pendiente/`](pendiente/) | Diseñado sin construir, o construido con pendientes abiertos | Trabajo abierto. |

---

## referencia/

Lo que se debe cumplir al escribir código nuevo.

| Documento | Para qué |
|---|---|
| [ARCHITECTURE.md](referencia/ARCHITECTURE.md) | Ciclo de vida del request en CI4, sistema de módulos, patrones controlador/servicio/modelo, estructura de la API, diseño del esquema de BD |
| [CONVENTIONS.md](referencia/CONVENTIONS.md) | PSR-12, naming, rutas, migraciones, seguridad, mensajes de commit. §2.1 es la regla de homologación de identificadores de módulo |
| [CONEXIONES.md](referencia/CONEXIONES.md) | Inventario de integraciones externas: protocolo, tipo de autenticación, dónde se guarda la config y si está cifrada |
| [tt-apps.postman_collection.json](referencia/tt-apps.postman_collection.json) | Colección Postman. Se actualiza cada vez que se agrega o cambia un endpoint |

Fuera de `docs/`, en la raíz del proyecto: `CLAUDE.md` (guía del proyecto para
agentes) y `DESIGN.md` (sistema de diseño, tokens CSS y accesibilidad).

## modulos/

Briefs y specs con los que se construyó cada módulo. Útiles para entender **por
qué** algo se hizo así. Para el estado actual, el código manda.

| Módulo | Documento |
|---|---|
| Provisioning | [brief-aprovisionamiento.md](modulos/provisioning/brief-aprovisionamiento.md) : orquestador del ciclo de vida de identidades hacia GLPI, Mailcow e Intranet |
| Mailboxes | [integracion-mailcow.md](modulos/mailboxes/integracion-mailcow.md) : cómo generar y configurar la API key de Mailcow |
| MailDispatch | [spec.md](modulos/maildispatch/spec.md) : despacho del buzón compartido de M365. Ver también `pendiente/` |
| TechBot | [spec.md](modulos/techbot/spec.md) : bot de Telegram para que técnicos de campo documenten tickets de GLPI |
| ServiceDesk | [actualizacion-masiva.md](modulos/servicedesk/actualizacion-masiva.md) : actualización y cierre masivo de tickets con el mismo Excel del importador |
| HelpdeskSupervisor | [fase-1-supervisor.md](modulos/helpdesk-supervisor/fase-1-supervisor.md) : auditoría de GLPI contra el Manual MAC |
| HelpdeskSupervisor | [fase-2-notificaciones-ia.md](modulos/helpdesk-supervisor/fase-2-notificaciones-ia.md) : notificaciones IA y envío de reportes |
| AgentKpis | [fase-3-kpis-agentes.md](modulos/helpdesk-supervisor/fase-3-kpis-agentes.md) : evaluación mensual de agentes |

## guias/

Escritas para que un agente entienda sus propios números sin preguntarle a nadie.
Las vistas de ayuda in-app son espejo de estos textos: si cambias el cálculo,
actualiza el documento **y** la vista.

| Documento | Espejo en la app |
|---|---|
| [agentkpis-evaluacion.md](guias/agentkpis-evaluacion.md) | Service Desk > Mis evaluaciones (`app/Modules/ServiceDesk/Views/help/evaluacion.php`) |
| [maildispatch-metricas.md](guias/maildispatch-metricas.md) | Despacho de Correo > Equipo y Métricas (`app/Modules/MailDispatch/Views/help/metricas.php`) |
| [manual-mesa-de-ayuda/](guias/manual-mesa-de-ayuda/) | Manual operativo de GLPI para agentes de la MAC. 5 capítulos y 7 anexos. Empieza por [00-indice.md](guias/manual-mesa-de-ayuda/00-indice.md) |

## operacion/

| Documento | Para qué |
|---|---|
| [cronjobs.md](operacion/cronjobs.md) | Cron de producción: rutas de ejemplo en cPanel y comandos de cada worker |
| [fix-ci-sessions-timestamp.md](operacion/fix-ci-sessions-timestamp.md) | Cierre de sesión aleatorio en Docker por `ci_sessions.timestamp` INT vs TIMESTAMP. Aplicado solo en local; producción pendiente de confirmar |
| `reset-db.md` | Modos del script `reset-db.sh`. **No versionado**: vive solo en local, por diseño (ver `.gitignore`) |

## pendiente/

| Documento | Estado |
|---|---|
| [maildispatch-autogestion.md](pendiente/maildispatch-autogestion.md) | Diseño cerrado el 2026-08-05. **Sin construir.** Auto-creación de tickets de GLPI desde correo por reglas |
| [maildispatch-pendientes.md](pendiente/maildispatch-pendientes.md) | Pendientes de MailDispatch al corte del 2026-07-28, para dar el módulo por terminado |

---

## Dónde poner un documento nuevo

1. ¿Es una regla que el código nuevo debe cumplir? : `referencia/`
2. ¿Describe qué construir en un módulo? : `modulos/<modulo>/`
3. ¿Lo va a leer un agente de mesa o un usuario final? : `guias/`
4. ¿Explica cómo operar o reparar algo en producción? : `operacion/`
5. ¿Está diseñado pero no construido? : `pendiente/`

Al mover o renombrar un documento, corre esta verificación para no dejar enlaces rotos:

```bash
grep -rhoE 'docs/[A-Za-z0-9_./-]+\.(md|json)' --include='*.md' --include='*.php' \
  --include='*.sh' --include='*.json' . --exclude-dir=vendor --exclude-dir=.git \
  --exclude-dir=builds --exclude-dir=writable | sort -u \
  | while read -r p; do [ -f "$p" ] || echo "ROTA: $p"; done
```
