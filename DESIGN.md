# Design System

> Inspirado en Shopify Polaris. Funcional, accesible, consistente.  
> Color principal: **#1773C8** (Blue 500).

---

## 1. Filosofía

- **Claridad sobre expresión.** Cada elemento sirve una función. Si no ayuda al usuario a completar una tarea, no está.
- **Predecibilidad.** Mismo componente, mismo comportamiento, siempre.
- **Accesibilidad primero.** WCAG AA como mínimo. Los estados de foco son visibles y nunca se ocultan.
- **Sistema, no colección.** Los tokens son la fuente de verdad. No uses valores hardcoded.
- **Tipografía deterministe.** El frontend no usa em-dashes (`—`) ni emojis: rompen la consistencia visual y dependen de fuentes externas no controladas. Ver §12 para alternativas (`:`, `·`, `-`, íconos SVG).

---

## 2. Color

### 2.0 Modelo de tokens en tres capas

```
Capa 1  Primitivas   --color-blue-*, --color-neutral-*, --color-*-default/-surface/-strong
                     Invariantes: el mismo valor en claro y en oscuro. Nunca se usan
                     directamente fuera de la definición de un alias en :root.
Capa 2  Semánticas   --bg-*, --text-*, --border-*, --action-*, --accent-*, --status-*
                     Se re-vinculan por tema en [data-theme-resolved="dark"]. Todo CSS
                     de componentes y toda vista deben usar SOLO estos.
Capa 3  Componente   --sidebar-*, --chart-*, --shadow-*
                     Tokens de una parte específica de la interfaz; también se
                     re-vinculan por tema.
```

**Regla dura:** si necesitas un color y no existe un alias de Capa 2/3 para el caso, el bug es que falta el alias — no uses la primitiva directamente ni un hex literal.

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
  --bg-elevated:     var(--color-neutral-0);  /* dropdowns, popovers */
  --bg-hover:        var(--color-neutral-100);
  --bg-overlay:      rgba(0, 0, 0, 0.5);

  /* Texto */
  --text-primary:    var(--color-neutral-900);
  --text-secondary:  var(--color-neutral-700);
  --text-muted:      var(--color-neutral-500);
  --text-disabled:   var(--color-neutral-400);
  --text-inverse:    #FFFFFF;   /* texto sobre chrome oscuro — sí se re-vincula */
  --on-accent:       #FFFFFF;   /* texto sobre relleno de marca — fijo en los dos temas */
  --text-link:       var(--color-blue-500);
  --text-link-hover: var(--color-blue-600);

  /* Bordes */
  --border-subtle:   var(--color-neutral-200);
  --border-default:  var(--color-neutral-300);
  --border-strong:   var(--color-neutral-400);
  --border-focus:    var(--color-blue-500);
  --border-critical: var(--color-critical-default);

  /* Acciones */
  --action-primary:          var(--color-blue-500);
  --action-primary-hover:    var(--color-blue-400);
  --action-primary-pressed:  var(--color-blue-600);
  --action-primary-disabled: var(--color-neutral-300);

  /* Acento (tinte de "seleccionado/activo") y estado (superficie+texto+borde) */
  --accent-surface: var(--color-blue-50);
  --accent-text:    var(--color-blue-700);
  --accent-border:  var(--color-blue-200);
  --status-info-text:     var(--color-blue-800);
  --status-success-text:  var(--color-success-strong);
  --status-warning-text:  var(--color-warning-strong);
  --status-critical-text: var(--color-critical-strong);
}
```

### Reglas de uso de color

- Máximo **2 colores de acento por pantalla**: el primario (`blue-500`) y un semántico si hay estado.
- El fondo de página siempre es `--bg-page`. Las cards y modales van sobre `--bg-surface`; los dropdowns/popovers sobre `--bg-elevated` (en claro son el mismo blanco; en oscuro `--bg-elevated` es un paso más claro que `--bg-surface`, porque ahí no hay sombra que los separe visualmente).
- Nunca uses `blue-500` directamente como fondo de una superficie grande — solo en botones, badges, y highlights pequeños.
- Los textos sobre azul sólido usan `--on-accent` (blanco fijo), no `--text-inverse` (que sí cambia con el tema). Verifica ratio mínimo 4.5:1.
- **Nunca uses una primitiva `--color-*` fuera de la definición de un alias en `:root`.** Si el alias que necesitas no existe, agrégalo — no reintroduzcas la primitiva en el CSS del componente.

### Tema oscuro

Tres estados de preferencia, dos temas efectivos:

| Atributo (en `<html>`) | Valores | Qué hace |
|---|---|---|
| `data-theme` | `light` / `dark` / `system` | La preferencia guardada del usuario (`localStorage nx_theme`). Solo la lee el selector de la interfaz. |
| `data-theme-resolved` | `light` / `dark` | El tema efectivo que usa el CSS. Es `dark` cuando la preferencia es `dark`, o cuando es `system` y el SO está en oscuro. |

Ambos se fijan **antes del primer pintado** por un script en `<head>` (`Core/Views/partials/theme_boot.php`, incluido antes del `<link>` a `app.css` en cada layout) — el mismo patrón que ya usa `nx_sidebar_collapsed` para evitar el parpadeo del ancho del sidebar. La preferencia es por dispositivo (localStorage), no hay columna en `users`.

```css
:root { /* claro — valores por defecto, ver §13 */ }

:root[data-theme-resolved="dark"] {
  /* un solo bloque de overrides: SOLO Capa 2 y Capa 3. Las primitivas de
     Capa 1 nunca se re-vinculan aquí. */
}
```

**Por qué la rampa `--color-neutral-*` nunca se invierte:** invertirla (hacer que `neutral-0` valga un tono oscuro) parece ahorrar trabajo, pero rompe el modelo — cada primitiva pasaría a significar cosas distintas según el tema, y el oscuro dejaría de ser una decisión de diseño propia (compresión de contraste, elevación por luminosidad, estados desaturados) para ser un espejo automático del claro, que casi nunca es la superficie correcta. Rebindar solo los alias de Capa 2/3 es más trabajo una vez, y correcto para siempre.

**Elevación:** claro eleva con sombra (`--shadow-*` sobre un fondo ya claro se ve). Oscuro no puede: la sombra por sí sola es invisible sobre un fondo ya oscuro, así que eleva con **luminosidad** — cada nivel (`--bg-page` → `--bg-surface` → `--bg-surface-alt` → `--bg-elevated`) es un paso más claro que el anterior — más un filo superior sutil (`--shadow-elevation-highlight`, `inset 0 1px 0 rgba(255,255,255,.05)` en oscuro, `none` en claro).

**El sidebar es oscuro en los dos temas, pero no del mismo oscuro** — es la única superficie con esta regla, y es fácil "simplificarla" por error:
- En **claro**, el sidebar contrasta por **inversión**: la página es clara, el sidebar es la superficie oscura de la aplicación.
- En **oscuro**, no se puede invertir — la página ya es oscura. El sidebar contrasta por **elevación hacia arriba**: un paso más claro que `--bg-page`, con un borde visible (`--sidebar-border`), no el mismo negro que la página.

**Contraste verificado en oscuro** (superficie `--bg-surface` `#1A222B`):

| Alias | Valor | Ratio | ¿Pasa AA? |
|---|---|---|---|
| `--text-primary` | `#D2D9E0` | 11.3:1 | ✅ |
| `--text-muted` | `#8795A2` | 5.6:1 | ✅ |
| `--text-link` | `#6FB2EA` | ~6.1:1 | ✅ |
| `--status-success-text` | `#55D6A8` | 7.9:1 | ✅ |
| `--status-warning-text` | `#F2C55C` | 9.2:1 | ✅ |
| `--status-critical-text` | `#FF8F7A` | 5.0:1 | ✅ |

> **Trampa:** `--color-success-default` (`#008060`) es ~2.4:1 sobre `--bg-surface` en oscuro — no pasa AA como texto. Por eso `--status-success-text` existe como alias separado de la primitiva de estado: para **texto** en oscuro siempre usa `--status-*-text`, nunca `--color-*-default` directo.

**Qué NO sigue el tema del usuario, y por qué:**
- **Correos** (`Views/emails/*.php`): los clientes de correo no soportan `prefers-color-scheme` ni variables CSS de forma confiable. Se quedan en claro, con hex fijo — es lo correcto ahí, no una omisión.
- **Modo presentación** (`Reports/Views/present.php`): tiene identidad propia y deliberada (deck siempre oscuro, para proyectar), documentada en su propio encabezado. No debe acoplarse a `nx_theme`.
- **Widget público de ServiceDesk** (`Views/widget/*.php`): se embebe en sitios de terceros vía iframe; debe verse igual sin importar el tema del usuario de Nexus que lo insertó.

---

## 3. Tipografía

### Stack

```css
:root {
  --font-sans: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI',
               Roboto, 'Helvetica Neue', Arial, sans-serif;
  --font-mono: 'IBM Plex Mono', 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
```

**IBM Plex Sans / IBM Plex Mono, autoalojadas** (`public/fonts/`, licencia SIL OFL 1.1). Nexus es una consola de operaciones — folios de ticket, IDs de GLPI, correos, timestamps, cifras KPI — e IBM Plex está dibujada para eso: producto técnico/enterprise, con carácter propio (no lee como la fuente por defecto de cualquier sitio) y buena cobertura de diacríticos en español. Plex Mono acompaña en identificadores y datos donde importa el alineado.

> **`public/fonts/` es la única fuente permitida.** La CSP del proyecto es `styleSrc/scriptSrc 'self'` — no se carga tipografía desde un CDN (ni Google Fonts, ni ningún otro). Si se agrega un peso o un nuevo corte, se descarga el `.woff2`, se sube a `public/fonts/` y se declara con `@font-face` en `app.css`, igual que los cuatro archivos actuales (latin + latin-ext, Sans 400–700 variable + Mono 400).
>
> Si el archivo `.woff2` no carga por cualquier razón, el stack cae a las fuentes del sistema (`-apple-system`, `Segoe UI`, etc.) — la lectura nunca se rompe.

### Escala tipográfica

Los saltos son perceptibles a propósito. La escala anterior tenía 4 niveles (`sm`/`base`/`md`/`lg`) repartidos en 3px — una diferencia menor al antialiasing, que no genera jerarquía visible.

| Token               | `font-size` | `line-height` | `font-weight` | Uso |
|---------------------|-------------|----------------|----------------|-----|
| `--text-xs`         | `12px`      | `16px`         | 400            | Metadata, timestamps, captions |
| `--text-sm`         | `13px`      | `20px`         | 400            | Labels de campos, texto de ayuda |
| `--text-base`       | `14px`      | `20px`         | 400            | Cuerpo de texto, párrafos |
| `--text-lg`         | `16px`      | `24px`         | 500            | Subtítulos de sección (`--text-md` es alias de este, por compatibilidad) |
| `--text-xl`         | `18px`      | `28px`         | 600            | Títulos de página, headings |
| `--text-2xl`        | `22px`      | `32px`         | 600            | Headings principales, stat numbers |
| `--text-3xl`        | `28px`      | `36px`         | 700            | Hero stats, números grandes |

Cifras (montos, conteos, IDs de ticket) llevan `font-variant-numeric: tabular-nums` — utilidad `.tabular-nums`, ya aplicada por defecto en `.table`, `.stat-value`, `.stat-delta` y `.pagination` — para que alineen en columna en vez de bailar por el ancho variable de cada dígito.

```css
:root {
  --text-xs:   0.75rem;
  --text-sm:   0.8125rem;
  --text-base: 0.875rem;
  --text-md:   1rem;    /* alias de --text-lg */
  --text-lg:   1rem;
  --text-xl:   1.125rem;
  --text-2xl:  1.375rem;
  --text-3xl:  1.75rem;

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

Las sombras se componen sobre `--shadow-color`, no llevan el color escrito adentro — así se pueden re-vincular por tema sin repetir cada `rgba(...)`. En claro es negro suave; en oscuro es más negro y más opaco, porque sobre un fondo ya oscuro una sombra tenue no se distingue (ver "Tema oscuro" en §2).

| Token             | Composición | Uso |
|-------------------|-----------|-----|
| `--shadow-xs`     | `0 1px 2px var(--shadow-color)` | Hover de row, hover de card |
| `--shadow-sm`     | `0 2px 4px var(--shadow-color)` | Card en reposo |
| `--shadow-md`     | `0 4px 12px var(--shadow-color)` | Dropdown, popover |
| `--shadow-lg`     | `0 8px 24px var(--shadow-color-strong)` | Modal, dialog |
| `--shadow-xl`     | `0 16px 48px var(--shadow-color-strong)` | Drawer lateral |
| `--shadow-focus`  | fijo | Estado de foco (ring) — azul en claro, blue-300 en oscuro |
| `--shadow-focus-critical` | fijo | Foco en campo con error |
| `--shadow-elevation-highlight` | `none` en claro / `inset 0 1px 0 rgba(255,255,255,.05)` en oscuro | Filo superior en superficies elevadas (dropdown, modal) — el sustituto de la sombra en oscuro |

```css
:root {
  --shadow-color:        rgba(0, 0, 0, 0.10);
  --shadow-color-strong: rgba(0, 0, 0, 0.16);
  --shadow-xs:    0 1px 2px var(--shadow-color);
  --shadow-sm:    0 2px 4px var(--shadow-color);
  --shadow-md:    0 4px 12px var(--shadow-color);
  --shadow-lg:    0 8px 24px var(--shadow-color-strong);
  --shadow-xl:    0 16px 48px var(--shadow-color-strong);
  --shadow-focus: 0 0 0 3px rgba(23,115,200,0.35);
  --shadow-focus-critical: 0 0 0 3px rgba(215,44,13,0.30);
  --shadow-elevation-highlight: none;
}

:root[data-theme-resolved="dark"] {
  --shadow-color:        rgba(0, 0, 0, 0.55);
  --shadow-color-strong: rgba(0, 0, 0, 0.70);
  --shadow-elevation-highlight: inset 0 1px 0 rgba(255, 255, 255, 0.05);
}
```

**Regla:** una superficie que solo necesita distinguirse del fondo (tablas, `.stat-card`, inputs) lleva borde y nada más — sin sombra decorativa. Las superficies que flotan sobre el resto del contenido (`.card`, dropdowns, modales) sí llevan sombra además del borde, con la intensidad según cuánto flotan: `--shadow-sm` para una card en el flujo normal, `--shadow-lg`/`--shadow-xl` para lo que aparece encima de todo. Ver §2 "Tema oscuro" para la regla de elevación en oscuro (luminosidad, no sombra).

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

**Regla de filtros en desktop:** Los `<select>` y `<input>` usados como filtros inline (dentro de una barra de filtros con `display: flex`) NO deben heredar `width: 100%`. Asigna un ancho fijo explícito via `style="width: Xpx;"` proporcional al contenido esperado (mínimo `160px`, típico `200–240px`). En mobile (`max-width: 640px`) pueden expandirse a `width: 100%`. El `width: 100%` del `.select` global aplica solo a campos de formulario verticales (dentro de `.field-group` o similar).

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

**El rol ARIA decide si el banner desaparece solo.** `public/js/app.js` auto-oculta a los
5 segundos todo `.banner[role="status"]`, porque ese rol marca los mensajes flash de una
acción recién hecha ("Guardado", "Carga #12 encolada").

| Uso | Rol | Conducta |
|---|---|---|
| Mensaje flash de una acción recién ejecutada | `role="status"` | Se desvanece a los 5 s |
| Advertencia que el usuario debe seguir viendo | `role="alert"` | Permanente, y el lector de pantalla la anuncia |
| Texto explicativo permanente de la pantalla | sin rol | Permanente y silencioso para el lector de pantalla |

Poner `role="status"` en un banner explicativo lo hace desaparecer a media lectura y deja
al usuario sin el contexto. Si el banner explica cómo funciona la pantalla, va sin rol.

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

.table tr:hover td { background: var(--bg-hover); }
.table tr:last-child td { border-bottom: none; }
```

### 8.8 Gráficas (Chart.js)

Los dashboards con Chart.js (Reports, Employees, KPIsOperativos) no llevan color fijo: leen su paleta de `NxChartTheme.palette()` (`public/js/chart-theme.js`), que a su vez lee los tokens `--chart-*` de `app.css`. Nunca hardcodees un hex en un config de Chart.js — agrega o usa uno de estos tokens.

| Token | Uso |
|---|---|
| `--chart-cat-1/-2/-3` | Identidad categórica, orden fijo — nunca se reciclan para otra cosa |
| `--chart-good/-warning/-serious/-critical` | Paleta de estado, fija — nunca se reusa para una serie cualquiera |
| `--chart-seq-1` … `--chart-seq-5` | Rampa secuencial (magnitud/ordinal). **Se invierte en oscuro**: en fondo oscuro "más" se lee más claro, no más oscuro |
| `--chart-grid`, `--chart-text`, `--chart-text-muted` | Ejes y rejilla |
| `--chart-tooltip-bg`, `--chart-tooltip-text`, `--chart-point-bg` | Tooltip y borde de punto/segmento |

Un dashboard nuevo debe: llamar `NxChartTheme.palette()` al construir cada gráfica (no guardar los colores en una constante a nivel de módulo — se evaluaría una sola vez, con el tema de la primera carga) y suscribirse con `NxChartTheme.onChange(renderAll)` para destruir y reconstruir sus `Chart` cuando el usuario cambia de tema. Ver `public/js/reports-dashboard.js` como referencia.

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

### Contrastes verificados (oscuro)

Sobre `--bg-surface` en oscuro (`#1A222B`):

| Combinación | Ratio | ¿Pasa AA? |
|-------------|-------|-----------|
| `--text-primary` (`#D2D9E0`) | 11.3:1 | ✅ |
| `--text-muted` (`#8795A2`) | 5.6:1 | ✅ |
| `--text-link` (`#6FB2EA`) | ~6.1:1 | ✅ |
| `--status-success-text` (`#55D6A8`) | 7.9:1 | ✅ |
| `--status-warning-text` (`#F2C55C`) | 9.2:1 | ✅ |
| `--status-critical-text` (`#FF8F7A`) | 5.0:1 | ✅ |

> **Trampa:** `--color-success-default` (`#008060`, la primitiva de estado) es ~2.4:1 sobre superficie oscura — no pasa AA como texto ahí. Para texto en oscuro siempre usa el alias `--status-*-text`, nunca la primitiva `--color-*-default` directo. Ver §2 "Tema oscuro".

---

## 12. Tonos de voz y copy

- **Claro y directo.** "Guardar cambios" no "Aplicar modificaciones".
- **Accionable.** Los labels de botón son verbos. "Crear producto", "Eliminar", "Ver detalles".
- **Los errores explican qué hacer.** "El correo no es válido. Usa el formato correo@dominio.com".
- **Sin jerga técnica** en mensajes de usuario. Los logs técnicos van en consola, no en UI.
- **Mayúsculas de título** solo en navegación principal y headings de página. El resto en sentence case.

### Caracteres prohibidos en el frontend

Las siguientes reglas aplican a **todo texto renderizado al usuario** (views PHP, plantillas de email, atributos `alt`/`title`/`aria-label`, strings de JS expuestas en la UI, mensajes flash, banners, etc.). NO aplican a comentarios de código ni a logs técnicos.

| Carácter | Code point | Estado | Reemplazo recomendado |
|----------|------------|--------|------------------------|
| `—` em-dash (guion largo) | U+2014 | **Prohibido** | Reescribir la frase, o usar `:` (dos puntos), `·` (middle dot U+00B7), `-` (hyphen-minus) según contexto |
| Emojis (🎉 ✅ ⚠️ ❌ etc.) | varios | **Prohibido** | Usar íconos SVG del sistema (Lucide/Heroicons) con `aria-label` o `aria-hidden` según semántica |

**Por qué:**
- El em-dash es ambiguo, no se puede teclear consistentemente y rompe con el tono claro y directo del copy. Si necesitas una pausa fuerte, usa dos oraciones cortas.
- Los emojis varían según sistema operativo, fuente y versión de Unicode. Rompen la consistencia visual del design system y dependen de fuentes externas no controladas. Los íconos SVG son escalables, accesibles, color-token-friendly y deterministas.

**Reglas de aplicación:**
- Para valores vacíos en tablas (placeholder de "sin dato"), usar `-` (hyphen-minus) o dejar la celda vacía con estilo `text-muted`. Nunca `—`.
- Para separadores decorativos entre nombre y descripción en labels/listas, usar `·` (middle dot) o reestructurar con `<span>` y CSS spacing. Nunca `—`.
- Para indicadores de estado (éxito, error, advertencia), usar componentes `badge` o `banner` con el ícono SVG correspondiente. Nunca emoji.
- En select options, evitar adornos textuales (`— Sin asignar —`). Escribir el texto plano: `Sin asignar`.

**Cómo verificar antes de hacer merge:**

```bash
# Detecta em-dashes en views (debe regresar 0 líneas)
grep -rn "—" app/Modules/*/Views/

# Detecta emojis comunes en views (debe regresar 0 líneas)
grep -rnE "[😀-🙏✅❌⚠️🎉]" app/Modules/*/Views/
```

---

## 13. Variables CSS — referencia completa

La fuente de verdad es `public/css/app.css` — este bloque es un resumen. Estructura real del archivo: **13.1** primitivas (invariantes), **13.2** semánticas claro/oscuro lado a lado, **13.3** tokens de componente (sidebar, gráficas, sombras), **13.4** alias heredados.

### 13.1 Primitivas (Capa 1 — no cambian entre temas)

```css
--color-blue-50..900:     #EEF5FC → #061F36  (10 pasos)
--color-neutral-0..900:   #FFFFFF → #1A1C1E  (11 pasos)
--color-success-surface/-default/-strong:  #F1F8F5 / #008060 / #00593F
--color-warning-surface/-default/-strong:  #FFF5EA / #B98900 / #8A6500
--color-critical-surface/-default/-strong: #FFF4F4 / #D72C0D / #A21A00
--color-info-surface/-default:             #EEF5FC / #1773C8
```

### 13.2 Semánticas (Capa 2 — claro / oscuro)

| Token | Claro | Oscuro |
|---|---|---|
| `--bg-page` | `#F6F6F7` | `#10151B` |
| `--bg-surface` | `#FFFFFF` | `#1A222B` |
| `--bg-surface-alt` | `#F1F1F2` | `#212B35` |
| `--bg-elevated` | `#FFFFFF` | `#28333F` |
| `--bg-hover` | `neutral-100` | `rgba(255,255,255,.06)` |
| `--text-primary` | `#1A1C1E` | `#D2D9E0` |
| `--text-secondary` | `#44494D` | `#AEB9C4` |
| `--text-muted` | `#6D7175` | `#8795A2` |
| `--text-link` | `#1773C8` | `#6FB2EA` |
| `--on-accent` | `#FFFFFF` | `#FFFFFF` (fijo) |
| `--border-subtle` | `#E3E4E5` | `rgba(255,255,255,.09)` |
| `--border-default` | `#C9CCCF` | `rgba(255,255,255,.15)` |
| `--border-focus` | `#1773C8` | `#57A5E0` |
| `--action-primary` | `#1773C8` | `#1773C8` (fijo — el botón no cambia, solo lo que lo rodea) |
| `--accent-surface`/`-text`/`-border` | tinte azul claro | tinte azul translúcido |
| `--status-{info,success,warning,critical}-{surface,text,border}` | superficie tenue + texto `-strong` | superficie translúcida + texto más claro |

```css
--font-sans: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
--font-mono: 'IBM Plex Mono', 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
--text-xs/-sm/-base/-lg/-xl/-2xl/-3xl: 12 / 13 / 14 / 16 / 18 / 22 / 28px;
--weight-regular/-medium/-semibold/-bold: 400 / 500 / 600 / 700;

--space-1..20 (base 4px): 4 / 8 / 12 / 16 / 20 / 24 / 32 / 40 / 48 / 64 / 80px;
--radius-sm/-md/-lg/-xl/-full: 4 / 8 / 12 / 16px / 9999px;

--duration-fast/-base/-moderate/-slow/-slower: 100 / 150 / 200 / 300 / 400ms;
--ease-default/-in/-out/-spring: cubic-bezier(...);
```

### 13.3 Tokens de componente (Capa 3 — claro / oscuro)

```css
/* Sombras — composición, ver §6 */
--shadow-color / --shadow-color-strong;         /* rgba(0,0,0,.10/.16) claro; .55/.70 oscuro */
--shadow-elevation-highlight;                    /* none claro; inset highlight oscuro */

/* Sidebar — oscuro en los dos temas, no el mismo oscuro (ver §2) */
--sidebar-bg/-border/-text/-text-hover/-icon/-section-label/-hover-bg/-active-bg/-active-text/-accent;

/* Gráficas Chart.js — ver §8.8 */
--chart-cat-1/-2/-3; --chart-good/-warning/-serious/-critical;
--chart-seq-1..5 (se invierte en oscuro); --chart-grid/-text/-text-muted/-tooltip-bg/-tooltip-text/-point-bg;
```

### 13.4 Alias heredados (no usar en código nuevo)

`app.css` define `--color-primary`, `--color-text`, `--color-border`, `--surface`, `--radius-base`, etc. — nombres que ya existían en vistas de módulos antes de que este documento fijara los nombres canónicos. Son alias de solo lectura hacia los tokens de 13.2/13.1 (así heredan el tema automáticamente); no se usan al escribir CSS nuevo, solo existen para que las vistas antiguas no queden rotas. La lista completa está al final del bloque `:root` en `app.css`, marcada con ese mismo comentario.

---

## 14. Qué NO hacer

- Valores hardcoded en CSS. Siempre tokens.
- Más de 1 botón `primary` por vista.
- `outline: none` sin `box-shadow` de reemplazo.
- Sombras decorativas donde el borde es suficiente.
- Gradientes en fondos de página o cards.
- Paleta de colores que no proviene de los tokens.
- Font-size menor de 12px en interfaz real.
- Animaciones en `prefers-reduced-motion`.
- Cards sin suficiente breathing room: mínimo `--space-4` de padding.
- Texto de error en color rojo puro (`#FF0000`): usa `--color-critical-default`.
- **Em-dashes (`—`) en cualquier texto renderizado al usuario.** Reescribir la frase o usar `:`, `·`, `-` (ver §12).
- **Emojis en la interfaz.** Usar íconos SVG (ver §7 y §12).
- **Invertir la rampa `--color-neutral-*` para simular oscuro.** Es Capa 1, invariante — el oscuro se hace re-vinculando los alias de Capa 2/3 (ver §2 "Tema oscuro").
- **Usar una primitiva `--color-*` fuera de la definición de un alias en `:root`.** Si falta el alias, agrégalo.
- **Un `<style>` con hex literal en una vista del shell principal.** Las únicas excepciones legítimas son las plantillas de correo, el modo presentación y el widget público (ver §2 "Tema oscuro" → qué no sigue el tema).
- **Cargar tipografía desde un CDN.** La CSP es `'self'`; toda fuente va autoalojada en `public/fonts/` (ver §3).
- **Asumir que el tema oscuro es el claro invertido.** Cambia la estrategia de elevación (luminosidad, no sombra), el sidebar contrasta al revés, y la rampa secuencial de gráficas se invierte — no es un filtro, es una decisión de diseño propia.

### Verificación

```bash
# Tokens usados en vistas pero nunca definidos en app.css (debe regresar solo
# los locales de widget/frame.php, widget/landing.php y present.php)
comm -23 \
  <(grep -rhoE 'var\(--[a-z0-9-]+' app/Modules --include=*.php | sed 's/var(//' | sort -u) \
  <(grep -oE '^\s*--[a-z0-9-]+' public/css/app.css | tr -d ' ' | sort -u)

# Color literal en style="" de una vista del shell (debe tender a 0; excluye
# emails, widget público y present.php)
grep -rnE 'style="[^"]*(color|background|border|shadow)[^"]*#[0-9a-fA-F]{3,8}' app/Modules --include=*.php \
  | grep -v -E 'emails/|widget/|Reports/Views/present.php'
```
