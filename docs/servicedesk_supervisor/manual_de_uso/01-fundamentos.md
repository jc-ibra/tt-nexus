# Parte 1. Fundamentos
 
**Manual de Uso de GLPI para Agentes de Mesa de Ayuda**
Trantor Technologies | Service Desk
 
---
 
## 1.1 Introducción
 
### Propósito del manual
 
Este manual establece la forma correcta y estandarizada de operar GLPI como herramienta de gestión de la mesa de ayuda. Su objetivo es que todos los agentes registren, atiendan, documenten y cierren tickets bajo un mismo criterio, de modo que el servicio sea consistente sin importar quién lo atienda.
 
No es solo un instructivo de botones. Busca que el agente entienda por qué se hace cada cosa, apoyándose en las buenas prácticas de ITIL, para que las decisiones del día a día tengan sentido y aporten a la calidad del servicio.
 
### Alcance
 
Este manual está dirigido a los agentes de mesa de ayuda de Trantor Technologies que operan GLPI para la atención de usuarios y la gestión de servicios de soporte.
 
Cubre la operación funcional de la herramienta: acceso, registro, clasificación, atención, documentación y cierre de tickets, así como los conceptos ITIL necesarios para hacerlo bien.
 
No cubre la administración técnica de GLPI (instalación, configuración de servidor, gestión de perfiles y reglas), que corresponde al administrador de la plataforma.
 
### A quién está dirigido
 
- Agentes de mesa de ayuda de nuevo ingreso, como material de inducción.
- Agentes con experiencia, como referencia de consulta y estandarización.
- Supervisores, como base para evaluar la correcta operación del equipo.
### Cómo usar este manual
 
El manual va de lo general a lo específico. Las primeras partes explican los fundamentos y conceptos; las siguientes bajan al detalle operativo de cada tarea.
 
- Si eres nuevo, léelo en orden.
- Si ya operas GLPI, usa el índice y los anexos como consulta rápida.
- Los recuadros de buena práctica marcan recomendaciones alineadas a ITIL que elevan la calidad del servicio.
---
 
## 1.2 ¿Qué es GLPI?
 
### Descripción breve
 
GLPI (Gestión Libre de Parque Informático) es una plataforma de código abierto para la gestión de servicios de TI. Integra en un solo lugar la mesa de ayuda (gestión de tickets) y la gestión de activos e inventario, lo que permite atender solicitudes e incidencias con contexto sobre el equipo y los servicios involucrados.
 
Para el agente de mesa de ayuda, GLPI es la herramienta central donde vive todo el trabajo: cada solicitud del usuario se convierte en un ticket que se registra, clasifica, atiende, documenta y cierra dentro del sistema.
 
### Rol de GLPI dentro de la operación de soporte
 
GLPI es la fuente única de verdad de la operación. Todo lo que pasa con una atención debe quedar registrado ahí. Esto permite:
 
- Dar seguimiento a cada caso sin depender de la memoria o de correos sueltos.
- Medir el servicio con datos reales (volúmenes, tiempos, cumplimiento de SLA).
- Mantener trazabilidad: quién hizo qué, cuándo y con qué resultado.
- Construir conocimiento reutilizable para resolver más rápido casos similares.
Regla base de la operación: si no está en el ticket, no sucedió. Toda gestión, llamada, correo o acción debe reflejarse en GLPI.
 
### Punto de acceso
 
El acceso a GLPI para Trantor Technologies es:
 
`https://helpdesk.trantortechnologies.mx`
 
El detalle de acceso, credenciales y recuperación se aborda en la Parte 2.
 
---
 
## 1.3 Conceptos ITIL aplicados
 
ITIL es el marco de buenas prácticas más usado para la gestión de servicios de TI. No es una norma rígida ni un software; es un conjunto de recomendaciones probadas sobre cómo entregar y mejorar servicios. GLPI implementa varios de estos conceptos, y entenderlos hace que el agente use la herramienta con criterio y no solo por costumbre.
 
A continuación, los conceptos mínimos que todo agente debe dominar.
 
### Incidencia vs. Solicitud de servicio
 
Esta es la distinción más importante del día a día, porque define cómo se clasifica y se atiende cada ticket.
 
- **Incidencia:** una interrupción no planeada de un servicio o una reducción en su calidad. Algo que funcionaba y dejó de funcionar, o funciona mal. El objetivo es restablecer el servicio lo antes posible.
  Ejemplo: "La impresora del área de facturación no imprime."
- **Solicitud de servicio:** una petición formal del usuario para algo estándar y previsto, que no implica una falla. El objetivo es cumplir la petición conforme a lo definido.
  Ejemplo: "Solicito la instalación de un nuevo equipo de cómputo para un colaborador de nuevo ingreso."
Clasificar correctamente desde el inicio impacta la prioridad, el SLA aplicable y la ruta de atención.
 
### Servicio
 
Un servicio es un medio para entregar valor al usuario ayudándolo a lograr un resultado, sin que él tenga que administrar la complejidad técnica. En la mesa de ayuda, los servicios son las capacidades que el usuario consume: impresión, cómputo, correo, conectividad, entre otros.
 
Pensar en términos de servicio ayuda al agente a dimensionar el impacto: no es lo mismo un equipo aislado que un servicio del que dependen muchos usuarios.
 
### SLA (Acuerdo de Nivel de Servicio)
 
Un SLA es el compromiso de tiempo y calidad con el que se atiende un ticket. Normalmente define un tiempo máximo de respuesta (cuándo se toma el caso) y un tiempo máximo de solución (cuándo se resuelve).
 
El SLA que aplica depende de la prioridad del ticket. Por eso una mala clasificación no es un error menor: puede asignar un compromiso equivocado y afectar el cumplimiento.
 
### Prioridad: impacto y urgencia
 
La prioridad no se decide "a ojo". Resulta de combinar dos variables:
 
- **Impacto:** qué tan grande es el efecto. A cuántos usuarios o servicios afecta.
- **Urgencia:** qué tan rápido necesita resolverse para evitar mayor daño.
La combinación de ambos, mediante una matriz, define la prioridad y con ella el SLA. La matriz detallada se incluye en la Parte 3 y en los anexos.
 
### Escalación
 
Escalar es transferir un ticket a un nivel o grupo con mayor capacidad de resolución cuando el agente no puede resolverlo, o cuando el caso lo requiere por tiempo o complejidad. Existen dos tipos:
 
- **Escalación funcional:** se pasa a otro equipo o especialista con más conocimiento técnico.
- **Escalación jerárquica:** se involucra a un nivel superior por gravedad, riesgo de incumplir SLA o necesidad de una decisión.
Escalar a tiempo es una buena práctica, no una falla. Retener un ticket que no se puede resolver solo consume el SLA.
 
### Base de Conocimiento
 
Es el repositorio de soluciones documentadas dentro de GLPI. Permite resolver más rápido casos conocidos y estandarizar respuestas. El agente la consume para resolver y también la alimenta cuando documenta una solución nueva y reutilizable.
 
### Por qué importan en el día a día del agente
 
Estos conceptos no son teoría de escritorio. Cada uno tiene un efecto directo en el trabajo del agente:
 
- Clasificar bien (incidencia o solicitud) define la ruta de atención.
- Priorizar con criterio define el SLA y el orden de trabajo.
- Escalar a tiempo protege el cumplimiento y al usuario.
- Documentar bien construye conocimiento y mejora los tiempos de todo el equipo.
Un agente que entiende el "por qué" toma mejores decisiones que uno que solo sigue pasos.
 
---
 
## 1.4 Glosario de términos
 
Términos usados en este manual, con su significado en GLPI y su equivalente o relación con ITIL.
 
| Término | Significado en la operación | Relación con ITIL |
|---|---|---|
| Ticket | Registro de una atención en GLPI. Puede ser una incidencia o una solicitud. | Unidad de trabajo de la gestión de incidencias y solicitudes. |
| Incidencia | Falla o interrupción no planeada de un servicio. | Gestión de Incidencias. |
| Solicitud | Petición estándar del usuario, sin falla de por medio. | Cumplimiento de Solicitudes de Servicio. |
| Solicitante | Usuario que reporta o pide algo. | Usuario del servicio. |
| Agente / Técnico | Quien atiende el ticket en GLPI. | Rol de soporte (nivel 1, 2, etc.). |
| Grupo | Conjunto de agentes que atienden un tipo de caso. | Equipo de soporte / cola. |
| Estado | Etapa del ticket en su ciclo de vida (nuevo, en curso, resuelto, cerrado, etc.). | Ciclo de vida del ticket. |
| Impacto | Alcance del efecto de un caso (usuarios o servicios afectados). | Variable de priorización. |
| Urgencia | Rapidez con la que se necesita la solución. | Variable de priorización. |
| Prioridad | Resultado de combinar impacto y urgencia. | Determina el SLA. |
| SLA | Compromiso de tiempo de respuesta y solución. | Acuerdo de Nivel de Servicio. |
| Escalación | Transferir el ticket a un nivel o grupo superior. | Escalación funcional o jerárquica. |
| Seguimiento | Nota o comunicación registrada en el ticket. | Registro y trazabilidad. |
| Tarea | Actividad concreta dentro de un ticket, asignable a un agente. | Gestión de la atención. |
| Solución | Registro de cómo se resolvió el ticket. | Cierre de la incidencia o solicitud. |
| Base de Conocimiento | Repositorio de soluciones documentadas. | Gestión del Conocimiento. |
| Categoría | Clasificación temática del ticket. | Clasificación del servicio. |
 
---
 
*Fin de la Parte 1. Fundamentos.*