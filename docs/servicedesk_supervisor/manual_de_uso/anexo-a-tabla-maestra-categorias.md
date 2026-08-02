# Anexo A. Tabla maestra de categorías
 
**Manual de Uso de GLPI para Agentes de Mesa de Ayuda**
Trantor Technologies | Service Desk
 
---
 
Esta tabla concentra, por categoría, todo lo que el agente MAC necesita para registrar y despachar un ticket correctamente. Sustituye la consulta de varias tablas dispersas: aquí está el tipo de ticket en que la categoría es visible, la convención de SUCURSAL para el título, cómo se asigna, qué tab de campos personalizados se llena y si lleva IDS.
 
## Cómo leer la tabla
 
- **Tipo visible:** en qué tipo de ticket aparece la categoría (Incidencia, Solicitud o ambas). Si el caso no corresponde al tipo, la categoría no se mostrará.
- **Convención SUCURSAL:** qué va en el segmento SUCURSAL del título de los **clientes externos** (rama OP > CE). "Sucursal real" es el nombre de la sucursal física donde se dará el servicio; el resto son convenciones fijas. Las **categorías internas** (áreas internas y administración) **no llevan segmento SUCURSAL**: su título se reduce a `NOMBRE DE CATEGORÍA - DESCRIPCIÓN` (ver 3.3, Título).
- **Asignación:** "Auto" significa que GLPI asigna solo por regla de negocio (el agente no elige coordinador). "Coordinador por estado" significa que el agente asigna manualmente al coordinador según el estado (ver Anexo de coordinadores por estado).
- **Tab:** la tab de campos personalizados que se completa al reabrir el ticket.
- **IDS:** si se captura la tab IDS.
## Rama OP > CE (Clientes Externos)
 
| Categoría | Tipo visible | Convención SUCURSAL | Asignación | Tab | IDS |
|---|---|---|---|---|---|
| OP > CE > Actinver > Edificios | Ambas | EDIFICIOS (ver nota) | Auto: Eleazar Espinoza Chávez | Clientes Externos | Sí |
| OP > CE > Actinver > Data Center | Ambas | DATA CENTER | Auto: Fernando Manuel Juárez Ruiz | Clientes Externos | Sí |
| OP > CE > Actinver > Multivendor | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Afirme > Edificios | Ambas | EDIFICIOS (ver nota) | Auto: Arely Belén Hernández Reyes | Clientes Externos | Sí |
| OP > CE > Afirme > Multivendor | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Banorte | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Sellcom BBVA > Incidencias | Solo incidencia | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Sellcom BBVA > Instalaciones RT | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Sellcom BBVA > Cajeros | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Sellcom BBVA > CEASC | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Sellcom BBVA > Plan Inmobiliario | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Sellcom | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Cattri | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Lexmark | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Pop Media | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Symetry > Receptores | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Symetry > Kiosko Digital | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > VoxPop | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Sappa | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Grupo Asesores | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Digital Solution | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
| OP > CE > Otro | Ambas | Sucursal real | Coordinador por estado | Clientes Externos | Sí |
 
## Rama OP > AI (Áreas Internas)
 
| Categoría | Tipo visible | Convención SUCURSAL | Asignación | Tab | IDS |
|---|---|---|---|---|---|
| OP > AI > Laboratorio | Ambas | Sin sucursal (título = LABORATORIO) | Auto: Raúl López Balbuena | Áreas Internas | Sí |
| OP > AI > Sistemas Internos | Ambas | Sin sucursal (título = SISTEMAS INTERNOS) | Auto: Fernando Zárate Delgadillo | Áreas Internas | Sí |
| OP > AI > Documentación Interna | Solo solicitud | Sin sucursal (título = DOCUMENTACIÓN INTERNA) | Coordinador por estado | Áreas Internas | Sí |
 
## Rama AD (Administración)
 
| Categoría | Tipo visible | Convención SUCURSAL | Asignación | Tab | IDS |
|---|---|---|---|---|---|
| AD > Almacén > Control de Activos | Solo solicitud | Sin sucursal (título = CONTROL DE ACTIVOS) | Auto: Antonio Hernández Bermúdez | Control de Activos | No |
| AD > Servicios Generales > Control de Envíos | Solo solicitud | Sin sucursal (título = CONTROL DE ENVÍOS) | Auto: Gloria Deyanira Guerrero Palomares | Control de Envíos | Sí |
| AD > Servicios Generales > Servicios Internos | Ambas | Sin sucursal (título = SERVICIOS INTERNOS) | Auto: Gloria Deyanira Guerrero Palomares | Áreas Internas | Sí |
| AD > Tesorería > Viáticos | Solo solicitud | Sin sucursal (título = VIÁTICOS) | Auto: Ramón Escalante Méndez | Fuera de alcance en esta fase | Fuera de alcance |
| AD > Relaciones Humanas > Personal | Solo solicitud | Sin sucursal (título = PERSONAL) | Auto: Miriam Yennifer López Hipólito | Fuera de alcance en esta fase | Fuera de alcance |
 
## Notas
 
- **Nodos padre no seleccionables:** OP, AD, CE, AI, y los nodos de cliente que solo agrupan (por ejemplo Actinver, Afirme, Sellcom BBVA, Symetry como nodo) no se seleccionan directamente. El agente elige siempre la categoría hoja más específica.
- **Categorías Edificios (Actinver/Afirme):** son la excepción con Regional propia. El título lleva `CLIENTE - EDIFICIOS - DESCRIPCIÓN` (ej. `ACTINVER - EDIFICIOS - FALLA DE TECLADO`); en la tab Clientes Externos se elige la Regional **Edificios** y la Sucursal se captura como `ACTINVER CORPORATIVO` o `AFIRME CORPORATIVO`. El resto (IDS y demás campos) se llena como cualquier cliente externo (ver 3.7.1).
- **Viáticos y Personal:** en esta fase el agente MAC no crea tickets de estas categorías (se gestionarán mediante formularios más adelante). Sí puede darles seguimiento. Su detalle irá en un anexo posterior.
- **Fuente:** esta tabla se construyó a partir del catálogo de categorías de GLPI, la matriz de responsables de asignación y la tabla de convenciones de SUCURSAL. Cualquier alta o cambio de categoría debe reflejarse aquí para mantenerla como referencia única.
---
 
*Fin del Anexo A. Tabla maestra de categorías.*
 