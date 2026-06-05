# Archivos de referencia — KPIs Operativos / GLPI Tickets

Estos archivos provienen del pipeline original Python+Node (`kpi_operativo/`) y se conservan como **referencia de implementación** para el puerto a CodeIgniter. No se ejecutan desde la app.

| Archivo | Uso |
|---|---|
| `generar_pptx.py` | Spec viva del cálculo de KPIs. Guía para `GlpiKpiCalculator`. |
| `generar_pptx.js` | Coordenadas, paleta y layout de los 10 slides. Guía para el render con PHPPresentation. |
| `kpi_data.example.json` | Snapshot de salida real. Fixture para tests del calculator (debe coincidir 1:1). |
| `samples/glpi_mayo.csv` | Export real de GLPI (mayo 2026). Sample para pruebas manuales del parser. |

## Contrato del snapshot (`kpi_data.json`)

| Campo | Tipo | Descripción |
|---|---|---|
| `total`, `cerrados`, `en_curso`, `tasa_cierre` | int | Top-line ticket counts |
| `sla_pct`, `prom_h` | float | SLA % a 24h, horas promedio de resolución |
| `sin_reg`, `sin_idc` | int | Tickets sin regional / sin IDC asignado |
| `reg_top`, `est_top`, `idc_top`, `idc_bottom`, `cat_top`, `proy_top` | `[string, int][]` | Rankings ordenados desc |
| `estados_ticket` | `[string, int][]` | Breakdown completo por estado |
| `env_total`, `env_cerr`, `env_pend`, `env_pct` | int | Sub-pipeline "Control de Envíos" |
| `coord_tickets` | `{ [zona]: int }` | Tickets por zona |
| `coord_info` | `{ [zona]: { coord, gte } }` | Catálogo coordinador/gerente (ahora en BD: `glpi_coordinators`) |

## Reglas de cálculo críticas

- **Cerrados** = `Estado IN ('Cerrado', 'Resuelto')`.
- **SLA**: solo sobre cerrados con `fecha_cierre >= fecha_apertura`. `prom_h` = horas promedio de resolución.
- **Envíos**: filtro por substring `"ENVI"` en `categoria` (case-insensitive), NO por categoría exacta.
- **IDC**: excluye vacíos y `"SIN ASIGNAR"` del ranking; los cuenta en `sin_idc`.
- **Match de zona** contra `glpi_coordinators`: normalizado (uppercase, sin espacios). Si no hay match, `coord` = nombre de zona, `gte` = `—`.
- **Fechas**: aceptar `dd/mm/yy HH:MM[:SS]` y `YYYY-MM-DD HH:MM[:SS]` con fallback genérico.
