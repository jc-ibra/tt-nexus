# Cómo se calcula tu evaluación mensual

Guía para agentes · Módulos Supervisor de Mesa y Evaluación de Agentes de Nexus

Este documento explica, pieza por pieza, cómo se arma la calificación que ves en
**Service Desk > Mis evaluaciones**: qué tickets entran, cómo se mide cada uno de
los cinco KPIs, cómo se convierte todo eso en un porcentaje y qué parte la pone
tu supervisor a mano.

Está escrito para que puedas revisar tu propia evaluación y saber si cuadra, sin
tener que preguntarle a nadie. Si al final un número te sigue pareciendo
incorrecto, la última sección dice qué hacer.

---

## 1. De dónde salen los números

Tu calificación tiene dos mitades y cada una nace en un lugar distinto.

**El 80% cuantitativo sale de la auditoría de mesa.** Una vez al mes el
supervisor corre una auditoría que lee directamente de GLPI: tus tickets del
período, sus campos, su bitácora de cambios y sus seguimientos. Sobre cada ticket
se aplican reglas tomadas del Manual de Mesa de Ayuda, y cada regla que no se
cumple deja registrada una **observación**. Los KPIs cuentan observaciones. No
hay nadie calificando ticket por ticket a mano.

**El 20% cualitativo lo captura tu supervisor.** Son ocho competencias que se
califican del 1 al 4. Ahí sí hay criterio humano, y es a propósito: hay cosas que
ningún sistema puede medir leyendo la base de datos.

> **La auditoría no inventa datos.** Todo lo que reporta está en GLPI y puedes
> verlo tú mismo abriendo el ticket. Si una observación no corresponde, el ticket
> es la evidencia para discutirla.

---

## 2. Qué tickets entran en tu evaluación

Este es el punto que más dudas genera, así que conviene tenerlo claro antes de
mirar cualquier porcentaje.

**Entran los tickets que tú registraste, con fecha de apertura dentro del mes
evaluado.** El criterio es quién aparece como el usuario que dio de alta el
ticket en GLPI, no quién lo tiene asignado hoy.

De ahí salen dos denominadores distintos:

| Denominador | Qué incluye | Qué KPIs lo usan |
|---|---|---|
| Tickets del período | Todos tus tickets abiertos dentro del mes, sin importar su estado actual | KPI 1, 2 y 3 |
| Tickets abiertos | De esos, los que siguen sin resolver al momento de correr la auditoría | KPI 4 |

Dos consecuencias importantes:

- **Si no registraste ningún ticket en el mes, no se te genera evaluación.** No
  sales con cero: simplemente no apareces en ese período.
- **La auditoría es una foto del día que se corrió.** El KPI 4 mira tus tickets
  abiertos *en ese momento*. Si dos días después pusiste al día un ticket
  rezagado, el número del mes ya no cambia salvo que el supervisor vuelva a
  correr la auditoría y recalcule.

También conviene saber que la evaluación de un mes solo puede generarse si existe
una auditoría **completada para ese mes natural**, del día 1 al último día. Una
auditoría de una quincena o de un rango partido no alimenta la evaluación
mensual.

---

## 3. Los cinco KPIs, uno por uno

Cada KPI se califica en tres niveles: **Cumple**, **Parcial** o **No cumple**.
Solo los que quedan en *Cumple* suman para el puntaje. Un *Parcial* está mejor
que un *No cumple* para leer tu tendencia, pero para la suma valen igual.

### KPI 1 · Seguimiento activo

**Qué mide:** de tus tickets que ya se resolvieron o cerraron, en cuántos dejaste
al menos un seguimiento, tarea o solución tuya antes de resolverlos.

**Cómo se calcula:** tickets del período sin esta observación, entre el total de
tickets del período, por cien.

| Cumple | Parcial | No cumple |
|---|---|---|
| 90% o más | de 75% a 89.99% | menos de 75% |

**Qué lo baja:** cerrar un ticket sin haber registrado nada en él. Resolverlo por
teléfono y cerrarlo sin dejar constancia cuenta como falta de seguimiento,
aunque el usuario haya quedado atendido. Referencia: Manual, Parte 4.1
"Propiedad del ticket".

### KPI 2 · Clasificación correcta

**Qué mide:** en cuántos de tus tickets la Categoría o el Tipo **no** tuvieron
que cambiarse después de crearlos.

**Cómo se calcula:** igual que el KPI 1, sobre el total de tickets del período.

| Cumple | Parcial | No cumple |
|---|---|---|
| 92% o más | de 80% a 91.99% | menos de 80% |

**Qué lo baja:** que alguien (tú, un compañero o el coordinador) modifique la
categoría o el tipo después de la creación. Se detecta en la bitácora de cambios
de GLPI. Poner la categoría por primera vez al crear el ticket no cuenta como
reclasificación: solo cuenta cuando había un valor previo y se sustituyó.
Referencia: Manual, Parte 3.3 "Categoría".

### KPI 3 · Completitud de campos

**Qué mide:** en cuántos de tus tickets están llenos los campos obligatorios de
la pestaña que corresponde a su categoría, más la pestaña IDS.

**Cómo se calcula:** igual que los anteriores, sobre el total de tickets del
período. Es el KPI más exigente de los tres.

| Cumple | Parcial | No cumple |
|---|---|---|
| 95% o más | de 85% a 94.99% | menos de 85% |

**Qué lo baja:** dejar vacío cualquier campo obligatorio de la pestaña
correspondiente, o dejar incompleta la pestaña IDS (Nombre y Número de empleado)
en las categorías que la exigen. Un detalle a favor: la convención
`NO PROPORCIONADO` **cuenta como lleno**. Si el usuario no te dio el dato,
escríbelo así en lugar de dejar el campo en blanco. Control de Activos es la
única categoría que no pide la pestaña IDS. Referencias: Manual, Parte 3.7 y
Parte 3.7.5.

### KPI 4 · Tickets abandonados

**Qué mide:** qué porcentaje de tus tickets **abiertos** lleva demasiado tiempo
sin actividad tuya. Es el único donde menos es mejor.

**Cómo se calcula:** tickets abiertos abandonados, entre tickets abiertos, por
cien. Si no traes ningún ticket abierto, el KPI queda en 0% y en Cumple.

| Cumple | Parcial | No cumple |
|---|---|---|
| 5% o menos | de 5.01% a 10% | más de 10% |

**Qué cuenta como abandono:** que hayan pasado más días hábiles de los
configurados (5 por omisión) desde tu última actividad en ese ticket. Solo cuenta
**tu** actividad: si un compañero lo movió, el reloj no se reinicia. Si nunca
registraste nada, se cuenta desde la fecha de apertura. Sábados y domingos no
suman. Referencia: Manual, Parte 4.1.

### KPI 5 · Escalaciones

**Qué mide:** cuántas escalaciones válidas se te registraron en el mes. No sale
de la auditoría automática: las captura el supervisor a mano, con ticket, fecha y
motivo, y solo cuentan las que él marca como válidas.

| Cumple | Parcial | No cumple |
|---|---|---|
| 0 escalaciones | 1 o 2 | 3 o más |

Este KPI tiene una consecuencia especial que se explica en la sección 6.

---

## 4. De los KPIs cumplidos al 80% cuantitativo

No se promedian los porcentajes. Se cuenta **cuántos KPIs quedaron en Cumple** y
esa cuenta se traduce a un nivel:

| KPIs en Cumple | Nivel | Puntos cuantitativos (80% del total) |
|---|---|---|
| 5 de 5 | 100 | 80.00 |
| 4 de 5 | 75 | 60.00 |
| 3 de 5 | 50 | 40.00 |
| 2 o menos | 0 | 0.00 |

Esto explica el efecto de escalón que a veces sorprende: **la diferencia entre
89.9% y 90% en un solo KPI puede valer 20 puntos de la calificación final**,
porque cambia la cuenta de KPIs cumplidos. Y también explica lo contrario: subir
un KPI que ya estaba en Cumple no agrega nada. Si quieres mover tu calificación,
el mejor uso de tu esfuerzo es el KPI que está más cerca de cruzar su umbral.

---

## 5. La rúbrica cualitativa (20%)

Son ocho competencias, cada una calificada del 1 al 4 por tu supervisor:

| Competencia | Peso |
|---|---|
| Atención telefónica | 20% |
| Resolución y contención en primer contacto | 18% |
| Iniciativa | 14% |
| Responsabilidad | 13% |
| Buena comunicación | 12% |
| Conocimientos técnicos | 10% |
| Trabajo en equipo | 8% |
| Flexibilidad | 5% |

La escala es la misma para todas:

| Nivel | Significado |
|---|---|
| 1 | No cumple: por debajo de lo esperado de forma recurrente |
| 2 | En desarrollo: cumple parcialmente, requiere seguimiento |
| 3 | Cumple: desempeño adecuado y consistente con lo esperado |
| 4 | Sobresaliente: supera lo esperado de forma constante |

**Cómo se convierte en puntos:** se multiplica cada calificación por su peso y se
suman. Eso da un número entre 1.00 y 4.00, que se lleva a la escala de 20 puntos.
Un 3 parejo en las ocho competencias da 15 de 20; un 4 parejo da los 20; un 2
parejo da 10.

El punto de partida cuando el supervisor no marca nada en una competencia es
**3 (Cumple)**, no cero. La rúbrica se hace para dejar constancia de lo que se
sale de lo esperado, hacia arriba o hacia abajo.

Al abrir tu evaluación ves las ocho competencias con su peso, tu calificación,
lo que aportó cada una y el comentario que tu supervisor haya escrito en cada
punto. Si una competencia tiene un 1 o un 2 sin comentario, eso es exactamente
lo que conviene preguntar en tu sesión de retroalimentación.

---

## 6. El puntaje final y el bloqueo por KPI 5

**Puntaje final = puntos cuantitativos + puntos cualitativos.** Máximo 100.

Ejemplo real de lectura: 4 KPIs cumplidos (60 puntos) más una rúbrica que
promedió cerca de 2 (unos 10 puntos) da alrededor de 70. En esa evaluación lo que
más mueve la aguja es cruzar el quinto KPI, no discutir un punto de la rúbrica.

Mientras tu supervisor no capture la rúbrica, la evaluación **no aparece** en tu
pantalla. Solo ves los períodos ya cerrados, para que nadie lea una calificación
a medio calcular.

**El bloqueo por KPI 5.** Si acumulas 3 o más escalaciones válidas en el mes, la
evaluación queda **bloqueada**: no se calcula puntaje final, aunque tus otros
cuatro KPIs estén perfectos. Sí ves todo el detalle, con la explicación del
bloqueo. Es una regla del sistema de evaluación, no un error de cálculo, y está
pensada para que ese mes se converse en lugar de resumirse en un número.

---

## 7. Tus comentarios (derecho de réplica)

Al final de cada evaluación tienes un espacio propio para escribir. Es lo único
que tú puedes modificar de la evaluación, y nadie más escribe ahí: tu supervisor
lo ve en solo lectura desde su módulo.

Sirve para dejar constancia de contexto que los números no traen: una incapacidad,
un mes con soporte a un proyecto especial, un ticket que la auditoría marcó y que
tú sabes que estuvo bien atendido. Puedes editarlo las veces que quieras.

Un comentario útil es concreto: el número de ticket, qué pasó y qué esperabas que
se contara. "No estoy de acuerdo" sin datos no se puede revisar.

---

## 8. Preguntas frecuentes

### Vi una observación en Mi desempeño que no aparece en mis KPIs, o al revés

Son dos vistas con propósitos distintos y sí pueden no coincidir:

- **Mi desempeño** te muestra únicamente las observaciones que tu supervisor
  revisó y marcó como **procedentes**. Es la lista para conversar y corregir.
- **Los KPIs** se calculan sobre **todas** las observaciones que detectó la
  auditoría de ese mes, estén confirmadas o no.

Así está definido hoy el sistema de evaluación. Si detectas una observación que
no procede y que sí está afectando tu KPI, ese es exactamente el caso que hay que
plantearle a tu supervisor, porque implica recalcular el mes.

### El ticket lo atendí yo, pero lo levantó otra persona

Entonces no está en tu denominador. La evaluación agrupa por quien registró el
ticket en GLPI. Si en tu operación es común atender tickets levantados por otros,
es un tema para plantear con tu coordinador, porque cambia lo que la evaluación
alcanza a ver de tu trabajo.

### Ya puse al día un ticket abandonado, ¿por qué sigue contando?

Porque el KPI 4 se congeló el día que se corrió la auditoría. Ponerlo al día
evita que vuelva a contar el mes siguiente; para que se refleje en el mes ya
corrido, el supervisor tendría que volver a correr la auditoría y recalcular.

### Mi KPI 3 salió bajo y yo lleno todos los campos

Revisa tres cosas, en este orden:

1. **Los campos que se dejaron literalmente vacíos.** Si el usuario no dio el
   dato, `NO PROPORCIONADO` cuenta como lleno; el espacio en blanco no.
2. **La pestaña IDS.** Es la causa más común. Se exige en todas las categorías
   excepto Control de Activos, y sus dos campos son obligatorios.
3. **La categoría del ticket.** Cada categoría exige la pestaña que le
   corresponde. Un ticket mal categorizado se audita contra los campos
   equivocados, y de paso te pega en el KPI 2.

### Subí un KPI y mi calificación no se movió

Es normal si ese KPI ya estaba en Cumple: el puntaje solo cuenta cuántos KPIs
cumples, no por cuánto. Mira cuál de los que están en Parcial es el más cercano a
su umbral.

### Mi evaluación no aparece este mes

Tres razones posibles: no registraste tickets en el período, no se ha corrido la
auditoría de ese mes, o tu supervisor todavía no captura la rúbrica. En los tres
casos aparece en cuanto se resuelva.

### ¿El supervisor puede cambiar mis KPIs a mano?

No. Los cinco KPIs se calculan de los datos de GLPI. Lo que el supervisor
determina es la rúbrica cualitativa, el registro de escalaciones y qué
observaciones marca como procedentes.

---

## 9. Qué no mide esta evaluación

Vale la pena decirlo con claridad, para que nadie lea de más en el número:

- **No mide la satisfacción del usuario.** Ninguna regla sabe si la persona quedó
  contenta con la atención.
- **No mide dificultad ni volumen.** Veinte tickets sencillos y veinte complejos
  se ven igual en el denominador.
- **No mide el trabajo fuera de GLPI.** Llamadas, apoyos a compañeros, visitas y
  todo lo que no deja rastro en un ticket es invisible aquí.
- **No mide presencia ni horario.** No hay registro de entrada ni de sesión.

Los KPIs miden **disciplina de registro**: que el ticket refleje lo que pasó, con
sus datos completos y su seguimiento. Ese es su alcance y por eso pesan 80 y no
100. El resto lo aporta la rúbrica, que sí es criterio humano.

---

## 10. Si un número no cuadra

1. **Abre el detalle de tu evaluación.** Cada KPI muestra su denominador y
   cuántos tickets cumplieron el criterio. Ahí suele verse de inmediato si el
   problema es un ticket suelto o algo generalizado.
2. **Contrasta con Mi desempeño.** Las observaciones procedentes te dicen qué
   tickets específicos se marcaron y por qué regla del Manual.
3. **Abre el ticket en GLPI.** La evidencia siempre está ahí: los campos, la
   bitácora de cambios y los seguimientos.
4. **Deja tu réplica por escrito.** Con el número de ticket y qué esperabas que
   se contara.
5. **Plantéalo con tu supervisor.** Si procede, tendrá que recalcular el mes, y
   para eso necesita el caso concreto.
