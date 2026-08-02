# Anexo B. Niveles de servicio (SLA)
 
**Manual de Uso de GLPI para Agentes de Mesa de Ayuda**
Trantor Technologies | Service Desk
 
---
 
El SLA (Acuerdo de Nivel de Servicio) es el compromiso de tiempo con el que se atiende un ticket. Define dos relojes:
 
- **Tiempo de asignación (TTO):** tiempo máximo para que el ticket sea tomado y asignado a quien lo atenderá. Es el compromiso de respuesta.
- **Tiempo de solución (TTR):** tiempo máximo para restablecer el servicio (incidente) o cumplir la petición (solicitud). Es el compromiso de resolución.
Estos dos relojes son los que aparecen en el formulario del ticket como "Tiempo de asignación (TTO)" y "Tiempo de solución (TTR)".
 
## Cómo se asigna el SLA
 
El SLA **se asigna de forma automática** según el tipo de ticket (Incidente o Solicitud). El agente no lo captura ni lo elige: GLPI lo aplica solo al guardar. Por eso clasificar bien el **tipo** del ticket es lo que activa el compromiso correcto.
 
## SLA estándar vigente
 
Actualmente aplica un estándar general para todas las categorías. Los tiempos son:
 
| Tipo de ticket | Compromiso | Tiempo máximo | Calendario |
|---|---|---|---|
| Incidente | Asignación (TTO) | 3 horas | 5x8 |
| Incidente | Solución (TTR) | 10 horas | 5x8 |
| Solicitud | Asignación (TTO) | 6 horas | 5x8 |
| Solicitud | Solución (TTR) | 30 horas | 5x8 |
 
Lectura rápida: los incidentes tienen tiempos más cortos que las solicitudes, porque un incidente implica un servicio interrumpido que hay que restablecer; una solicitud es una petición estándar que admite más margen.
 
## Qué significa el calendario 5x8
 
El reloj del SLA corre sobre un **calendario de horario hábil (5x8)**: cinco días a la semana, ocho horas por día. No corre las 24 horas ni en fines de semana o días no laborables. Es decir, si un ticket entra fuera del horario hábil, el tiempo empieza a contar cuando inicia la siguiente jornada.
 
> Nota: la ventana exacta del calendario 5x8 (hora de inicio y fin, días festivos considerados) está definida en la configuración de GLPI.
 
## Alcance de este estándar
 
Este es un **estándar general provisional**. Más adelante se definirán SLA específicos por tipo de proyecto o servicio, y estos tiempos podrían cambiar por categoría. Cuando eso ocurra, este anexo se actualizará.
 
## Por qué le importa al agente
 
Aunque el SLA se asigne solo, el agente es quien determina si se cumple o no, porque:
 
- El **tipo** que elige (incidente o solicitud) define qué reloj aplica.
- La **fecha de apertura** que captura es el punto desde el que corre el tiempo. Un dato mal puesto mueve todo el cálculo.
- La **prioridad** (impacto y urgencia) define el orden de trabajo frente al reloj.
- Escalar a tiempo evita que un ticket detenido consuma el SLA sin avanzar.
---
 
*Fin del Anexo B. Niveles de servicio (SLA).*