# Cronjobs

Rutas de ejemplo del servidor (cPanel):

- PHP: `/usr/local/bin/php`
- App: `/home/t7x4o9pmpeuw/public_html/nexus.trantortechnologies.mx`

Ajusta las rutas si tu instalación es distinta.

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
