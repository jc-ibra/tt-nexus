# Fase 3: Módulo KPIs de Agentes

> **Documento:** Especificación técnica para Claude Code  
> **Módulo:** AgentKpis  
> **Clave:** `agent_kpis`  
> **Ruta base:** `/agent-kpis`  
> **Prefijo de tablas:** `agent_kpis_`  
> **Namespace:** `App\Modules\AgentKpis`  
> **UI display:** "Evaluación de Agentes"  
> **Fase:** 3 de 3  
> **Prerrequisitos:** Fase 1 completada (HelpdeskSupervisor funcional con datos). Leer `NEXUS.md`, `CONEXIONES.md` y el documento adjunto `sistema_evaluacion_mesa_n1_v2.md`.

---

## 1. Objetivo

Implementar el sistema de evaluación de desempeño mensual para los 5 agentes de Mesa de Ayuda N1, consumiendo los datos de auditoría de HelpdeskSupervisor (Fase 1) para los KPIs cuantitativos, y proporcionando una interfaz para la evaluación cualitativa (rúbrica) que aplica el gerente de mesa.

La evaluación mensual combina un componente cuantitativo (80%) y un componente cualitativo (20%), con una regla de bloqueo por escalaciones (KPI 5).

---

## 2. Relación con HelpdeskSupervisor

Este módulo **no duplica** la auditoría de GLPI. Consume los datos ya calculados:

| Dato | Fuente |
|---|---|
| KPI 1 (seguimiento activo) | `helpdesk_supervisor_deviations` con `kpi_mapping = 'KPI-1'` |
| KPI 2 (clasificación correcta) | `helpdesk_supervisor_deviations` con `kpi_mapping = 'KPI-2'` |
| KPI 3 (completitud de campos) | `helpdesk_supervisor_deviations` con `kpi_mapping = 'KPI-3'` |
| KPI 4 (tickets abandonados) | `helpdesk_supervisor_deviations` con `kpi_mapping = 'KPI-4'` |
| KPI 5 (escalaciones) | `helpdesk_supervisor_escalations` |
| Total de tickets por agente/período | `helpdesk_supervisor_audit_runs` + conteo de tickets únicos en deviations |

La lectura es cross-module vía servicio (no acceso directo a tablas de otro módulo).

---

## 3. Estructura del módulo

```
app/Modules/AgentKpis/
  Config/
  Controllers/
    Api/
      AgentKpisApiController.php
  Database/
    Migrations/
    Seeders/
      AgentKpisModuleSeeder.php
  Models/
    MonthlyEvaluation.php
    QualitativeScore.php
    KpiSnapshot.php
  Services/
    KpiCalculationService.php
    QualitativeEvaluationService.php
    EvaluationReportService.php
    HelpdeskSupervisorBridge.php   # Servicio para leer datos de HelpdeskSupervisor
  Views/
    dashboard.php
    evaluation_detail.php
    qualitative_form.php
    history.php
    agent_history.php
    settings.php
  Routes.php
```

---

## 4. Tablas

### 4.1 `agent_kpis_monthly_evaluations`

Evaluación mensual consolidada por agente.

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| nexus_user_id | INT UNSIGNED | Usuario en Nexus |
| glpi_user_id | INT UNSIGNED | Usuario en GLPI |
| agent_name | VARCHAR(150) | Nombre (snapshot) |
| period_year | SMALLINT UNSIGNED | Año |
| period_month | TINYINT UNSIGNED | Mes (1-12) |
| audit_run_id | INT UNSIGNED NULL | Run de auditoría del que se tomaron los datos cuantitativos |
| total_tickets | INT UNSIGNED DEFAULT 0 | Total de tickets del agente en el período |
| kpi1_value | DECIMAL(5,2) NULL | Porcentaje de seguimiento activo |
| kpi1_status | ENUM('cumple','parcial','no_cumple') NULL | |
| kpi2_value | DECIMAL(5,2) NULL | Porcentaje sin reclasificación |
| kpi2_status | ENUM('cumple','parcial','no_cumple') NULL | |
| kpi3_value | DECIMAL(5,2) NULL | Porcentaje de campos completos |
| kpi3_status | ENUM('cumple','parcial','no_cumple') NULL | |
| kpi4_value | DECIMAL(5,2) NULL | Porcentaje de tickets abandonados |
| kpi4_status | ENUM('cumple','parcial','no_cumple') NULL | |
| kpi5_escalations_count | TINYINT UNSIGNED DEFAULT 0 | Conteo de escalaciones válidas |
| kpi5_status | ENUM('cumple','parcial','no_cumple') NULL | |
| kpis_met_count | TINYINT UNSIGNED DEFAULT 0 | Cantidad de KPIs que cumplen (0-5) |
| quantitative_level | DECIMAL(5,2) NULL | Nivel de cumplimiento cuantitativo (0, 50, 75, 100) |
| quantitative_score | DECIMAL(5,2) NULL | Puntaje cuantitativo (level x 0.80) |
| qualitative_score_raw | DECIMAL(3,2) NULL | Puntaje rúbrica (0.00 a 4.00) |
| qualitative_score | DECIMAL(5,2) NULL | Puntaje cualitativo ((raw/4.0) x 0.20) |
| is_blocked | TINYINT(1) DEFAULT 0 | Si KPI 5 >= 3 escalaciones |
| final_score | DECIMAL(5,2) NULL | Puntaje total (quant + qual), o NULL si bloqueada |
| final_status | ENUM('blocked','evaluated','pending_qualitative','draft') DEFAULT 'draft' | |
| evaluated_by_user_id | INT UNSIGNED NULL | Supervisor que finalizó la evaluación |
| evaluated_at | DATETIME NULL | Fecha/hora de finalización |
| agent_comments | TEXT NULL | Comentarios del agente (derecho de réplica) |
| supervisor_notes | TEXT NULL | Notas del supervisor |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Índice unique: `(nexus_user_id, period_year, period_month)`.

### 4.2 `agent_kpis_qualitative_scores`

Desglose de la rúbrica cualitativa por competencia.

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| evaluation_id | INT UNSIGNED FK | Referencia a `agent_kpis_monthly_evaluations` |
| competency_key | VARCHAR(30) | Clave de la competencia |
| competency_name | VARCHAR(100) | Nombre de la competencia |
| weight | DECIMAL(4,2) | Peso (ej. 0.20 para 20%) |
| score | TINYINT UNSIGNED NULL | Puntos (1-4) |
| evidence | TEXT NULL | Evidencia documentada |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Las 8 competencias y sus pesos son fijos (hardcoded o en config):

| Key | Nombre | Peso |
|---|---|---|
| `phone_service` | Atención telefónica | 0.20 |
| `first_contact` | Resolución y contención en primer contacto | 0.18 |
| `initiative` | Iniciativa | 0.14 |
| `responsibility` | Responsabilidad | 0.13 |
| `communication` | Buena comunicación | 0.12 |
| `technical_knowledge` | Conocimientos técnicos | 0.10 |
| `teamwork` | Trabajo en equipo | 0.08 |
| `flexibility` | Flexibilidad | 0.05 |

### 4.3 `agent_kpis_kpi_snapshots`

Snapshot de los datos que alimentaron cada KPI, para trazabilidad (opcional pero recomendado).

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| evaluation_id | INT UNSIGNED FK | |
| kpi_number | TINYINT UNSIGNED | 1-5 |
| total_tickets_evaluated | INT UNSIGNED | Denominador de la fórmula |
| tickets_meeting_criteria | INT UNSIGNED | Numerador |
| calculated_value | DECIMAL(5,2) | Resultado del cálculo |
| threshold_met | VARCHAR(20) | 'cumple', 'parcial', 'no_cumple' |
| detail_json | JSON NULL | Detalle (lista de ticket IDs que fallan, para drill-down) |
| created_at | DATETIME | |

---

## 5. Fórmulas de cálculo (KpiCalculationService)

### 5.1 KPIs individuales

**KPI 1 - Seguimiento activo hasta cierre:**
```
valor = (total_tickets - tickets_con_desviacion_KPI1) / total_tickets x 100

>= 90%  -> cumple
75-89%  -> parcial
< 75%   -> no_cumple
```

Donde `tickets_con_desviacion_KPI1` es el conteo de tickets únicos con al menos una desviación de `kpi_mapping = 'KPI-1'` en el run.

**KPI 2 - Clasificación correcta:**
```
valor = (total_tickets - tickets_con_desviacion_KPI2) / total_tickets x 100

>= 92%  -> cumple
80-91%  -> parcial
< 80%   -> no_cumple
```

**KPI 3 - Completitud de campos:**
```
valor = (total_tickets - tickets_con_desviacion_KPI3) / total_tickets x 100

>= 95%  -> cumple
85-94%  -> parcial
< 85%   -> no_cumple
```

**KPI 4 - Tickets abandonados:**
```
valor = tickets_con_desviacion_KPI4 / total_tickets_abiertos x 100

<= 5%   -> cumple
6-10%   -> parcial
> 10%   -> no_cumple
```

Nota: el denominador de KPI 4 es "tickets abiertos" (no resueltos/cerrados al corte), no el total del período. Verificar con los datos de la auditoría.

**KPI 5 - Escalaciones:**
```
valor = conteo de escalaciones válidas del agente en el mes
        (tabla helpdesk_supervisor_escalations, is_valid = 1)

0          -> cumple
1-2        -> parcial
>= 3       -> no_cumple + BLOQUEO
```

### 5.2 Nivel cuantitativo

Contar cuántos de los 5 KPIs tienen status `cumple`:

| KPIs que cumplen | Nivel |
|---|---|
| 0-2 | 0% (no cumple) |
| 3 | 50% |
| 4 | 75% |
| 5 | 100% |

`quantitative_score = quantitative_level x 0.80`

### 5.3 Puntaje cualitativo

```
qualitative_score_raw = SUM(score_i x weight_i) para i = 1..8
    donde score_i es el puntaje (1-4) y weight_i es el peso

qualitative_score = (qualitative_score_raw / 4.0) x 0.20 x 100
```

El máximo de `qualitative_score_raw` es 4.0 (si todo es Sobresaliente).

### 5.4 Puntaje final

```
SI kpi5_escalations_count >= 3:
    is_blocked = 1
    final_score = NULL
    final_status = 'blocked'

SI NO:
    final_score = quantitative_score + qualitative_score
    final_status = 'evaluated'
```

### 5.5 Controles de estado

| Status | Significado |
|---|---|
| `draft` | Evaluación creada, datos cuantitativos calculados, pendiente lo cualitativo |
| `pending_qualitative` | Cuantitativo listo, falta la rúbrica |
| `evaluated` | Evaluación completa (cuantitativo + cualitativo) |
| `blocked` | Bloqueada por KPI 5 (>= 3 escalaciones) |

---

## 6. Servicio `HelpdeskSupervisorBridge`

Servicio que abstrae la lectura de datos del módulo HelpdeskSupervisor:

```php
class HelpdeskSupervisorBridge
{
    /** Obtener el último audit_run completado para un período */
    public function getLatestRun(int $year, int $month): ?array;

    /** Contar tickets únicos auditados por agente en un run */
    public function getTicketCountByAgent(int $auditRunId, int $glpiUserId): int;

    /** Contar tickets con desviaciones de un KPI específico */
    public function getDeviationCountByKpi(int $auditRunId, int $glpiUserId, string $kpiMapping): int;

    /** Obtener IDs de tickets que fallan un KPI (para snapshot) */
    public function getFailingTicketIds(int $auditRunId, int $glpiUserId, string $kpiMapping): array;

    /** Contar escalaciones válidas del agente en un mes */
    public function getEscalationCount(int $glpiUserId, int $year, int $month): int;

    /** Obtener lista de agentes mapeados (con glpi_user_id) */
    public function getMappedAgents(): array;

    /** Contar tickets abiertos (no resueltos/cerrados) del agente al corte */
    public function getOpenTicketCount(int $auditRunId, int $glpiUserId): int;
}
```

Este servicio lee las tablas `helpdesk_supervisor_*` y `users` (por `glpi_user_id`). No accede directamente a GLPI; eso ya lo hizo la auditoría.

---

## 7. Flujo de evaluación mensual

### Paso 1: generar evaluaciones cuantitativas

El supervisor entra al módulo, selecciona el mes y presiona **"Generar evaluaciones"**. El sistema:

1. Verifica que exista al menos un `audit_run` completado para ese mes en HelpdeskSupervisor. Si no, muestra error indicando que debe correr la auditoría primero.
2. Para cada agente mapeado:
   a. Calcula KPIs 1-4 usando los datos del run.
   b. Calcula KPI 5 desde las escalaciones.
   c. Determina cuántos cumplen y el nivel cuantitativo.
   d. Verifica regla de bloqueo.
   e. Crea o actualiza el registro en `agent_kpis_monthly_evaluations` con status `draft`.
   f. Guarda snapshots en `agent_kpis_kpi_snapshots`.

### Paso 2: aplicar rúbrica cualitativa

El supervisor accede al detalle de cada agente y completa la rúbrica:

- Ve las 8 competencias con sus descriptores (niveles 1-4).
- Asigna puntaje a cada una.
- Opcionalmente documenta evidencia.
- Al guardar, el sistema calcula el puntaje cualitativo y el puntaje final.
- Status pasa a `evaluated` (o `blocked` si KPI 5 lo bloquea).

### Paso 3: derecho de réplica (opcional)

Si los agentes tienen acceso al módulo (o a una vista limitada), pueden ver su evaluación y dejar comentarios en `agent_comments`. Esto es opcional y puede implementarse después.

### Paso 4: histórico

Todas las evaluaciones se conservan. El supervisor puede ver la evolución de cada agente mes a mes.

---

## 8. Rutas

### Web

```
GET  /agent-kpis                                      -> Dashboard mensual
POST /agent-kpis/generate                              -> Generar evaluaciones del mes
GET  /agent-kpis/evaluations/{id}                      -> Detalle de evaluación
GET  /agent-kpis/evaluations/{id}/qualitative          -> Formulario de rúbrica
POST /agent-kpis/evaluations/{id}/qualitative          -> Guardar rúbrica
POST /agent-kpis/evaluations/{id}/finalize             -> Finalizar evaluación
GET  /agent-kpis/agents/{nexusUserId}/history          -> Historial del agente
GET  /agent-kpis/history                               -> Historial general
GET  /agent-kpis/settings                              -> Configuración
POST /agent-kpis/settings                              -> Guardar configuración
```

### API

Mismas rutas bajo `/api/v1/agent-kpis/`. JSON envelope estándar.

Filtros: `AuthFilter` + `ModuleAccessFilter` con key `agent_kpis`.

---

## 9. Pantallas

### 9.1 Dashboard (`/agent-kpis`)

**Selector de período:** mes y año (default: mes en curso).

**Botón "Generar evaluaciones"** (si no hay evaluaciones del mes) o **"Recalcular"** (si ya las hay).

**Tabla de evaluaciones del mes:**

| Agente | Tickets | KPI 1 | KPI 2 | KPI 3 | KPI 4 | KPI 5 | Cumplidos | Cuant. | Cual. | Final | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Nombre | 45 | 92% C | 88% P | 96% C | 3% C | 0 C | 4/5 | 60% | 15% | 75% | Evaluado |

Donde C = Cumple (verde), P = Parcial (amarillo), N = No cumple (rojo). Status "Bloqueada" en rojo si KPI 5 >= 3.

Link al detalle de cada evaluación.

### 9.2 Detalle de evaluación (`/evaluations/{id}`)

**Sección KPIs cuantitativos:**

Por cada KPI:
- Nombre y descripción breve.
- Valor calculado (porcentaje o conteo).
- Status (badge de color).
- Meta para cumplir.
- Drill-down: lista de tickets que fallaron (desde el snapshot).

**Sección rúbrica cualitativa:**
- Si ya está capturada: tabla con las 8 competencias, puntaje, peso, puntaje ponderado.
- Si no: botón "Completar rúbrica".

**Sección puntaje final:**
- Barra o indicador visual del puntaje total.
- Desglose: cuantitativo (X%) + cualitativo (Y%) = total (Z%).
- Si bloqueada: mensaje prominente.

**Sección notas:**
- Notas del supervisor (editable).
- Comentarios del agente (si hay, solo lectura para el supervisor).

### 9.3 Formulario de rúbrica (`/evaluations/{id}/qualitative`)

8 secciones, una por competencia:

- Nombre de la competencia y peso.
- Descriptores de los 4 niveles (texto del documento de evaluación).
- Selector de puntaje (1, 2, 3, 4) con radio buttons o similar.
- Campo de evidencia (textarea).

Al final:
- Preview del puntaje cualitativo calculado.
- Botón "Guardar rúbrica".

Default si no hay evidencia: "Cumple" (3), según las reglas del documento.

### 9.4 Historial del agente (`/agents/{id}/history`)

Tabla con evaluaciones mes a mes:

| Mes | KPIs cumplidos | Cuant. | Cual. | Final | Status |
|---|---|---|---|---|---|
| Junio 2026 | 4/5 | 60% | 15% | 75% | Evaluado |
| Mayo 2026 | 3/5 | 40% | 16% | 56% | Evaluado |

Gráfica de tendencia (línea) del puntaje final a lo largo de los meses.

### 9.5 Historial general (`/history`)

Vista comparativa de todos los agentes por mes, con filtro de rango de meses. Permite ver tendencias del equipo completo.

---

## 10. Seeder

`AgentKpisModuleSeeder`:

1. Insertar registro en `modules` con key `agent_kpis`, name "Evaluación de Agentes", route_base `agent-kpis`, is_active 1.
2. Asignar al rol SuperAdmin en `role_module`.

---

## 11. Sidebar

Agregar entrada "Evaluación de Agentes" en la navegación, debajo de "Supervisor de Mesa" (agrupación lógica). Ícono sugerido: `bar-chart` o `award`.

---

## 12. Consideraciones

- **Recalculación:** si el supervisor corre una nueva auditoría en HelpdeskSupervisor y luego recalcula KPIs, los valores se actualizan. El `audit_run_id` referenciado cambia al más reciente.
- **Evaluaciones bloqueadas:** una evaluación bloqueada no tiene puntaje final. La UI lo muestra de forma clara (sin intentar calcular un porcentaje).
- **Derecho de réplica:** el documento de evaluación lo menciona. Para la primera versión, puede ser un campo de texto que el supervisor llena con los comentarios del agente. Si más adelante los agentes tienen acceso a una vista de su evaluación, pueden capturarlo directamente.
- **Período de medición:** el período es mensual natural (día 1 al último día del mes). La auditoría de HelpdeskSupervisor debe correr con esas fechas para que los datos coincidan.
- **Competencias y pesos:** están hardcoded según el documento de evaluación. Si cambian, se actualiza el código o se mueven a configuración. Para la primera versión, hardcoded es suficiente dado que son 5 agentes y el sistema es nuevo.
- **Exportación:** la evaluación debería poder exportarse a Excel o PDF para archivo. Esto es un nice-to-have que puede agregarse después, reutilizando PhpSpreadsheet (Fase 2) o la generación de PDF del skill.

---

## 13. Entregables de la Fase 3

- [ ] Todas las migraciones del módulo AgentKpis (3 tablas).
- [ ] Seeder del módulo.
- [ ] Registro del namespace en `Autoload.php`.
- [ ] `HelpdeskSupervisorBridge` (servicio de lectura cross-module).
- [ ] `KpiCalculationService` con las 5 fórmulas y la lógica de niveles/bloqueo.
- [ ] `QualitativeEvaluationService` para la rúbrica.
- [ ] Controller y vistas (dashboard, detalle, rúbrica, historial).
- [ ] Rutas web y API.
- [ ] Entrada en sidebar.
- [ ] Agregar seeder a `setup.sh` y `public/setup.php`.
- [ ] Verificar con `php spark db:verify-schema`.
- [ ] Endpoints API en Postman collection.

---

## 14. Resumen de las 3 fases

| Fase | Módulo | Qué hace | Depende de |
|---|---|---|---|
| 1 | HelpdeskSupervisor | Audita tickets de GLPI contra las reglas del manual. Detecta desviaciones por agente. Registra escalaciones. | Conexión a GLPI (existente) |
| 2 | HelpdeskSupervisor (ext.) | Genera correos con IA (Haiku) sobre desviaciones, adjunta Excel, envía por SMTP. | Fase 1 + Claude API + MailerService |
| 3 | AgentKpis | Calcula KPIs mensuales, rúbrica cualitativa, puntaje final, historial de evaluaciones. | Fase 1 (consume sus datos) |

Los tres trabajan juntos pero son incrementales: cada fase entrega valor por sí sola.

---

*Fin de la Fase 3.*
