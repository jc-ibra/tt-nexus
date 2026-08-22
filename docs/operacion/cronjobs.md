# Cronjobs

Rutas de ejemplo del servidor (cPanel):

- PHP: `/usr/local/bin/php`
- App: `/home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx`

Ajusta las rutas si tu instalación es distinta.

# Local (Docker · tt-nexus)

Ejecución manual en tu máquina, desde la raíz del proyecto (`.../Dev/php/tt-nexus`).
Estos NO son cron: los corres a mano para probar/forzar cada proceso. El servicio
del contenedor es `app`. Agrega `--debug` para ver el detalle por elemento.

## 1. Comunicaciones — cola de correos

```
docker compose exec app php spark comms:process-queue
```

## 2. Service Desk — importaciones masivas de tickets

```
docker compose exec app php spark servicedesk:process-imports
```

## 3. Aprovisionamiento — reintentos pendientes

```
docker compose exec app php spark provisioning:process-retries
```

## 4. Service Desk — reporte diario de backlog

Auto-gatillado por la hora de corte de la UI; para forzar el envío ahora, agrega `--force`.

```
docker compose exec app php spark servicedesk:send-backlog-report
```

## 5. Despacho de Correo — sincronización del buzón compartido

```
docker compose exec app php spark maildispatch:sync-mailbox --debug
```

Para reimportar todo (ignora el cursor/delta y respeta la fecha de corte configurada):

```
docker compose exec app php spark maildispatch:sync-mailbox --full --debug
```

## 6. Despacho de Correo — autogestión (auto-creación de tickets GLPI)

Corre **después** del sync: crea en GLPI los tickets de las conversaciones
autogeneradas pendientes y responde. `--batch=N` limita cuántas procesa (default 20).

```
docker compose exec app php spark maildispatch:process-autogen --debug
```

# Producción

## 1. Comunicaciones — cola de correos

Frecuencia: cada 5 minutos (`*/5 * * * *`)

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/spark comms:process-queue >> /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/writable/logs/comms-queue.log 2>&1
```

## 2. Service Desk — importaciones masivas de tickets

Frecuencia: cada minuto (`* * * * *`)

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/spark servicedesk:process-imports >> /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/writable/logs/servicedesk-imports.log 2>&1
```

## 3. Aprovisionamiento — reintentos pendientes

Frecuencia: cada 5 minutos (`*/5 * * * *`)

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/spark provisioning:process-retries >> /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/writable/logs/provisioning-retries.log 2>&1
```

## 4. Service Desk — reporte diario de backlog

Frecuencia: cada 5 minutos (`*/5 * * * *`). El comando se auto-gatilla: solo envía al llegar la hora de corte configurada en `/admin/servicedesk/settings#backlog` y una sola vez al día. No edites el crontab para cambiar la hora, cámbiala desde la UI.

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/spark servicedesk:send-backlog-report >> /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/writable/logs/servicedesk-backlog.log 2>&1
```

## 5. Despacho de Correo — sincronización del buzón compartido

Frecuencia: cada 2 minutos (`*/2 * * * *`). Solo sincroniza cuando está habilitado en `/admin/dispatch/settings`; un lockfile evita corridas solapadas.

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/spark maildispatch:sync-mailbox >> /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/writable/logs/maildispatch-sync.log 2>&1
```

## 6. Despacho de Correo — autogestión (auto-creación de tickets)

Frecuencia: cada 2 minutos (`*/2 * * * *`). Corre **después** del sync: crea los tickets GLPI de las conversaciones autogeneradas pendientes y responde. Solo actúa cuando la autogestión está habilitada en `/admin/dispatch/settings#autogestion`; un lockfile evita corridas solapadas.

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/spark maildispatch:process-autogen >> /home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx/writable/logs/maildispatch-autogen.log 2>&1
```

# Test

App: `/home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx`

## 1. Comunicaciones — cola de correos

Frecuencia: cada 5 minutos (`*/5 * * * *`)

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/spark comms:process-queue >> /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/writable/logs/comms-queue.log 2>&1
```

## 2. Service Desk — importaciones masivas de tickets

Frecuencia: cada minuto (`* * * * *`)

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/spark servicedesk:process-imports >> /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/writable/logs/servicedesk-imports.log 2>&1
```

## 3. Aprovisionamiento — reintentos pendientes

Frecuencia: cada 5 minutos (`*/5 * * * *`)

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/spark provisioning:process-retries >> /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/writable/logs/provisioning-retries.log 2>&1
```

## 4. Service Desk — reporte diario de backlog

Frecuencia: cada 5 minutos (`*/5 * * * *`). Auto-gatillado por la hora de corte configurada en la UI.

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/spark servicedesk:send-backlog-report >> /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/writable/logs/servicedesk-backlog.log 2>&1
```

## 5. Despacho de Correo — sincronización del buzón compartido

Frecuencia: cada 2 minutos (`*/2 * * * *`). Solo sincroniza cuando está habilitado en `/admin/dispatch/settings`; un lockfile evita corridas solapadas.

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/spark maildispatch:sync-mailbox >> /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/writable/logs/maildispatch-sync.log 2>&1
```

## 6. Despacho de Correo — autogestión (auto-creación de tickets)

Frecuencia: cada 2 minutos (`*/2 * * * *`). Corre **después** del sync: crea los tickets GLPI de las conversaciones autogeneradas pendientes y responde. Solo actúa cuando la autogestión está habilitada en `/admin/dispatch/settings#autogestion`; un lockfile evita corridas solapadas.

```
/usr/local/bin/php /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/spark maildispatch:process-autogen >> /home/t7x4o9pmpeuw/public_html/test-nexus.trantortechnologies.mx/writable/logs/maildispatch-autogen.log 2>&1
```
