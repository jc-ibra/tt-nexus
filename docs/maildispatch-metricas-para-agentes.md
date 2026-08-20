# Cómo se miden tus métricas en Despacho de Correo

Guía para agentes · Módulo MailDispatch de Nexus

Este documento explica, número por número, qué muestran las pantallas **Equipo**
y **Métricas**, de dónde sale cada dato y qué acciones tuyas lo mueven. Está
escrito para que puedas revisar tus propios números y saber si cuadran, sin
tener que preguntarle a nadie.

Si después de leerlo un número te sigue pareciendo incorrecto, al final está la
sección de qué hacer en ese caso.

---

## 1. De dónde salen los números

No existe un cronómetro aparte ni un sistema de vigilancia. Todo se calcula a
partir de dos cosas que el módulo ya guarda mientras trabajas:

**a) Las marcas de tiempo de cada conversación.** Cada hilo guarda cinco fechas:

| Marca | Cuándo se pone | Se vuelve a poner? |
|---|---|---|
| Recibido | Cuando llega el primer correo del solicitante | No |
| Asignado | La primera vez que alguien la toma o te la asignan | No, queda la primera |
| Primera respuesta | La primera vez que sale una respuesta del hilo | No, queda la primera |
| Última actividad | Cada vez que entra o sale un mensaje | Sí, siempre la más reciente |
| Cerrado | Cuando se cierra con una disposición | Se borra si se reabre |

**b) La bitácora de acciones.** Cada vez que haces algo deliberado en el módulo
se guarda un renglón con tu nombre, la conversación y la hora. Se registran:

- Responder
- Reenviar
- Tomar una conversación
- Asignar o reasignar a alguien
- Liberar una conversación
- Cambiar el estado
- Cerrar
- Reabrir
- Escribir una nota interna
- Verificar un autoarchivo o un ticket autogenerado

Esa bitácora es la misma que ves en el detalle de cada conversación. Las
pantallas de métricas simplemente la cuentan agrupada por persona y por periodo.

> **Lo que el sistema registra con tu nombre es lo que hiciste en Nexus.** Si
> respondes un correo directamente desde Outlook, el módulo lo detecta al
> sincronizar y sí actualiza la conversación, pero la acción queda a nombre del
> sistema, no tuyo. Esto es importante y se explica a detalle en la sección 6.

---

## 2. El reloj: horas hábiles

Todos los tiempos de estas pantallas pueden medirse en **horas hábiles** en
lugar de horas de reloj, si el horario de servicio está activado en la
configuración del módulo.

Con el horario activo:

- Un correo que llega el viernes a las 18:50 y se contesta el lunes a las 09:10
  no consume el fin de semana. Consume los minutos hábiles del viernes más los
  del lunes.
- El reloj del SLA se detiene fuera del horario. Si son las 22:00, tu contador
  de "fuera de SLA" no está creciendo.
- Un "día" en estas pantallas significa un **día de servicio**, no 24 horas. Si
  tu jornada es de 10 horas, "2 d" son 20 horas hábiles, no 48 horas.
- Los días festivos y las excepciones cargadas en el calendario tampoco cuentan.

Puedes confirmar si está activo y cuál es el horario en el encabezado de la
pantalla **Equipo**: aparece una línea que dice "Tiempos en horas hábiles" con
el horario configurado. Si esa línea no aparece, los tiempos son de reloj
corrido.

En **Métricas** aparece la misma aclaración debajo de los indicadores.

---

## 3. Estados de una conversación

Entender los estados evita la mayoría de las dudas sobre los conteos.

| Estado | Qué significa |
|---|---|
| Nueva | Llegó y nadie la ha tomado |
| Asignada | Tiene dueño, todavía sin trabajarse |
| En atención | El agente está trabajando en ella |
| Respondida | Salió una respuesta y esperamos al solicitante |
| Esperando agente | El solicitante contestó y ahora la pelota es nuestra |
| Cerrada | Se cerró con una disposición |
| Autoarchivo | Una regla la archivó sin intervención humana |
| Autogenerado | Se convirtió en ticket de GLPI automáticamente |

Movimientos automáticos que conviene conocer:

- Si respondes, la conversación pasa a **Respondida**.
- Si el solicitante contesta un hilo respondido, asignado o en atención, pasa
  sola a **Esperando agente**. No es un castigo: es la señal de que vuelve a
  requerir tu atención.
- Si el solicitante contesta un hilo **cerrado**, se reabre en **Esperando
  agente** conservando a su dueño, y se borra su fecha de cierre.

**Autoarchivo y Autogenerado no cuentan como carga de nadie.** No aparecen en
tus conversaciones en curso ni en el trabajo del equipo, porque nadie las está
trabajando. Solo aplican a conversaciones que no tenían dueño.

---

## 4. Pantalla Equipo

Es la vista en vivo del despachador: qué trae cada quien **en este momento**.
Se actualiza sola cada 30 segundos. Casi todo aquí es estado actual, no
histórico.

### 4.1 Barra superior

| Indicador | Qué cuenta |
|---|---|
| Abiertas | Todas las conversaciones activas: las de los agentes, las sin asignar y las de agentes dados de baja |
| Sin responder | De las abiertas con dueño, las que todavía no tienen primera respuesta |
| Fuera de SLA | De las sin responder, las que ya pasaron el tiempo de primera respuesta acordado |
| Sin actividad | Agentes que llevan 4 horas hábiles o más sin registrar ninguna acción |
| Agentes | Agentes activos en el módulo |

### 4.2 Tarjeta "Sin asignar"

Conversaciones que llegaron a la bandeja y nadie ha tomado. No incluye los hilos
que nosotros iniciamos hacia afuera, ni los autoarchivados, ni los
autogenerados.

"La más antigua" es cuánto lleva esperando la más vieja del montón, y se pone
en ámbar o en rojo según el tiempo de asignación acordado.

### 4.3 Tarjeta de cada agente

| Dato | Qué cuenta exactamente |
|---|---|
| En curso | Tus conversaciones abiertas ahora mismo. Excluye cerradas, autoarchivo y autogenerado |
| Sin responder | De esas, las que aún no tienen primera respuesta |
| En espera | De esas, las que están en estado "Esperando agente" |
| Fuera de SLA | De las sin responder, las que ya rebasaron el tiempo de primera respuesta |
| Cerradas hoy | Conversaciones tuyas cerradas desde las 00:00 de hoy |
| Sin movimiento | Hace cuánto se movió por última vez la **más rezagada** de tus conversaciones abiertas |
| Sin actividad | Hace cuánto registraste **tú** tu última acción, en cualquier conversación |

Las dos últimas filas responden preguntas distintas y es normal que no
coincidan:

- **Sin movimiento** habla de los hilos. Puedes haber contestado diez
  conversaciones hoy y aun así traer una desde hace tres días sin tocar. Eso es
  lo que marca.
- **Sin actividad** habla de la persona. Es la única de las dos que dice algo si
  no traes nada abierto.

El color de "Sin actividad" es: verde con menos de 2 horas hábiles, ámbar de 2 a
4, y rojo de 4 en adelante. Si dice "Sin actividad registrada" en rojo, significa
que esa persona no tiene ninguna acción en la bitácora del módulo: normalmente es
un agente recién dado de alta que todavía no trabaja desde Nexus.

El color de la tarjeta completa se decide por la carga: rojo si tienes algo
fuera de SLA, ámbar si acumulas 5 o más sin responder, azul si traes trabajo,
gris si estás libre.

### 4.4 Actividad reciente

El listado de la derecha muestra las últimas asignaciones, reasignaciones,
liberaciones, cierres y reaperturas del equipo, con quién las hizo. Las
respuestas y las notas internas no aparecen ahí para no saturar el listado, pero
sí cuentan en tus métricas.

Cuando dice "El sistema", significa que el movimiento lo hizo la sincronización
de correo y no una persona: por ejemplo, una conversación que se reabre sola
porque el solicitante contestó.

---

## 5. Pantalla Métricas

Aquí sí es histórico: todo se calcula sobre un rango de fechas.

### 5.1 Rango y filtros

- Si no eliges fechas, el rango son los **últimos 30 días**.
- "Desde" empieza a las 00:00 de ese día y "Hasta" termina a las 23:59.
- El filtro de agente afecta a los indicadores de arriba, a las disposiciones y
  a la gráfica diaria. **No afecta** a Destacados ni a la tabla de actividad por
  agente: esas dos siempre muestran a todo el equipo, porque su función es
  comparar.

### 5.2 Indicadores superiores

| Indicador | Qué mide |
|---|---|
| Backlog sin asignar | Conversaciones sin dueño ahora mismo. No depende del rango |
| Recibidas | Conversaciones cuyo primer correo llegó dentro del rango |
| Cerradas | Conversaciones cerradas dentro del rango |
| Prom. primera asignación | Tiempo promedio entre que llega y que alguien la toma |
| Prom. primera respuesta | Tiempo promedio entre que llega y que sale la primera respuesta |

Los dos promedios se calculan sobre las conversaciones **recibidas** dentro del
rango que ya tienen esa marca. Una conversación recibida el último día del rango
y contestada al día siguiente no entra todavía en el promedio.

### 5.3 Destacados del periodo

Cuatro reconocimientos, a propósito separados:

| Destacado | Cómo se gana |
|---|---|
| Mayor carga actual | Quien trae más conversaciones abiertas en este momento |
| Más actividad | Quien registró más acciones en el rango |
| Más cierres | Quien cerró más conversaciones propias en el rango |
| Respuesta más rápida | Mejor promedio de primera respuesta en el rango |

**No hay un "mejor agente" general, y es deliberado.** Estas cuatro preguntas
casi nunca apuntan a la misma persona: quien más carga trae no es quien más
rápido contesta, y quien más cierra no siempre es quien más se mueve.
Combinarlas en un solo puntaje escondería justo lo que hay que ver.

El de respuesta más rápida exige **mínimo 3 conversaciones contestadas** en el
rango. Con una sola conversación contestada en dos minutos nadie gana el
reconocimiento, porque no sería una medición, sería suerte.

### 5.4 Tabla "Actividad por agente"

Ordenada por acciones, de más a menos.

| Columna | Qué cuenta | Periodo |
|---|---|---|
| Abiertas ahora | Conversaciones activas que traes en este momento | Actual, ignora el rango |
| Cerradas | Conversaciones tuyas cerradas | Rango |
| Respuestas | Respuestas que enviaste **desde Nexus** | Rango |
| Asignaciones | Veces que tomaste o reasignaste una conversación | Rango |
| Prom. 1ª resp. | Promedio de primera respuesta de tus conversaciones recibidas en el rango | Rango |
| Acciones | Todo lo que registraste en el módulo | Rango |

Detalles que explican diferencias que parecen errores:

- **"Abiertas ahora" es lo único de esa tabla que no depende del rango.** Si
  cambias las fechas, esa columna no se mueve. Es el mismo número que "en curso"
  en la pantalla Equipo.
- **"Cerradas" se acredita al dueño de la conversación, no a quien la cerró.**
  Si un despachador cierra un hilo tuyo, te suma a ti. La acción de cerrar, en
  cambio, se le cuenta a él en "Acciones".
- **"Prom. 1ª resp." se agrupa por el dueño actual.** Si una conversación se
  reasignó después de la primera respuesta, ese tiempo cuenta para quien la
  tiene hoy, no para quien contestó. En reasignaciones frecuentes conviene leer
  este número con cuidado.
- El número entre paréntesis del tooltip dice sobre cuántas conversaciones se
  calculó tu promedio. Un promedio sobre 2 conversaciones no dice gran cosa.
- La barra azul de "Acciones" es proporcional a quien más acciones tuvo en el
  rango. Sirve para comparar de un vistazo, no es un porcentaje de meta.

### 5.5 Distribución de disposiciones

Con qué disposición se cerraron las conversaciones del rango. Si el filtro de
agente está puesto, muestra solo las tuyas.

### 5.6 Volumen diario recibido

Cuántas conversaciones llegaron cada día. Los días sin correo se dibujan en
cero, no se saltan: así un fin de semana tranquilo se ve como lo que es y no
como si el lunes siguiera al viernes.

### 5.7 Exportar CSV

Baja el detalle conversación por conversación del rango: asunto, solicitante,
estado, agente, disposición, folio de GLPI, las cuatro fechas clave y el número
de mensajes. Si un número agregado no te cuadra, este archivo es la forma de
revisarlo renglón por renglón.

### 5.8 Mis métricas

La misma pantalla, siempre filtrada a ti, sin Destacados ni tabla comparativa.
Cualquier agente puede abrirla. La versión completa del equipo es solo para
despachadores.

---

## 6. Preguntas frecuentes

**Respondí desde Outlook. ¿Cuenta?**

Cuenta a medias, y conviene entender la diferencia.

La sincronización detecta el correo saliente y sí actualiza la conversación:
registra la primera respuesta, la pasa a "Respondida" y actualiza la última
actividad. O sea, tu tiempo de primera respuesta **sí mejora** y el hilo **sí**
deja de aparecer como sin responder.

Lo que no pasa es que quede registrado con tu nombre. El correo no le dice al
módulo quién de la cuenta compartida lo escribió, así que esa acción se guarda
como del sistema. Por eso **no suma en "Respuestas" ni en "Acciones", y no
reinicia tu contador de "Sin actividad"**.

Si quieres que tu trabajo se refleje en las métricas, responde desde Nexus.

**Mi promedio de primera respuesta salió alto y yo contesto rápido.**

Revisa tres cosas, en este orden:

1. Sobre cuántas conversaciones se calculó (el tooltip de la columna). Con pocas
   conversaciones, una sola muy lenta arrastra el promedio entero.
2. Si te reasignaron conversaciones viejas. El promedio se agrupa por dueño
   actual, así que heredas el tiempo de respuesta de lo que te pasaron.
3. Si el horario de servicio está apagado. Sin él, las noches y los fines de
   semana sí cuentan como tiempo de espera.

**Contesté un correo atrasado, ¿por qué sigue en rojo "fuera de SLA"?**

No debería. "Fuera de SLA" solo cuenta conversaciones **sin primera respuesta**.
En cuanto respondes, sale de ese conteo. Recuerda que la pantalla Equipo se
refresca cada 30 segundos.

**¿Por qué tengo menos "Respuestas" que "Cerradas"?**

Es normal y no significa nada malo. Hay conversaciones que se cierran sin
responder: informativas, duplicadas, spam, o casos que se resolvieron por
teléfono. También pasa si respondiste desde Outlook.

**Estuve trabajando toda la mañana y aparezco "Sin actividad".**

"Sin actividad" mide **acciones registradas**, no presencia. Leer conversaciones,
investigar, o hablar por teléfono no dejan rastro en el módulo. Tomar, responder,
notar, cambiar estado o cerrar sí.

Si estuviste atendiendo un caso complicado sin registrar nada en Nexus, una nota
interna en la conversación deja constancia de en qué ibas y además actualiza tu
contador.

**Cerré una conversación de un compañero. ¿A quién le suma?**

La conversación cerrada le suma **a su dueño** en la columna "Cerradas". A ti te
suma una acción en "Acciones". Es a propósito: "Cerradas" mide casos resueltos
del agente que los llevó, no clics.

**Reabrieron una conversación que yo había cerrado. ¿Pierdo el cierre?**

Sí. Al reabrirse se borra su fecha de cierre, así que sale del conteo de
"Cerradas" del rango. Cuando se vuelva a cerrar, contará con la nueva fecha.
Su tiempo de primera respuesta original no se pierde ni se recalcula.

**Cambié el rango de fechas y "Abiertas ahora" no se movió.**

Correcto. Esa columna es una foto del momento actual. Todas las demás columnas
de esa tabla sí responden al rango.

**¿Los correos autoarchivados o autogenerados me afectan?**

No. No cuentan como carga tuya en ninguna pantalla. Si tú verificas un
autoarchivo o un ticket autogenerado, esa verificación sí te suma una acción,
porque es trabajo que hiciste.

**¿Por qué en Equipo salen números distintos a los de Métricas?**

Porque responden preguntas distintas. Equipo es **ahora**: qué está abierto en
este instante. Métricas es **un periodo**: qué pasó entre dos fechas. El único
número que debe coincidir es "en curso" de Equipo con "Abiertas ahora" de
Métricas.

---

## 7. Qué no miden estas pantallas

Vale la pena decirlo con claridad, para que nadie lea de más en los números:

- **No miden calidad.** Ninguna pantalla sabe si una respuesta resolvió el
  problema o si el solicitante quedó conforme.
- **No miden dificultad.** Diez casos triviales generan más acciones que dos
  casos complejos que sí requirieron trabajo real.
- **No miden presencia ni horario.** No hay registro de entrada, de sesión ni de
  tiempo frente a la pantalla.
- **No miden trabajo fuera del módulo.** Llamadas, visitas, asesorías de pasillo
  y todo lo que se resuelve sin tocar Nexus es invisible aquí.

"Acciones" es una medida de **movimiento**. Se lee junto a las cerradas y al
tiempo de respuesta, nunca sola y nunca como si fuera una calificación.

---

## 8. Si un número no te cuadra

1. Confirma el rango de fechas y el filtro de agente que tienes puestos.
2. Revisa si el horario de servicio está activo, en el encabezado de Equipo.
3. Exporta el CSV del rango y busca la conversación específica. Las cuatro
   fechas por renglón casi siempre explican la diferencia.
4. Abre la conversación en Nexus y revisa su bitácora: ahí está, en orden, todo
   lo que le pasó y quién lo hizo.
5. Si con eso sigue sin cuadrar, repórtalo con el ID de la conversación, el
   rango que usaste y qué esperabas ver. Con ese dato se puede rastrear.
