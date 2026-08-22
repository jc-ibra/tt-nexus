# Fix: cierre de sesión aleatorio en local (Docker) — tabla `ci_sessions`

> Estado: **aplicado solo en local (Docker)** el 2026-08-05. La migración **NO** fue
> modificada. Producción **no** fue tocada. Este documento explica la causa, el fix,
> y el análisis de riesgo para producción / `public/setup.php`.

---

## 1. Síntoma

En local con Docker la sesión se cierra sola y de forma aleatoria (a veces al minuto,
a veces a los 5–10 minutos). En stage/prod (Apache) no ocurre.

## 2. Causa raíz

La sesión usa el driver **`DatabaseHandler`** (`app/Config/Session.php` → tabla
`ci_sessions`). En **CodeIgniter 4.7** (`composer.json` pide `^4.7`) el handler escribe
y compara la columna `timestamp` usando la función SQL **`now()`**, que devuelve un
**DATETIME**, no un entero:

- Escritura: `vendor/.../Session/Handlers/DatabaseHandler.php:196` y `:218`
  → `->set('timestamp', 'now()', false)`
- Garbage collector: `DatabaseHandler.php:281`
  → `WHERE timestamp < now() - INTERVAL {lifetime} second`

Pero la migración de este proyecto creó la columna como **`INT UNSIGNED`** (esquema
viejo de CI4):

`app/Modules/Core/Database/Migrations/2026-06-15-000001_CreateCiSessionsTable.php:14`
```php
'timestamp' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, ...]
```

### Qué pasa exactamente

1. Al guardar la sesión, MySQL coacciona `now()` (ej. `2026-08-05 23:35:32`) al meterlo
   en un `INT` → `20260805233532` (~2×10¹³). Eso **desborda** el máximo de `INT UNSIGNED`
   (`4294967295`) y se **trunca** a ese máximo. Por eso todas las filas guardan
   `timestamp = 4294967295`.
2. El GC borra donde `timestamp < now() - INTERVAL 7200 second`. El umbral coercido a int
   es un número de 14 dígitos (~2×10¹³). Como `4294967295 < 2×10¹³` es **siempre verdad**,
   **cada corrida del GC borra TODAS las sesiones activas**.
3. PHP dispara el GC de forma **probabilística** (~1% de los requests). Cuando toca,
   te quedas sin fila de sesión → el `AuthFilter` no encuentra `user_id` → te manda a login.
   Eso explica el patrón aleatorio (1 / 5 / 10 min).

### Evidencia (verificada en vivo contra el Docker local)

```
stored_ts (todas las filas) = 4294967295
umbral GC coercido a int     = 20260805213532
4294967295 < 20260805213532  → matches_gc_delete = 1   (toda fila cae en el DELETE)
sql_mode incluye STRICT_TRANS_TABLES ; MySQL 8.0.46
```

## 3. El fix (aplicado SOLO en local)

Alinear el tipo de la columna con lo que espera CI4 4.7 (`TIMESTAMP`). Se ejecuta
directo en el MySQL del contenedor `tt-nexus-db`, **sin tocar la migración**:

```sql
TRUNCATE TABLE ci_sessions;                 -- limpia filas con el valor corrupto (cierra sesiones una vez)
ALTER TABLE ci_sessions
  MODIFY `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
```

Comando usado:
```bash
docker exec tt-nexus-db mysql -uroot -proot tt_nexus \
  -e "TRUNCATE TABLE ci_sessions; ALTER TABLE ci_sessions MODIFY \`timestamp\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;"
```

### Efectos esperados tras aplicar (en local)
- Las sesiones dejan de morir aleatoriamente; duran las 2 h configuradas
  (`Session::$expiration = 7200`) y el GC solo borra sesiones realmente vencidas.
- Al aplicar el `TRUNCATE` se cierra una vez cualquier sesión activa (hay que
  volver a iniciar sesión una sola vez). Sin pérdida de datos de negocio: `ci_sessions`
  solo guarda sesiones efímeras.
- La columna queda como `timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
  (nota: rango válido de TIMESTAMP en MySQL llega a 2038; suficiente).

## 4. Análisis de riesgo para PRODUCCIÓN y `public/setup.php`

**Este fix en local NO afecta producción.** Razones:

1. **Bases de datos separadas.** El `ALTER`/`TRUNCATE` corre contra el MySQL del
   contenedor Docker local, no contra la BD de prod.
2. **La migración NO se modificó.** El comportamiento de `setup.php` es idéntico a hoy.
3. **CI4 no re-ejecuta migraciones ya registradas.** En `setup.php`:
   - `latest()` (`public/setup.php:230`) salta `CreateCiSessionsTable` porque ya está
     en la tabla `migrations`.
   - El `baseline` (`:185-186`) solo comprueba que **exista la tabla** (regex de
     `createTable('ci_sessions')`); nunca revisa el tipo de columna.
   - La verificación de esquema (`:268-278`) también solo comprueba **existencia** de tablas.
   Conclusión: correr `setup.php` en prod **no altera el tipo de columna** de `ci_sessions`
   en ningún caso, ni antes ni después de este cambio.

### Producción: AFECTADA pero ENMASCARADA (verificado 2026-08-05 con capturas de prod)

**Hallazgo definitivo (la captura sana de datetimes anterior era STAGE, no prod):**
- Prod corre CI4 **v4.7.3** (igual que local; `CI_VERSION` + `composer.lock`).
- La columna de prod es `timestamp int(10) UNSIGNED` (la buggy).
- Las **56 filas** de `ci_sessions` en prod tienen `timestamp = 4294967295` (el valor truncado).
  → **Prod tiene EXACTAMENTE el mismo bug que tenía local.**

**Entonces, ¿por qué prod no cierra sesión?** Por la config de GC de PHP, NO por la versión ni la
columna (son iguales). Se confirmó:
- Local (contenedor `php:8.2-apache`): `session.gc_probability = 1`, `gc_divisor = 100`
  → el GC se dispara en ~1% de los requests → borra todas las filas `4294967295` → logout aleatorio.
- Prod: 56 sesiones acumuladas sin borrar → su `session.gc_probability` es ≈0 (típico de
  hosting/cPanel donde la limpieza la hace un cron). Con el GC apagado, la tabla nunca se vacía
  → los usuarios nunca pierden la sesión.

**EL FACTOR DIFERENCIADOR local vs prod = `session.gc_probability` de PHP.**

**Prod NO está sano, está enmascarado. Dos consecuencias reales:**
1. **Logout latente:** si el `gc_probability` de prod cambia (migración de hosting, cambio de
   `php.ini`) o alguien corre limpieza de sesiones, TODOS los usuarios se desloguean de golpe.
2. **Seguridad:** como la fila nunca se borra y el `read()` de CI4 no valida `timestamp`, las
   sesiones **nunca expiran del lado servidor** — la expiración de 2 h no se aplica; una cookie
   `ci_session` robada seguiría válida indefinidamente.

Por eso conviene arreglar prod aunque hoy no se vea el síntoma (urgencia baja, pero real por el
punto de seguridad). Ver sección 5.

**Nota sobre STAGE:** la captura previa con datetimes reales era de stage; ahí la columna ya se
comporta como datetime. Conviene confirmar el tipo real en stage con:
```sql
SELECT COLUMN_TYPE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ci_sessions' AND COLUMN_NAME = 'timestamp';
```

## 5. Arreglo permanente (CONSTRUIDO 2026-08-05)

Migración **nueva** (no se editó la vieja): 
`app/Modules/Core/Database/Migrations/2026-08-05-000001_FixCiSessionsTimestampType.php`

Qué hace, de forma **guardada e idempotente**:
- Lee el tipo real de `ci_sessions.timestamp` vía `information_schema`.
- Si es `int` → `TRUNCATE TABLE ci_sessions` (sesiones efímeras) + 
  `ALTER TABLE ci_sessions MODIFY \`timestamp\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- Si ya es `timestamp`/`datetime` → **no hace nada** (seguro en local ya corregido y en stage).
- `down()` revierte a `INT(10) UNSIGNED` (reintroduce el esquema original).

Se vacía la tabla antes de convertir porque los enteros corruptos (`4294967295`) no convierten
a un datetime válido bajo modo estricto. Al ser sesiones efímeras, el único efecto es que los
usuarios re-inician sesión **una vez**.

**Verificado en local (2026-08-05):**
- `migrate --all` la aplica (batch 31); en local ya estaba `timestamp` → la saltó sin borrar sesiones.
- Prueba end-to-end del camino de prod: `down()` dejó la columna en `int unsigned`; `up()` la
  convirtió a `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- `db:verify-schema` → OK (72 tablas).

**Efecto esperado al desplegar en prod** (vía `setup.php` o `migrate`):
- Detecta `int`, vacía `ci_sessions` (todos re-inician sesión una vez) y corrige la columna.
- A partir de ahí las sesiones expiran bien (2 h) y el GC solo borra las realmente vencidas.
- Recomendado correrlo en ventana de bajo tráfico.

**Migración auto-descubierta por `migrate --all` — no requiere cambios en `setup.sh`.**
