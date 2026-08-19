<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Provisioning\Services\GlpiCatalogService;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;

/**
 * Resolución nombre <-> id contra el GLPI vivo, con cachés por proceso.
 *
 * Extraído de TicketBulkImporter para que el alta masiva y la actualización
 * masiva compartan EXACTAMENTE las mismas reglas de resolución (una categoría
 * se busca igual al crear que al planchar) y para que un lote no repita miles
 * de consultas idénticas.
 *
 * Los mapas inversos (id -> nombre) existen solo para el reporte de cambios del
 * actualizador: sin ellos la columna CAMBIOS mostraría ids crudos en lugar de
 * las etiquetas que el operador escribió en el Excel.
 */
class GlpiValueResolver
{
    /** @var array<string,int> "tabla\0NOMBRE" => id */
    private array $dropdownCache = [];
    /** @var array<string,string> "tabla\0id" => nombre */
    private array $dropdownNameCache = [];
    /** @var array<string,int>|null NOMBRE => id */
    private ?array $categoryMap = null;
    /** @var array<int,string>|null id => nombre */
    private ?array $categoryNames = null;
    /** @var array<string,int>|null "APELLIDO NOMBRE" => id */
    private ?array $userMap = null;
    /** @var array<int,string>|null id => "Apellido Nombre" */
    private ?array $userNames = null;
    /** @var array<int,string>|null category id => etiqueta CLIENTE */
    private ?array $clienteMap = null;

    public function __construct(
        private GlpiDbConnection $glpiDb,
        private GlpiCatalogService $catalogs,
        private ServiceDeskCategoryMapModel $categoryMapModel,
    ) {}

    // ------------------------------------------------------------------
    // Dropdowns del plugin de campos adicionales
    // ------------------------------------------------------------------

    /**
     * Id del valor de catálogo, creándolo si el admin lo habilitó. 0 = no resuelto.
     */
    public function dropdownId(?string $table, ?string $name, bool $autocreate): int
    {
        if ($table === null || $name === null || $name === '') {
            return 0;
        }
        $key = $table . "\0" . mb_strtoupper($name);
        if (isset($this->dropdownCache[$key])) {
            return $this->dropdownCache[$key];
        }

        $existing = $this->catalogs->findByName($table, $name);
        if ($existing !== null) {
            return $this->dropdownCache[$key] = (int) $existing['id'];
        }

        if ($autocreate) {
            $res = $this->catalogs->ensureValue($table, $name);
            if ($res->success) {
                return $this->dropdownCache[$key] = (int) ($res->data['id'] ?? 0);
            }
        }

        return $this->dropdownCache[$key] = 0;
    }

    /**
     * Etiqueta de un valor de catálogo por id (para el reporte de cambios).
     */
    public function dropdownName(?string $table, int $id): string
    {
        if ($table === null || $id <= 0) {
            return '';
        }
        $key = $table . "\0" . $id;
        if (isset($this->dropdownNameCache[$key])) {
            return $this->dropdownNameCache[$key];
        }

        $db = $this->glpiDb->connection();
        if (! $db->tableExists($table)) {
            return $this->dropdownNameCache[$key] = '';
        }
        $row = $db->table($table)->select('name')->where('id', $id)->get()->getRowArray();

        return $this->dropdownNameCache[$key] = (string) ($row['name'] ?? '');
    }

    // ------------------------------------------------------------------
    // Categorías ITIL
    // ------------------------------------------------------------------

    public function categoryId(?string $name): int
    {
        if ($name === null || $name === '') {
            return 0;
        }
        $this->loadCategories();

        return $this->categoryMap[mb_strtoupper(trim($name))] ?? 0;
    }

    public function categoryName(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $this->loadCategories();

        return $this->categoryNames[$id] ?? '';
    }

    private function loadCategories(): void
    {
        if ($this->categoryMap !== null) {
            return;
        }
        $this->categoryMap   = [];
        $this->categoryNames = [];

        $db = $this->glpiDb->connection();
        if (! $db->tableExists('glpi_itilcategories')) {
            return;
        }
        // completename incluye la ruta padre > hijo, que es como el template
        // presenta las categorías; name es el respaldo si la columna no existe.
        $cols    = $db->getFieldNames('glpi_itilcategories');
        $nameCol = in_array('completename', $cols, true) ? 'completename' : 'name';

        foreach ($db->table('glpi_itilcategories')->select("id, {$nameCol} AS n")->get()->getResultArray() as $r) {
            $label = trim((string) $r['n']);
            $key   = mb_strtoupper($label);
            if ($key !== '') {
                $this->categoryMap[$key]              = (int) $r['id'];
                $this->categoryNames[(int) $r['id']]  = $label;
            }
        }
    }

    // ------------------------------------------------------------------
    // Usuarios de GLPI
    // ------------------------------------------------------------------

    public function userId(?string $displayName): int
    {
        if ($displayName === null || $displayName === '') {
            return 0;
        }
        $this->loadUsers();

        return $this->userMap[mb_strtoupper(trim($displayName))] ?? 0;
    }

    public function userName(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $this->loadUsers();

        return $this->userNames[$id] ?? '';
    }

    private function loadUsers(): void
    {
        if ($this->userMap !== null) {
            return;
        }
        $this->userMap   = [];
        $this->userNames = [];

        $db = $this->glpiDb->connection();
        if (! $db->tableExists('glpi_users')) {
            return;
        }
        foreach ($db->table('glpi_users')->select('id, realname, firstname')->where('is_active', 1)->get()->getResultArray() as $r) {
            $label = trim(trim((string) $r['realname']) . ' ' . trim((string) $r['firstname']));
            $key   = mb_strtoupper($label);
            if ($key !== '') {
                $this->userMap[$key]             = (int) $r['id'];
                $this->userNames[(int) $r['id']] = $label;
            }
        }
    }

    // ------------------------------------------------------------------
    // CLIENTE (mapeo categoría -> etiqueta, definido por el SuperAdmin)
    // ------------------------------------------------------------------

    public function clienteForCategoryName(?string $categoryName): string
    {
        return $this->clienteForCategoryId($this->categoryId($categoryName));
    }

    public function clienteForCategoryId(int $categoryId): string
    {
        if ($categoryId <= 0) {
            return '';
        }
        if ($this->clienteMap === null) {
            $this->clienteMap = $this->categoryMapModel->clienteMap();
        }

        return $this->clienteMap[$categoryId] ?? '';
    }
}
