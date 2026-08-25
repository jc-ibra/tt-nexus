# Service Desk — Matriz de asignaciones

> Estado: **CONSTRUIDO** (2026-08-24). Sin commitear.
> Verificado contra el archivo real `docs/asignaciones.xlsx` y contra la BD de
> desarrollo: importación, reimportación idempotente, conservación de las
> vinculaciones y render de las dos pantallas. La matriz ya quedó cargada en la
> BD local.
> Objetivo: que cada agente pueda **ver** en el módulo quién atiende cada
> categoría de GLPI, en qué etapa del ticket y por qué canal, sin que nadie
> descargue el archivo.

Es una **extensión de ServiceDesk**, no un módulo nuevo.

---

## 1. Decisiones cerradas (con el usuario, 2026-08-24)

1. **La matriz vive en la base de datos, no en el archivo.** El `.xlsx` es solo el
   medio de transporte: el SuperAdmin lo sube, se parsea, se guarda y el archivo se
   borra. Nadie lo descarga desde la app.
2. **Pantalla propia dentro del módulo, no una guía de ayuda.** Es una tabla que se
   consulta a diario, no material de referencia. Vive en `/servicedesk/asignaciones`,
   con su entrada en el submenú de Service Desk.
3. **Cada agente ve resaltado lo suyo.** El SuperAdmin vincula cada nombre del archivo
   con un usuario de Nexus; con eso el agente obtiene el filtro «Solo lo mío».
4. **Cargar un archivo reemplaza la matriz completa.** No hay carga incremental: la
   verdad es siempre el último archivo. Si el archivo no se puede interpretar, no se
   toca nada y se explica qué corregir.
5. **La leyenda sale del propio archivo.** El bloque de significados que vive a la
   derecha de la matriz se guarda y es lo que se muestra en pantalla, así que renombrar
   una etapa o un canal no requiere tocar código.

---

## 2. Qué forma tiene el archivo

Primera hoja del libro. Doble encabezado:

|   | A | B C D E | F G H I | ... | AA | AB |
|---|---|---|---|---|---|---|
| **1** | Proyecto Categoria | `Raul` (combinada) | `Cinthya` (combinada) | ... | `A` | `Apertura` |
| **2** | | `AV` `A` `D` `C` | `AV` `A` `D` `C` | ... | `D` | `Documentación` |
| **3** | `AD > Almacén > Control de Activos` | `E` `E` `E` | | ... | `E` | `Email` |

- **Fila 1:** el nombre de cada persona, sobre sus columnas.
- **Fila 2:** la etapa de cada columna: `AV` apertura por viáticos, `A` apertura,
  `D` documentación, `C` cierre.
- **Columna A:** la ruta completa de la categoría en GLPI, con el mismo separador
  ` > ` que usa `completename`.
- **Cada celda:** el canal por el que llega ese trabajo: `E`, `W`, `I`, `E / W`, `N/A`.
- **A la derecha de la matriz:** un bloque de dos columnas `código | significado` que
  se guarda como leyenda. Es opcional.

**Dónde termina la matriz.** El parser avanza desde la columna B mientras la fila 2
siga nombrando una etapa válida, y se detiene en la primera que no. Esa es la regla
que deja fuera el bloque de la leyenda, cuya primera columna también contiene letras
como `A` o `D`. Si algún día se agrega una columna intermedia sin etapa en la fila 2,
la matriz se cortará ahí.

---

## 3. Esquema

`servicedesk_assignment_agents` — una fila por persona nombrada en el archivo.

| Columna | Para qué |
|---|---|
| `name` | el nombre tal como viene en la fila 1 (único) |
| `user_id` | usuario de Nexus detrás de ese nombre; `NULL` mientras no se vincule |
| `sort_order` | el orden del archivo, para que las columnas se lean igual |

`servicedesk_assignments` — una fila por celda llena de la matriz.

| Columna | Para qué |
|---|---|
| `category_name` | la ruta completa tal como viene en la columna A |
| `glpi_category_id` | id de GLPI, si la categoría ya está en `servicedesk_category_map`. Informativo: la pantalla no depende de él |
| `row_order` | el orden del archivo |
| `agent_id`, `stage`, `channel` | quién, en qué etapa, por qué canal |

**Por qué dos tablas.** Para que la vinculación con usuarios sobreviva a una recarga.
Al reimportar, las personas se reconcilian **por nombre**: quien ya existía conserva su
`id` y su `user_id`, quien es nuevo se crea, y quien desapareció del archivo se borra
junto con sus celdas. Las celdas se reconstruyen siempre desde cero.

---

## 4. Pantallas

**Agente — `/servicedesk/asignaciones`** (`auth` + `module_access:servicedesk`)

Una fila por categoría y una columna por persona. Cada celda muestra un chip por
etapa cubierta, con el canal y el color del canal, y el detalle completo en el
tooltip. Encima: buscador de categoría (insensible a acentos), filtro por persona
—que además esconde las demás columnas en vez de dejarlas vacías— y, para quien
esté vinculado, «Solo lo mío». El encabezado y la columna de categoría quedan fijos
al hacer scroll.

**SuperAdmin — Configuración · Service Desk, pestaña «Asignaciones»**

Carga del `.xlsx` (reemplaza todo) y la tabla de vinculación nombre → usuario de
Nexus. Muestra cuántas categorías, personas y asignaciones hay, y de cuándo es la
última carga.

---

## 5. API

Espejo de las acciones web, bajo `/api/v1/servicedesk/assignments`. Leer está abierto
a cualquier token con acceso al módulo; reemplazar la matriz y vincular usuarios exigen
SuperAdmin, y como el filtro de la ruta solo valida acceso al módulo, la verificación
se hace dentro del controlador.

| Método | Ruta | Qué hace |
|---|---|---|
| GET | `/assignments` | matriz completa, leyenda, y `my_agent` si el usuario del token está vinculado |
| GET | `/assignments/agents` | personas de la matriz y su usuario |
| POST | `/assignments` | reemplaza la matriz (`multipart`, campo `file`). 422 con detalle si el archivo no sirve |
| POST | `/assignments/agents` | `{ "agent_user": { "<agent_id>": <user_id> } }`; `0` desvincula |

---

## 6. Puntos abiertos

- **La leyenda del archivo trae `Documenteación`.** Se muestra tal cual porque el
  archivo manda. Se corrige editando el `.xlsx` y volviendo a cargarlo.
- **Dos categorías no cruzaron con GLPI:** `OP > CE > Banorte` y `OP > CE > Lexmark`.
  No están en `servicedesk_category_map`, así que se guardaron sin `glpi_category_id`.
  No afecta lo que ve el agente; se resuelve mapeándolas en Configuración · Categorías.
- **Un usuario de Nexus solo puede estar detrás de una persona.** Al asignarlo a otra,
  se desvincula de la anterior, para que «Solo lo mío» no sea ambiguo.
