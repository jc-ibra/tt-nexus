# Design System

> Inspirado en Shopify Polaris. Funcional, accesible, consistente.  
> Color principal: **#1773C8** (Blue 500).

---

## 1. Filosofía

- **Claridad sobre expresión.** Cada elemento sirve una función. Si no ayuda al usuario a completar una tarea, no está.
- **Predecibilidad.** Mismo componente, mismo comportamiento, siempre.
- **Accesibilidad primero.** WCAG AA como mínimo. Los estados de foco son visibles y nunca se ocultan.
- **Sistema, no colección.** Los tokens son la fuente de verdad. No uses valores hardcoded.

---

## 2. Color

### Paleta principal — Blue

| Token                   | Valor HEX | Uso |
|-------------------------|-----------|-----|
| `--color-blue-50`       | `#EEF5FC` | Fondos de estado informativo, superficies highlight |
| `--color-blue-100`      | `#D5E8F7` | Hover de superficies secundarias |
| `--color-blue-200`      | `#9CCAEE` | Bordes en estado focused light |
| `--color-blue-300`      | `#57A5E0` | Íconos decorativos, indicadores secundarios |
| `--color-blue-400`      | `#2F89D4` | Botón primario hover |
| `--color-blue-500`      | `#1773C8` | **Primario / acción principal** |
| `--color-blue-600`      | `#115EA3` | Botón primario pressed |
| `--color-blue-700`      | `#0D497F` | Textos sobre fondo claro con estado activo |
| `--color-blue-800`      | `#09345A` | Decorativo oscuro, bordes fuertes |
| `--color-blue-900`      | `#061F36` | Fondos de sidebars muy oscuras |

### Neutros

| Token                    | Valor HEX | Uso |
|--------------------------|-----------|-----|
| `--color-neutral-0`      | `#FFFFFF` | Superficie de card, modal |
| `--color-neutral-50`     | `#F6F6F7` | Fondo de página |
| `--color-neutral-100`    | `#F1F1F2` | Fondo de input deshabilitado |
| `--color-neutral-200`    | `#E3E4E5` | Bordes de dividers |
| `--color-neutral-300`    | `#C9CCCF` | Bordes de inputs en reposo |
| `--color-neutral-400`    | `#8C9196` | Placeholder text, iconos inactivos |
| `--color-neutral-500`    | `#6D7175` | Texto auxiliar / muted |
| `--color-neutral-600`    | `#5C6166` | Labels de campos |
| `--color-neutral-700`    | `#44494D` | Texto secundario |
| `--color-neutral-800`    | `#303538` | Texto principal dark |
| `--color-neutral-900`    | `#1A1C1E` | Texto principal / headings |

### Semánticos

| Token                        | Valor HEX | Uso |
|------------------------------|-----------|-----|
| `--color-success-surface`    | `#F1F8F5` | Fondo de banner de éxito |
| `--color-success-default`    | `#008060` | Texto e icono de éxito |
| `--color-success-strong`     | `#00593F` | Borde o acento de éxito fuerte |
| `--color-warning-surface`    | `#FFF5EA` | Fondo de banner de advertencia |
| `--color-warning-default`    | `#B98900` | Texto e icono de advertencia |
| `--color-warning-strong`     | `#8A6500` | Borde o acento warning fuerte |
| `--color-critical-surface`   | `#FFF4F4` | Fondo de banner de error |
| `--color-critical-default`   | `#D72C0D` | Texto e icono de error |
| `--color-critical-strong`    | `#A21A00` | Borde o acento de error fuerte |
| `--color-info-surface`       | `#EEF5FC` | Igual que blue-50 |
| `--color-info-default`       | `#1773C8` | Igual que blue-500 |

### Aliases semánticos de interfaz

```css
:root {
  /* Fondos */
  --bg-page:         var(--color-neutral-50);
  --bg-surface:      var(--color-neutral-0);
  --bg-surface-alt:  var(--color-neutral-100);
  --bg-overlay:      rgba(0, 0, 0, 0.5);

  /* Texto */
  --text-primary:    var(--color-neutral-900);
  --text-secondary:  var(--color-neutral-700);
  --text-muted:      var(--color-neutral-500);
  --text-disabled:   var(--color-neutral-400);
  --text-inverse:    #FFFFFF;
  --text-link:       var(--color-blue-500);
  --text-link-hover: var(--color-blue-600);

  /* Bordes */
  --border-default:  var(--color-neutral-300);
  --border-strong:   var(--color-neutral-400);
  --border-focus:    var(--color-blue-500);
  --border-critical: var(--color-critical-default);

  /* Acciones */
  --action-primary:          var(--color-blue-500);
  --action-primary-hover:    var(--color-blue-400);
  --action-primary-pressed:  var(--color-blue-600);
  --action-primary-disabled: var(--color-neutral-300);
}
```

### Reglas de uso de color

- Máximo **2 colores de acento por pantalla**: el primario (`blue-500`) y un semántico si hay estado.
- El fondo de página siempre es `--bg-page` (`#F6F6F7`). Las cards y modales van sobre `--bg-surface` (`#FFFFFF`).
- Nunca uses `blue-500` directamente como fondo de una superficie grande — solo en botones, badges, y highlights pequeños.
- Los textos sobre azul sólido deben ser blancos. Verifica ratio mínimo 4.5:1.

---

## 3. Tipografía

### Stack

```css
:root {
  --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
               'Helvetica Neue', Arial, sans-serif;
  --font-mono: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
```

> No se usan fuentes de display personalizadas. El sistema confía en las fuentes del sistema para velocidad y consistencia — igual que Polaris.

### Escala tipográfica

| Token               | `font-size` | `line-height` | `font-weight` | Uso |
|---------------------|-------------|----------------|----------------|-----|
| `--text-xs`         | `12px`      | `16px`         | 400            | Metadata, timestamps, captions |
| `--text-sm`         | `13px`      | `20px`         | 400            | Labels de campos, texto de ayuda |
| `--text-base`       | `14px`      | `20px`         | 400            | Cuerpo de texto, párrafos |
| `--text-md`         | `15px`      | `22px`         | 400            | Cuerpo de listas, descripciones |
| `--text-lg`         | `16px`      | `24px`         | 500            | Subtítulos de sección |
| `--text-xl`         | `20px`      | `28px`         | 600            | Títulos de página, headings |
| `--text-2xl`        | `24px`      | `32px`         | 600            | Headings principales, stat numbers |
| `--text-3xl`        | `32px`      | `40px`         | 700            | Hero stats, números grandes |

```css
:root {
  --text-xs:   0.75rem;
  --text-sm:   0.8125rem;
  --text-base: 0.875rem;
  --text-md:   0.9375rem;
  --text-lg:   1rem;
  --text-xl:   1.25rem;
  --text-2xl:  1.5rem;
  --text-3xl:  2rem;

  --leading-tight:  1.25;
  --leading-normal: 1.4286;   /* 20/14 */
  --leading-relaxed: 1.5;

  --weight-regular: 400;
  --weight-medium:  500;
  --weight-semibold: 600;
  --weight-bold:    700;
}
```

### Reglas tipográficas

- El tamaño base de cuerpo es **14px**. Nunca bajes de 12px para texto legible.
- Los `headings` de página usan `--text-xl` (20px), semibold.
- Los `headings` de card/sección usan `--text-lg` (16px), medium.
- Labels de campos van en `--text-sm` (13px), medium, color `--text-secondary`.
- Texto de ayuda / error va en `--text-sm`, color `--text-muted` o `--color-critical-default`.
- Nunca uses `font-weight: 300` — demasiado ligero en pantallas de baja densidad.

---

## 4. Espaciado

Sistema de **base 4px**. Todos los valores de margin, padding y gap deben ser múltiplos de 4.

| Token        | Valor | Uso típico |
|--------------|-------|------------|
| `--space-1`  | `4px`  | Espacio mínimo entre elementos inline |
| `--space-2`  | `8px`  | Padding interno de badges, chips |
| `--space-3`  | `12px` | Padding de inputs compactos |
| `--space-4`  | `16px` | Padding estándar de cards y modales |
| `--space-5`  | `20px` | Gap entre campos de formulario |
| `--space-6`  | `24px` | Sección interna de card |
| `--space-8`  | `32px` | Separación entre secciones de página |
| `--space-10` | `40px` | Padding de página en desktop |
| `--space-12` | `48px` | Header de página |
| `--space-16` | `64px` | Separación entre bloques principales |
| `--space-20` | `80px` | Secciones de landing / hero |

```css
:root {
  --space-1:  0.25rem;
  --space-2:  0.5rem;
  --space-3:  0.75rem;
  --space-4:  1rem;
  --space-5:  1.25rem;
  --space-6:  1.5rem;
  --space-8:  2rem;
  --space-10: 2.5rem;
  --space-12: 3rem;
  --space-16: 4rem;
  --space-20: 5rem;
}
```

---

## 5. Bordes y Radio

```css
:root {
  --radius-sm:   4px;   /* Chips, badges, inputs */
  --radius-md:   8px;   /* Cards, dropdowns, tooltips */
  --radius-lg:   12px;  /* Modales, paneles laterales */
  --radius-xl:   16px;  /* Drawer de mobile, sheet */
  --radius-full: 9999px; /* Botones pill, avatares, tags */

  --border-width-default: 1px;
  --border-width-strong:  2px;  /* Foco, selección activa */
}
```

**Regla:** Las cards usas `--radius-md`. Los modales usan `--radius-lg`. Los inputs usan `--radius-sm`. Nunca mezcles `border-radius` dentro del mismo componente salvo para anidamiento explícito.

---

## 6. Sombras / Elevación

| Token             | Valor CSS | Uso |
|-------------------|-----------|-----|
| `--shadow-xs`     | `0 1px 2px rgba(0,0,0,0.08)` | Hover de row, hover de card |
| `--shadow-sm`     | `0 2px 4px rgba(0,0,0,0.10)` | Card en reposo |
| `--shadow-md`     | `0 4px 12px rgba(0,0,0,0.12)` | Dropdown, popover |
| `--shadow-lg`     | `0 8px 24px rgba(0,0,0,0.14)` | Modal, dialog |
| `--shadow-xl`     | `0 16px 48px rgba(0,0,0,0.16)` | Drawer lateral |
| `--shadow-focus`  | `0 0 0 3px rgba(23,115,200,0.35)` | Estado de foco (ring) |
| `--shadow-focus-critical` | `0 0 0 3px rgba(215,44,13,0.30)` | Foco en campo con error |

```css
:root {
  --shadow-xs:    0 1px 2px rgba(0,0,0,0.08);
  --shadow-sm:    0 2px 4px rgba(0,0,0,0.10);
  --shadow-md:    0 4px 12px rgba(0,0,0,0.12);
  --shadow-lg:    0 8px 24px rgba(0,0,0,0.14);
  --shadow-xl:    0 16px 48px rgba(0,0,0,0.16);
  --shadow-focus: 0 0 0 3px rgba(23,115,200,0.35);
  --shadow-focus-critical: 0 0 0 3px rgba(215,44,13,0.30);
}
```

---

## 7. Íconos

- Usar íconos de **20×20px** como tamaño estándar en interfaces (botones, labels, nav).
- **16×16px** para contextos compactos (badges inline, texto auxiliar).
- **24×24px** para encabezados de sección y navigación lateral.
- Trazo uniforme de 1.5px. Estilo `outline` para acciones y navegación; `filled` solo para estados activos/seleccionados.
- Nunca uses emojis como íconos de interfaz.
- Sistema recomendado: [Lucide](https://lucide.dev/) o [Heroicons](https://heroicons.com/) (consistentes con Polaris en estilo).

---

## 8. Componentes Clave

### 8.1 Botones

**Jerarquía de 4 niveles:**

| Variante    | Cuándo usar |
|-------------|-------------|
| `primary`   | La acción más importante de la página. Máximo 1 por vista. |
| `secondary` | Acciones secundarias que acompañan a `primary`. |
| `tertiary`  | Acciones de baja prioridad, dentro de listas o tablas. |
| `critical`  | Acciones destructivas (eliminar, cancelar con consecuencias). |

```css
/* Botón Primary */
.btn-primary {
  background: var(--action-primary);
  color: #fff;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-sm);
  font-size: var(--text-base);
  font-weight: var(--weight-medium);
  border: none;
  cursor: pointer;
  transition: background 0.15s ease;
}
.btn-primary:hover    { background: var(--action-primary-hover); }
.btn-primary:active   { background: var(--action-primary-pressed); }
.btn-primary:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
.btn-primary:disabled {
  background: var(--action-primary-disabled);
  color: var(--text-disabled);
  cursor: not-allowed;
}

/* Botón Secondary */
.btn-secondary {
  background: var(--bg-surface);
  color: var(--text-primary);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-sm);
  font-size: var(--text-base);
  font-weight: var(--weight-medium);
  border: var(--border-width-default) solid var(--border-default);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.btn-secondary:hover { background: var(--bg-surface-alt); border-color: var(--border-strong); }
.btn-secondary:focus-visible { outline: none; box-shadow: var(--shadow-focus); }

/* Botón Critical */
.btn-critical {
  background: var(--color-critical-default);
  color: #fff;
  /* mismo padding y font que primary */
}
.btn-critical:hover  { background: var(--color-critical-strong); }
.btn-critical:focus-visible { box-shadow: var(--shadow-focus-critical); }
```

**Tamaños de botón:**

| Tamaño   | Padding                        | Font-size         |
|----------|--------------------------------|-------------------|
| `sm`     | `4px 12px`                     | `--text-sm` 13px  |
| `md`     | `8px 16px` *(default)*         | `--text-base` 14px|
| `lg`     | `10px 20px`                    | `--text-lg` 16px  |

---

### 8.2 Inputs y Formularios

```css
.field-label {
  display: block;
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  color: var(--text-secondary);
  margin-bottom: var(--space-1);
}

.input {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  font-size: var(--text-base);
  color: var(--text-primary);
  background: var(--bg-surface);
  border: var(--border-width-default) solid var(--border-default);
  border-radius: var(--radius-sm);
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.input::placeholder { color: var(--text-disabled); }
.input:hover        { border-color: var(--border-strong); }
.input:focus        { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.input.is-error     { border-color: var(--border-critical); }
.input.is-error:focus { box-shadow: var(--shadow-focus-critical); }
.input:disabled     { background: var(--bg-surface-alt); color: var(--text-disabled); cursor: not-allowed; }

.field-help   { font-size: var(--text-sm); color: var(--text-muted); margin-top: var(--space-1); }
.field-error  { font-size: var(--text-sm); color: var(--color-critical-default); margin-top: var(--space-1); }
```

**Regla de formularios:** Cada campo tiene `label → input → [help text | error text]`. Nunca omitas el label (usa `aria-label` si es visualmente oculto).

---

### 8.3 Cards

```css
.card {
  background: var(--bg-surface);
  border: var(--border-width-default) solid var(--color-neutral-200);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.card-header {
  padding: var(--space-4) var(--space-4) 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title {
  font-size: var(--text-lg);
  font-weight: var(--weight-semibold);
  color: var(--text-primary);
}

.card-body {
  padding: var(--space-4);
}

.card-section {
  padding: var(--space-4);
  border-top: var(--border-width-default) solid var(--color-neutral-200);
}
```

---

### 8.4 Badges / Tags

```css
.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 2px var(--space-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--weight-medium);
  line-height: 1;
}

/* Variantes */
.badge-info     { background: var(--color-blue-50);         color: var(--color-blue-700); }
.badge-success  { background: var(--color-success-surface);  color: var(--color-success-strong); }
.badge-warning  { background: var(--color-warning-surface);  color: var(--color-warning-strong); }
.badge-critical { background: var(--color-critical-surface); color: var(--color-critical-strong); }
.badge-neutral  { background: var(--color-neutral-100);      color: var(--color-neutral-700); }
```

---

### 8.5 Banners / Alerts

Estructura: `[icon] [título] [descripción] [acción opcional]`

```css
.banner {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-md);
  border: var(--border-width-default) solid transparent;
}
.banner-info     { background: var(--color-info-surface);     border-color: var(--color-blue-200);  color: var(--color-blue-800); }
.banner-success  { background: var(--color-success-surface);  border-color: #B8E0D4;               color: var(--color-success-strong); }
.banner-warning  { background: var(--color-warning-surface);  border-color: #FFDF99;               color: var(--color-warning-strong); }
.banner-critical { background: var(--color-critical-surface); border-color: #FFBFB6;               color: var(--color-critical-strong); }
```

---

### 8.6 Navegación lateral (Sidebar)

```css
.nav-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  font-size: var(--text-base);
  font-weight: var(--weight-regular);
  color: var(--text-secondary);
  text-decoration: none;
  cursor: pointer;
  transition: background 0.12s ease, color 0.12s ease;
}
.nav-item:hover           { background: var(--color-neutral-100); color: var(--text-primary); }
.nav-item.is-active       { background: var(--color-blue-50); color: var(--color-blue-700); font-weight: var(--weight-medium); }
.nav-item.is-active .icon { color: var(--color-blue-500); }
```

---

### 8.7 Tablas

```css
.table { width: 100%; border-collapse: collapse; font-size: var(--text-base); }

.table th {
  text-align: left;
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  color: var(--text-muted);
  background: var(--bg-surface-alt);
  border-bottom: var(--border-width-default) solid var(--border-default);
}

.table td {
  padding: var(--space-3) var(--space-4);
  color: var(--text-primary);
  border-bottom: var(--border-width-default) solid var(--color-neutral-200);
  vertical-align: middle;
}

.table tr:hover td { background: var(--color-neutral-50); }
.table tr:last-child td { border-bottom: none; }
```

---

## 9. Layout y Grid

### Contenedores

```css
.container-sm   { max-width: 640px;  margin: 0 auto; padding: 0 var(--space-4); }
.container-md   { max-width: 768px;  margin: 0 auto; padding: 0 var(--space-4); }
.container-lg   { max-width: 1024px; margin: 0 auto; padding: 0 var(--space-6); }
.container-xl   { max-width: 1280px; margin: 0 auto; padding: 0 var(--space-8); }
.container-full { width: 100%;       padding: 0 var(--space-8); }
```

### Layout de aplicación (shell)

```
┌─────────────────────────────────────────────────────┐
│ Top Nav (64px)                                      │
├──────────┬──────────────────────────────────────────┤
│ Sidebar  │  Main content area                       │
│ (240px)  │  padding: 24px                           │
│          │                                          │
│          │  Page header (48px)                      │
│          │  ─────────────────                       │
│          │  Content grid                            │
│          │                                          │
└──────────┴──────────────────────────────────────────┘
```

```css
.app-shell {
  display: grid;
  grid-template-rows: 64px 1fr;
  grid-template-columns: 240px 1fr;
  min-height: 100vh;
}
.app-topnav  { grid-column: 1 / -1; }
.app-sidebar { grid-row: 2; }
.app-main    { grid-row: 2; padding: var(--space-6); overflow-y: auto; }
```

### Grid de cards

```css
.grid-1 { display: grid; gap: var(--space-4); }
.grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4); }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4); }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-4); }

/* Responsive */
@media (max-width: 1024px) { .grid-4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px)  { .grid-3, .grid-4 { grid-template-columns: 1fr; } }
@media (max-width: 640px)  { .grid-2 { grid-template-columns: 1fr; } }
```

---

## 10. Movimiento y Transiciones

Todas las animaciones sirven un propósito funcional. No hay animaciones decorativas.

```css
:root {
  --duration-fast:    100ms;  /* Hover estados, badges */
  --duration-base:    150ms;  /* Botones, inputs, links */
  --duration-moderate: 200ms; /* Dropdowns, tooltips */
  --duration-slow:    300ms;  /* Modales, drawers */
  --duration-slower:  400ms;  /* Transiciones de página */

  --ease-default: cubic-bezier(0.4, 0, 0.2, 1);  /* Material ease */
  --ease-in:      cubic-bezier(0.4, 0, 1, 1);
  --ease-out:     cubic-bezier(0, 0, 0.2, 1);
  --ease-spring:  cubic-bezier(0.34, 1.56, 0.64, 1); /* Para pop-in */
}
```

**Reglas:**
- Hover en botones e inputs: `--duration-base` + `--ease-default`.
- Aparición de dropdowns/popovers: `--duration-moderate` + `--ease-out`.
- Modales: fade in `opacity` + scale pequeño (`0.96 → 1`), `--duration-slow`.
- Drawers: slide desde el borde, `--duration-slow` + `--ease-out`.
- `prefers-reduced-motion`: envuelve todas las animaciones en `@media (prefers-reduced-motion: no-preference)`.

```css
@media (prefers-reduced-motion: no-preference) {
  .btn-primary { transition: background var(--duration-base) var(--ease-default); }
}
```

---

## 11. Accesibilidad

### Requisitos mínimos

| Requisito | Detalle |
|-----------|---------|
| Contraste de texto normal | Mínimo **4.5:1** (WCAG AA) |
| Contraste de texto grande (≥18px o ≥14px bold) | Mínimo **3:1** |
| Contraste de componentes UI | Mínimo **3:1** para bordes de inputs, íconos funcionales |
| Foco visible | Siempre visible, nunca `outline: none` sin reemplazo. Usar `--shadow-focus` |
| Touch target | Mínimo **44×44px** en mobile |
| Aria labels | Todos los íconos-botón tienen `aria-label`. Inputs tienen `<label>` asociado |
| Gestión de foco en modales | Foco entra al modal al abrirse, regresa al trigger al cerrarse |

### Contrastes verificados

| Combinación | Ratio | ¿Pasa AA? |
|-------------|-------|-----------|
| `#1773C8` sobre `#FFFFFF` | ~4.3:1 | ⚠️ Texto normal borderline — usar bold o ≥16px para links |
| `#FFFFFF` sobre `#1773C8` | ~4.3:1 | ✅ Texto en botón primary (semibold, pasa AA large text) |
| `#1A1C1E` sobre `#F6F6F7` | 16.5:1 | ✅ Texto sobre fondo de página |
| `#6D7175` sobre `#FFFFFF` | 4.6:1 | ✅ Texto muted sobre card |
| `#D72C0D` sobre `#FFFFFF` | 5.3:1 | ✅ Error text |
| `#1773C8` sobre `#EEF5FC` | 3.0:1 | ✅ Íconos y texto grande (≥18px) |
| `#0D497F` sobre `#FFFFFF` | 8.1:1 | ✅ Alternativa AAA para texto normal |

> **Nota de accesibilidad:** `#1773C8` está en el límite para texto normal de 14px regular. Para links en cuerpo de texto usa `font-weight: 500` o el tono más oscuro `--color-blue-700` (`#0D497F`). Los botones primarios con texto blanco en semibold pasan AA en categoría "large text" (texto UI ≥14px bold cuenta como large text según WCAG).

---

## 12. Tonos de voz y copy

- **Claro y directo.** "Guardar cambios" no "Aplicar modificaciones".
- **Accionable.** Los labels de botón son verbos. "Crear producto", "Eliminar", "Ver detalles".
- **Los errores explican qué hacer.** "El correo no es válido. Usa el formato correo@dominio.com".
- **Sin jerga técnica** en mensajes de usuario. Los logs técnicos van en consola, no en UI.
- **Mayúsculas de título** solo en navegación principal y headings de página. El resto en sentence case.

---

## 13. Variables CSS — referencia completa

```css
:root {
  /* Blue */
  --color-blue-50:  #EEF5FC;
  --color-blue-100: #D5E8F7;
  --color-blue-200: #9CCAEE;
  --color-blue-300: #57A5E0;
  --color-blue-400: #2F89D4;
  --color-blue-500: #1773C8;
  --color-blue-600: #115EA3;
  --color-blue-700: #0D497F;
  --color-blue-800: #09345A;
  --color-blue-900: #061F36;

  /* Neutrals */
  --color-neutral-0:   #FFFFFF;
  --color-neutral-50:  #F6F6F7;
  --color-neutral-100: #F1F1F2;
  --color-neutral-200: #E3E4E5;
  --color-neutral-300: #C9CCCF;
  --color-neutral-400: #8C9196;
  --color-neutral-500: #6D7175;
  --color-neutral-600: #5C6166;
  --color-neutral-700: #44494D;
  --color-neutral-800: #303538;
  --color-neutral-900: #1A1C1E;

  /* Semantic */
  --color-success-surface: #F1F8F5;
  --color-success-default: #008060;
  --color-success-strong:  #00593F;
  --color-warning-surface: #FFF5EA;
  --color-warning-default: #B98900;
  --color-warning-strong:  #8A6500;
  --color-critical-surface: #FFF4F4;
  --color-critical-default: #D72C0D;
  --color-critical-strong:  #A21A00;
  --color-info-surface: #EEF5FC;
  --color-info-default: #1773C8;

  /* Aliases */
  --bg-page:        #F6F6F7;
  --bg-surface:     #FFFFFF;
  --bg-surface-alt: #F1F1F2;
  --bg-overlay:     rgba(0,0,0,0.5);
  --text-primary:   #1A1C1E;
  --text-secondary: #44494D;
  --text-muted:     #6D7175;
  --text-disabled:  #8C9196;
  --text-inverse:   #FFFFFF;
  --text-link:      #1773C8;
  --text-link-hover: #115EA3;
  --border-default: #C9CCCF;
  --border-strong:  #8C9196;
  --border-focus:   #1773C8;
  --border-critical: #D72C0D;
  --action-primary:          #1773C8;
  --action-primary-hover:    #2F89D4;
  --action-primary-pressed:  #115EA3;
  --action-primary-disabled: #C9CCCF;

  /* Typography */
  --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  --font-mono: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  --text-xs:   0.75rem;
  --text-sm:   0.8125rem;
  --text-base: 0.875rem;
  --text-md:   0.9375rem;
  --text-lg:   1rem;
  --text-xl:   1.25rem;
  --text-2xl:  1.5rem;
  --text-3xl:  2rem;
  --weight-regular:  400;
  --weight-medium:   500;
  --weight-semibold: 600;
  --weight-bold:     700;

  /* Spacing */
  --space-1:  0.25rem;
  --space-2:  0.5rem;
  --space-3:  0.75rem;
  --space-4:  1rem;
  --space-5:  1.25rem;
  --space-6:  1.5rem;
  --space-8:  2rem;
  --space-10: 2.5rem;
  --space-12: 3rem;
  --space-16: 4rem;
  --space-20: 5rem;

  /* Radii */
  --radius-sm:   4px;
  --radius-md:   8px;
  --radius-lg:   12px;
  --radius-xl:   16px;
  --radius-full: 9999px;

  /* Shadows */
  --shadow-xs:    0 1px 2px rgba(0,0,0,0.08);
  --shadow-sm:    0 2px 4px rgba(0,0,0,0.10);
  --shadow-md:    0 4px 12px rgba(0,0,0,0.12);
  --shadow-lg:    0 8px 24px rgba(0,0,0,0.14);
  --shadow-xl:    0 16px 48px rgba(0,0,0,0.16);
  --shadow-focus: 0 0 0 3px rgba(23,115,200,0.35);
  --shadow-focus-critical: 0 0 0 3px rgba(215,44,13,0.30);

  /* Motion */
  --duration-fast:     100ms;
  --duration-base:     150ms;
  --duration-moderate: 200ms;
  --duration-slow:     300ms;
  --duration-slower:   400ms;
  --ease-default: cubic-bezier(0.4, 0, 0.2, 1);
  --ease-out:     cubic-bezier(0, 0, 0.2, 1);
  --ease-spring:  cubic-bezier(0.34, 1.56, 0.64, 1);
}
```

---

## 14. Qué NO hacer

- ❌ Valores hardcoded en CSS. Siempre tokens.
- ❌ Más de 1 botón `primary` por vista.
- ❌ `outline: none` sin `box-shadow` de reemplazo.
- ❌ Sombras decorativas donde el borde es suficiente.
- ❌ Gradientes en fondos de página o cards.
- ❌ Paleta de colores que no proviene de los tokens.
- ❌ Font-size menor de 12px en interfaz real.
- ❌ Animaciones en `prefers-reduced-motion`.
- ❌ Cards sin suficiente breathing room — mínimo `--space-4` de padding.
- ❌ Texto de error en color rojo puro (`#FF0000`) — usa `--color-critical-default`.
