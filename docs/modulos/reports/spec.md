# Reports — informe ejecutivo mensual

**Estado:** construido 2026-09-18. Reemplaza a KPIsOperativos como fuente del reporte
de dirección; KPIsOperativos queda como archivo histórico de solo consulta (ver
`docs/referencia/CONEXIONES.md`).

## Por qué existe

KPIsOperativos era un pipeline **offline**: alguien exportaba un CSV/XLSX de otro
GLPI, lo subía, y el módulo calculaba un snapshot de 22 métricas por reporte. Sin
eje temporal (el período era un filtro de ingesta, no una dimensión), sin conexión
directa a GLPI (ya existente en la plataforma vía Provisioning) y sin nada más que
tickets: no mesa de ayuda por correo, no calidad documental, no desempeño de
agentes — datos que ya vive la plataforma en otros módulos.

## Arquitectura: snapshot mensual + providers

Un período natural (`YYYY-MM`) se materializa en una fila de `reports_snapshots`
con un `payload_json` compuesto por **secciones**. Cada sección la produce un
*provider* que implementa `Services\Providers\ReportSectionProvider`:

| Provider | Sección | Fuente |
|---|---|---|
| `GlpiTicketsProvider` | `glpi_tickets` | GLPI directo, vía `Provisioning\Services\GlpiDbConnection` |
| `TrendProvider` | `trend` | Re-consulta `GlpiTicketsProvider` para los N meses anteriores + backlog en vivo |
| `DispatchProvider` | `dispatch` | `MailDispatch\Services\MailDispatchMetrics::dashboard()`, sin cambios |
| `QualityProvider` | `quality` | `HelpdeskSupervisor\Services\HelpdeskSupervisorBridge::periodQualitySummary()` |
| `AgentPerformanceProvider` | `agents` | `AgentKpis\Services\AgentKpisBridge::periodEvaluationSummary()` |

`SnapshotBuilder::generate()` corre cada provider; si uno no está disponible o
lanza una excepción, esa sección queda `['available' => false]` en el payload —
el resto del informe se genera igual. Ningún provider toca tablas de otro módulo
directamente: GLPI solo por `GlpiDbConnection`, el resto por bridge.

### Congelado vs. en vivo

`payload_json` es inmutable una vez generado (`status = ready`). El dashboard, la
presentación y los archivos PPTX/XLSX se renderizan siempre desde ese payload. La
única excepción es `/reports/{y}/{m}/drill`: consulta GLPI en el momento, para el
mismo rango de fechas del mes, sin tocar el snapshot. Un SuperAdmin puede
regenerar un mes ya cerrado (`reports.admin.regenerate`); el payload anterior se
archiva en `reports_snapshot_versions` antes de sobrescribirse.

## Paridad con el legacy (`GlpiTicketsProvider`)

Reproduce casi el mismo contrato que `KPIsOperativos\Services\GlpiKpiCalculator::compute()`
(`total, cerrados, en_curso, tasa_cierre, sla_pct, prom_h, sin_reg, sin_ids,
reg_universe, reg_top, est_top, cat_top, ids_top, ids_bottom, estados_ticket,
env_total, env_cerr, env_pend, env_pct, coord_tickets, coord_info`), reutilizando
las mismas constantes de exclusión de `KPIsOperativos\Config\GlpiSchema`
(`CLOSED_STATES`, `ENVIOS_CATEGORY_SUBSTRING`, `ALMACEN_CATEGORY_SUBSTRING`,
`LAB_SUBCAT`) para que "Control de Envíos" y las exclusiones de regional/técnico
signifiquen exactamente lo mismo en ambos módulos. Dos diferencias deliberadas
frente al legacy, decididas con el usuario tras ver el mapeo real de campos:

- **Sin `proyecto` ni `cliente`.** En el CSV legacy eran columnas propias; en GLPI
  directo esa información ya la lleva la jerarquía de categorías (p. ej.
  `OP > CE > Afirme > Edificios`). El único campo literal "Proyecto" del plugin
  vive dentro del contenedor **Control de Envíos** (`proyectofield`): es metadato
  de envío, no un proyecto general del ticket.
- **`idc` se llama `ids`.** El nombre de negocio cambió (IDC → IDS); el snapshot
  usa `ids_top`/`ids_bottom`/`sin_ids`. El catálogo compartido de homologación
  fuzzy (`kpi_glpi_idc_canonical`) conserva su nombre interno histórico — es
  detalle de implementación, no se expone en la UI de Reports.

Los campos GLPI-directo (regional, estado, municipio, sucursal, IDS) ya no son
columnas de un CSV: cada uno vive en un contenedor del plugin *Additional
Fields*, y **no necesariamente el mismo contenedor** — en este despliegue,
regional/estado/municipio/sucursal viven en "Clientes Externos" pero IDS vive en
su propio contenedor "IDS". Por eso el mapeo en `/admin/reports/settings` es por
campo, no por contenedor único: `reports_settings.glpi_field_bindings` es un JSON
`logical_key => {container_id, field}` (`ReportsSettingsService::glpiFieldBindings()`).
Un campo sin binding configurado no rompe el snapshot: `GlpiTicketsProvider`
marca `client_data_available` (regional/estado/municipio/sucursal) e
`ids_available` como flags independientes, y el dashboard avisa cuál falta.

Los catálogos de coordinadores y de homologación de técnicos **no se duplican**:
se consumen del módulo legacy vía `KPIsOperativos\Services\KpisOperativosBridge`
(`coordinatorMap()`, `canonicalIdcName()`), el mismo patrón que
`HelpdeskSupervisorBridge`/`AgentKpisBridge` ya establecían.

## Secciones nuevas

- **Tendencia** (`TrendProvider`): serie de N meses (configurable,
  `reports_settings.trend_months`, default 12), comparativa contra el mes
  anterior y contra el mismo mes del año pasado con delta absoluto/porcentual, y
  antigüedad del backlog abierto **hoy** (no del mes del informe: es información
  operativa "al momento", igual que el drill-down).
- **Mesa de Ayuda por Correo** (`DispatchProvider`): expone tal cual el payload de
  `MailDispatchMetrics::dashboard()` para el rango del mes.
- **Calidad Documental** (`QualityProvider`): corrida de auditoría de
  HelpdeskSupervisor del mes, cumplimiento por regla, top de agentes con más
  desviaciones, escalaciones válidas del período.
- **Desempeño de Agentes** (`AgentPerformanceProvider`): evaluaciones mensuales de
  AgentKpis del período — **todas**, sin filtrar por estatus publicado (a
  diferencia del bridge que usa Service Desk para "Mis evaluaciones"): este es el
  reporte del supervisor, un borrador o un mes bloqueado es justo lo que
  dirección necesita ver.
- **Resumen ejecutivo con IA** (`ExecutiveNarrativeService`): usa
  `Core\Services\AiClient` sobre el `payload_json` ya calculado (nunca sobre
  tickets crudos). El resultado se guarda siempre como borrador editable en
  `reports_commentary` (`is_ai_draft = 1`); nadie lo ve hasta que alguien lo
  revisa y lo vuelve a guardar.

## Sistema de color de las gráficas

Todas las gráficas del módulo (dashboard web, PPTX y modo presentación) siguen el
método de la skill `dataviz`: el color se asigna por el trabajo que hace, nunca por
gusto, y se valida con `scripts/validate_palette.js` antes de usarse.

- **Identidad categórica** (series distintas): orden fijo, nunca se recicla —
  azul `#2a78d6`, naranja `#eb6834`, aqua `#1baf7a` (validados todos-contra-todos
  para 3 series). Usado en la tendencia (Total vs. Cerrados) y en disposiciones de
  correo.
- **Magnitud** (ranking de una sola métrica): un solo tono, nunca arcoíris —
  regional, estado geográfico, categoría, ranking IDS. Estado geográfico y
  ranking IDS se muestran con mayor y menor carga (`est_top`/`est_bottom`,
  `ids_top`/`ids_bottom`): un mapa de carga sirve tanto para "quién tiene más"
  como para "quién tiene menos". Categoría (`cat_top`) trae **todas** las
  categorías registradas en el período, no un top-N — el dashboard web y el
  XLSX muestran la lista completa; el PPTX, con espacio de slide fijo, recorta
  a las 15 de mayor volumen y deja el total real en el subtítulo.
- **Estado fijo** (bien/mal/advertencia, nunca reusado para una serie cualquiera):
  bien `#0ca30c`, warning `#fab219`, crítico `#d03b3b`, info `#2a78d6`. Usado en:
  severidad de reglas de calidad (por barra) y banda del score final de agentes
  (por barra) — ambos con leyenda visible, nunca solo color.
- **Ordinal** (progreso de un pipeline): "Tickets por estado" ya no es un
  donut — es una sola barra apilada en el **orden real del flujo** (Nuevo → ... →
  Cerrado, ver `GlpiTicketsProvider::orderedByStatus()`), coloreada con una rampa
  de un tono, clara → oscura. Posición y color codifican lo mismo: qué tan
  avanzado está el ticket.
- **Ratio contra un límite**: "Control de Envíos" (Cerrados vs. Pendientes) ya no
  es un donut de 2 rebanadas — es un **meter** (barra + track + texto del valor),
  siguiendo la regla de la skill de que un pie de 2 rebanadas siempre debe ser
  otra cosa.

El **modo presentación** (`/reports/{y}/{m}/present`) tiene una identidad
"consola de operaciones" propia, deliberadamente distinta del dashboard (claro):
fondo de tinta oscura, porque se proyecta en una sala a distancia, no se lee de
cerca. Reutiliza el mismo método de color con los pasos oscuros
correspondientes. Ver el comentario al inicio de `Views/present.php` para el
razonamiento completo. Escala tipográfica deliberadamente corta (6 tamaños:
13/15/20/28/38/44px) para que cada salto de tamaño signifique una jerarquía
real, no una variación cosmética.

**El PPTX reutiliza esta misma identidad oscura** (`Services\Export\SlideKit`,
tema "Operations Console"): mismo fondo de tinta, mismos tokens de color y el
mismo lenguaje de "scoreboard" (números separados por regla fina, sin tarjetas
con borde) que la presentación, en vez de una tercera paleta corporativa clara.
Un panel con borde sutil (`cardHeader`) se conserva solo donde varias gráficas
comparten un slide sin affordance de hover — la única concesión al formato
estático que la presentación en vivo no necesita.

## Exportación

- **PPTX**: `Services\Export\SlideKit` reutiliza el color y la tipografía del
  modo presentación (ver arriba). `PptxDeckBuilder` arma un slide por sección
  disponible (12 en total con todas las secciones disponibles): Portada,
  Resumen GLPI, Estados y Territorio, Estado Geográfico (top/bottom), Categoría,
  Ranking IDS (top/bottom), Control de Envíos, Tendencia, Correo, Calidad,
  Desempeño, Conclusiones.
- **XLSX**: `Services\Export\XlsxAnnexBuilder` vuelca los datos crudos de cada
  sección, una hoja por fuente, para quien quiera pivotear los números —
  incluida la lista completa de categorías, sin el recorte del PPTX.
- **Presentación**: `/reports/{y}/{m}/present` es un deck HTML de pantalla
  completa (no depende de PowerPoint), navegable con flechas o espacio.
  Paridad total de datos con el PPTX: resumen ejecutivo (si ya hay un borrador
  guardado en `reports_commentary`), regional + estado geográfico top/bottom,
  ranking IDS top/bottom en su propio slide, categoría (mismo recorte top-15
  del PPTX, con el total real en el subtítulo), control de envíos, y
  comparativas + antigüedad de backlog en tendencia. Top y bottom del mismo
  ranking comparten slide y se distinguen por color (azul = mayor carga,
  warning = menor carga), igual que en el PPTX — el color marca el extremo del
  ranking, no un estado bueno/malo.

## Envío por correo

`POST /reports/{y}/{m}/send` genera el PPTX si hace falta y lo envía con
`Communications\Services\MailerService::sendReport()` a los destinatarios de
`reports_settings.email_recipients`. Cada envío (éxito o falla) queda en
`reports_deliveries`.

## Tablas (prefijo `reports_`)

`reports_snapshots`, `reports_snapshot_versions`, `reports_commentary`,
`reports_settings`, `reports_deliveries`. Ver las migraciones en
`app/Modules/Reports/Database/Migrations/` para el esquema exacto.

## KPIsOperativos: qué cambió

- Rutas `GET`/`POST /kpi/glpi/upload` dadas de baja (404). El resto del módulo
  (consulta de reportes viejos, catálogo IDC, coordinadores) sigue funcionando
  igual.
- `core_modules.name` para `kpis_operativos` cambia a "KPI (histórico)" (migración
  `RenameLegacyKpiModule`), para que el sidebar deje claro que no es el reporte
  vigente.
- `KPIsOperativos\Services\KpisOperativosBridge` (nuevo): única puerta de entrada
  para que otro módulo lea sus catálogos.

## Pendientes conocidos

- No hay entidad/categoría scope configurable en `GlpiTicketsProvider` (a
  diferencia de HelpdeskSupervisor, que sí scopea por `GlpiTicketScope`): el
  informe asume alcance completo de la instancia GLPI. Si en el futuro hace
  falta acotar por entidad, replicar ese patrón aquí.
- `docs/referencia/tt-apps.postman_collection.json` incluye la carpeta `Reports`
  con los endpoints base; agregar ejemplos de payload conforme se use en
  producción.
