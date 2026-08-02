<?php

/**
 * setup.php - Equivalente web de setup.sh para hostings SIN acceso a terminal.
 *
 * Levanta migraciones y seeders iniciales de tt-nexus arrancando el framework
 * CodeIgniter 4 (Boot::bootWorker) sin despachar una petición HTTP normal.
 *
 * USO:
 *   1. Sube este archivo a la carpeta public/ del hosting (junto a index.php).
 *   2. Define SETUP_TOKEN en tu .env (o como variable de entorno del hosting):
 *        SETUP_TOKEN = un-valor-secreto-largo-y-aleatorio
 *   3. Visita:  https://TU-DOMINIO/setup.php?token=un-valor-secreto-largo-y-aleatorio
 *        - &only=migrations   corre solo migraciones
 *        - &only=seeders      corre solo seeders
 *        (sin 'only' corre ambos)
 *   4. IMPORTANTE: BORRA este archivo del servidor cuando termines.
 *
 * No contempla modo Docker (a diferencia de setup.sh).
 */

// ── Visibilidad de errores y sin límite de tiempo ───────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(0);

// ── Check versión de PHP (igual que index.php) ──────────────────────────────────
$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    exit(sprintf('Se requiere PHP %s o superior. Versión actual: %s', $minPhpVersion, PHP_VERSION));
}

// ── Arranque del framework CodeIgniter (sin routear) ────────────────────────────
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';

// bootWorker() carga .env, constantes, autoloader, namespaces de modulos y
// servicios, y devuelve la app SIN ejecutar el kernel HTTP. NO ejecuta
// migraciones ni seeders, así que es seguro arrancar ANTES de validar el token
// (y así podemos leer SETUP_TOKEN desde el .env que carga bootWorker).
CodeIgniter\Boot::bootWorker($paths);

// ── Guard por token ─────────────────────────────────────────────────────────────
// El token secreto se lee de la variable de entorno SETUP_TOKEN (definida en el
// .env de CI4 o como variable de entorno real del hosting). NUNCA se hardcodea.
$expectedToken = (string) (env('SETUP_TOKEN') ?: '');
if ($expectedToken === '') {
    header('HTTP/1.1 500 Internal Server Error', true, 500);
    exit('500 - SETUP_TOKEN no está configurado. Define SETUP_TOKEN en tu .env antes de usar este script.');
}
$token = $_GET['token'] ?? '';
if (! is_string($token) || ! hash_equals($expectedToken, $token)) {
    header('HTTP/1.1 403 Forbidden', true, 403);
    exit('403 Forbidden - token invalido o ausente.');
}

// ── Seeders (clase => etiqueta), mismo orden que setup.sh ───────────────────────
$SEEDERS = [
    ['App\Database\Seeds\CoreSeeder', 'CoreSeeder'],
    ['App\Modules\Employees\Database\Seeders\EmployeesModuleSeeder', 'EmployeesModuleSeeder'],
    ['App\Modules\HelpdeskSupervisor\Database\Seeders\HelpdeskSupervisorModuleSeeder', 'HelpdeskSupervisorModuleSeeder'],
    ['App\Modules\HelpdeskSupervisor\Database\Seeders\CoordinatorMapSeeder', 'CoordinatorMapSeeder'],
    ['App\Modules\KPIsOperativos\Database\Seeders\KPIsOperativosModuleSeeder', 'KPIsOperativosModuleSeeder'],
    ['App\Modules\KPIsOperativos\Database\Seeders\GlpiCoordinatorsSeeder', 'GlpiCoordinatorsSeeder'],
    ['App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder', 'MailboxesModuleSeeder'],
    ['App\Modules\Provisioning\Database\Seeders\ProvisioningModuleSeeder', 'ProvisioningModuleSeeder'],
    ['App\Modules\Provisioning\Database\Seeders\MsLicensesSeeder', 'MsLicensesSeeder'],
    ['App\Modules\ServiceDesk\Database\Seeders\ServiceDeskModuleSeeder', 'ServiceDeskModuleSeeder'],
    ['App\Modules\MailDispatch\Database\Seeders\MailDispatchModuleSeeder', 'MailDispatchModuleSeeder'],
    ['App\Modules\TechBot\Database\Seeders\TechBotModuleSeeder', 'TechBotModuleSeeder'],
];

$only = $_GET['only'] ?? '';
$runMigrations = ($only === '' || $only === 'migrations');
$runSeeders    = ($only === '' || $only === 'seeders');

// ── Salida HTML tipo consola ────────────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>tt-nexus · Setup web</title>
<style>
  :root { color-scheme: dark; }
  body { background:#0d1117; color:#c9d1d9; font:14px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; margin:0; padding:24px; }
  .wrap { max-width:880px; margin:0 auto; }
  h1 { font-size:18px; color:#fff; margin:0 0 4px; }
  .sub { color:#8b949e; margin:0 0 20px; }
  .step { color:#58a6ff; font-weight:700; margin:22px 0 8px; border-top:1px solid #21262d; padding-top:14px; }
  .line { padding:2px 0; }
  .ok   { color:#3fb950; }
  .warn { color:#d29922; }
  .err  { color:#f85149; }
  .info { color:#8b949e; }
  .box  { background:#161b22; border:1px solid #21262d; border-radius:8px; padding:14px 16px; }
  table { border-collapse:collapse; width:100%; margin-top:8px; font-size:12.5px; }
  th,td { text-align:left; padding:4px 10px; border-bottom:1px solid #21262d; }
  th { color:#8b949e; font-weight:600; }
  .done { margin-top:24px; padding:14px 16px; border-radius:8px; background:#0f2417; border:1px solid #1f6f3f; color:#3fb950; font-weight:700; }
  .danger { margin-top:12px; padding:12px 16px; border-radius:8px; background:#2d1315; border:1px solid #6f2226; color:#f85149; }
</style>
</head>
<body>
<div class="wrap">
<h1>tt-nexus · Setup inicial (web)</h1>
<p class="sub">modo: local · sin terminal</p>
<div class="box">
<?php
// Flush progresivo para ver el avance en vivo.
function emit(string $html): void
{
    echo $html . "\n";
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }
    @flush();
}

function step(string $t): void { emit('<div class="step">==> ' . htmlspecialchars($t) . '</div>'); }
function ok(string $t): void   { emit('<div class="line ok">  [OK] ' . htmlspecialchars($t) . '</div>'); }
function info(string $t): void { emit('<div class="line info">  --> ' . htmlspecialchars($t) . '</div>'); }
function warn(string $t): void { emit('<div class="line warn">  [!]  ' . htmlspecialchars($t) . '</div>'); }
function errl(string $t): void { emit('<div class="line err">  [ERR] ' . htmlspecialchars($t) . '</div>'); }

// ── Entorno ─────────────────────────────────────────────────────────────────────
step('Verificando entorno');
info('PHP version: ' . PHP_VERSION);
info('ENVIRONMENT: ' . (defined('ENVIRONMENT') ? ENVIRONMENT : 'desconocido'));

try {
    $db = \Config\Database::connect();
    $db->initialize();
    ok('Conexión a base de datos: ' . $db->getDatabase());
} catch (\Throwable $e) {
    errl('No se pudo conectar a la base de datos: ' . $e->getMessage());
    emit('</div><div class="danger">Revisa las credenciales de BD en tu archivo .env y vuelve a intentar.</div></div></body></html>');
    exit;
}

// ── Baseline (reconciliación de historial) ──────────────────────────────────────
// Antes de migrar, reconciliamos la tabla `migrations` con el esquema real. Si esta
// BD ya tiene tablas/columnas creadas a mano (levantamiento manual en staging/prod)
// pero sin su registro de migración, `latest()` intentaría recrearlas y fallaría con
// "already exists". Aquí marcamos como aplicadas SÓLO las migraciones cuyo efecto ya
// existe físicamente (misma lógica que `php spark db:baseline`). Seguro e idempotente:
// en una BD limpia no hace nada porque las tablas aún no existen.
if ($runMigrations) {
    step('Baseline (reconciliación de historial)');
    try {
        $runner = \Config\Services::migrations();
        $runner->setNamespace(null);
        $found = $runner->findMigrations();

        $applied = [];
        foreach ($runner->getHistory('') as $h) {
            $applied[$runner->getObjectUid($h)] = true;
        }

        $existingTables = array_flip($db->listTables());
        $baselined      = 0;
        $batch          = ((int) ($db->table('migrations')->selectMax('batch', 'b')->get()->getRow()->b ?? 0)) + 1;
        $nowTs          = \CodeIgniter\I18n\Time::now()->getTimestamp();

        foreach ($found as $m) {
            if (isset($applied[$m->uid])) {
                continue;
            }

            // Verifica que el efecto de la migración ya exista antes de marcarla.
            $code    = (string) @file_get_contents((string) $m->path);
            $present = false;
            if (preg_match("/createTable\(\s*'([a-zA-Z0-9_]+)'/", $code, $mm)) {
                $present = isset($existingTables[$mm[1]]);
            } elseif (preg_match("/addColumn\(\s*'([a-zA-Z0-9_]+)'/", $code, $mm) && isset($existingTables[$mm[1]])) {
                preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*\[/", $code, $cm);
                $present = true;
                foreach (array_unique($cm[1]) as $col) {
                    if (! $db->fieldExists($col, $mm[1])) {
                        $present = false;
                        break;
                    }
                }
            }

            if ($present) {
                $db->table('migrations')->insert([
                    'version'   => $m->version,
                    'class'     => $m->class,
                    'group'     => 'default',
                    'namespace' => $m->namespace,
                    'time'      => $nowTs,
                    'batch'     => $batch,
                ]);
                $baselined++;
                info('Baseline: ' . $m->version . ' ' . substr((string) strrchr($m->class, '\\'), 1));
            }
        }

        ok($baselined === 0 ? 'Historial ya sincronizado (nada que reconciliar).' : "Reconciliadas {$baselined} migración(es) ya aplicadas físicamente.");
    } catch (\Throwable $e) {
        warn('Baseline no pudo completarse: ' . $e->getMessage() . ' (continúo con migraciones).');
    }
}

// ── Migraciones ─────────────────────────────────────────────────────────────────
// Corremos TODAS las migraciones en orden cronológico global (setNamespace(null)),
// igual que `php spark migrate --all`. Esto auto-descubre todos los módulos y
// respeta el orden entre módulos (FKs cruzadas), evitando el fallo de "olvidé
// agregar el módulo a la lista".
$migrationErrors = [];
if ($runMigrations) {
    step('Migraciones');
    $migrate = \Config\Services::migrations();

    try {
        $migrate->setNamespace(null);
        $migrate->latest();
        ok('Todas las migraciones aplicadas (todos los módulos)');
    } catch (\Throwable $e) {
        // Un error de migración NO es benigno: se muestra completo y se registra.
        $migrationErrors['migrate --all'] = $e->getMessage();
        errl('migrate --all — ' . $e->getMessage());
    }
}

// ── Seeders ─────────────────────────────────────────────────────────────────────
if ($runSeeders) {
    step('Seeders');
    $seeder = \Config\Database::seeder();

    foreach ($SEEDERS as [$class, $label]) {
        info('Seeder: ' . $label);
        try {
            $seeder->call($class);
            ok($label);
        } catch (\Throwable $e) {
            warn($label . ' — ' . $e->getMessage() . ' (puede ser que ya existan los datos)');
        }
    }
}

// ── Verificación de tablas críticas ─────────────────────────────────────────────
// Detecta el caso "migración registrada en la tabla `migrations` pero la tabla
// física NO existe" (típico de un dump/import parcial en una BD de prueba). En
// ese estado `latest()` reporta OK pero la tabla nunca se crea.
step('Verificación de tablas críticas');
// Auto-derivamos la lista esperada leyendo los createTable()/CREATE TABLE de cada
// migración (misma lógica que `php spark db:verify-schema`). Sin lista manual que
// mantener: si agregas un módulo o migración, la verificación se entera sola.
$EXPECTED_TABLES = [];
foreach (glob(APPPATH . 'Modules/*/Database/Migrations/*.php') ?: [] as $file) {
    $code   = (string) @file_get_contents($file);
    $module = basename(dirname($file, 3));
    $origin = $module . '/' . basename($file);
    if (preg_match_all("/createTable\(\s*'([a-zA-Z0-9_]+)'/", $code, $m)) {
        foreach ($m[1] as $t) {
            $EXPECTED_TABLES[$t] = $origin;
        }
    }
    if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $code, $m)) {
        foreach ($m[1] as $t) {
            $EXPECTED_TABLES[$t] = $origin;
        }
    }
}
ksort($EXPECTED_TABLES);

$hasMissingTables = false;
try {
    $existing = array_flip($db->listTables());
    $missing  = [];
    foreach ($EXPECTED_TABLES as $table => $origin) {
        if (! isset($existing[$table])) {
            $missing[$table] = $origin;
        }
    }

    if ($missing === []) {
        ok(sprintf('Esquema OK: las %d tablas esperadas existen.', count($EXPECTED_TABLES)));
    } else {
        $hasMissingTables = true;
        foreach ($missing as $table => $origin) {
            errl('FALTA tabla: ' . $table . '  (' . $origin . ')');
        }
    }
} catch (\Throwable $e) {
    warn('No se pudo verificar tablas: ' . $e->getMessage());
}

if ($hasMissingTables) {
    emit('<div class="danger">Hay tablas faltantes. Si el error de arriba fue "Table ... already exists", '
        . 'la tabla <code>migrations</code> está desincronizada con el esquema real (una migración ya aplicada '
        . 'no está registrada y bloquea a las siguientes). NO borres registros a ciegas: revisa el estado de '
        . 'migraciones abajo y reconcilia registrando manualmente las migraciones ya aplicadas.</div>');
}

// ── Estado de migraciones ───────────────────────────────────────────────────────
step('Estado de migraciones');
try {
    $rows = $db->table('migrations')
        ->select('class, namespace, batch, version')
        ->orderBy('id', 'ASC')
        ->get()
        ->getResultArray();

    if ($rows === []) {
        info('La tabla migrations está vacía.');
    } else {
        emit('<table><thead><tr><th>Batch</th><th>Namespace</th><th>Clase</th><th>Version</th></tr></thead><tbody>');
        foreach ($rows as $r) {
            emit('<tr><td>' . htmlspecialchars((string) $r['batch'])
                . '</td><td>' . htmlspecialchars((string) $r['namespace'])
                . '</td><td>' . htmlspecialchars((string) $r['class'])
                . '</td><td>' . htmlspecialchars((string) $r['version']) . '</td></tr>');
        }
        emit('</tbody></table>');
    }
} catch (\Throwable $e) {
    warn('No se pudo leer la tabla migrations: ' . $e->getMessage());
}

emit('</div>'); // cierra .box
if ($migrationErrors !== [] || $hasMissingTables) {
    emit('<div class="danger">Setup terminó CON ERRORES. Revisa las líneas [ERR] de arriba y aplica la remediación antes de usar la app.</div>');
} else {
    emit('<div class="done">Setup completo.</div>');
}
emit('<div class="danger">Por seguridad, BORRA este archivo (public/setup.php) del servidor ahora que terminaste.</div>');
?>
</div>
</body>
</html>
