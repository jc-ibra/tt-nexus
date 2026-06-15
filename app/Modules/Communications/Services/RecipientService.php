<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Communications\Models\RecipientModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class RecipientService
{
    private const ALLOWED_MIME = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    private const MAX_BYTES    = 5_242_880; // 5 MB

    public function __construct(private RecipientModel $model) {}

    public function paginate(int $perPage = 20): array
    {
        $recipients = $this->model->paginate($perPage);
        $pager      = $this->model->pager;

        return compact('recipients', 'pager');
    }

    public function total(): int
    {
        return $this->model->countAll();
    }

    public function findById(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function create(array $data): ServiceResult
    {
        $rules = [
            'name'  => 'required|max_length[120]',
            'email' => 'required|valid_email|is_unique[comms_recipients.email]',
        ];

        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $id = $this->model->insert([
            'name'   => trim($data['name']),
            'email'  => strtolower(trim($data['email'])),
            'area'   => isset($data['area']) && $data['area'] !== '' ? trim($data['area']) : null,
            'status' => $data['status'] ?? 'active',
        ]);

        if ($id === false) {
            return ServiceResult::fail('Error al crear el destinatario.');
        }

        return ServiceResult::ok($this->model->find((int) $id));
    }

    public function update(int $id, array $data): ServiceResult
    {
        if ($this->model->find($id) === null) {
            return ServiceResult::fail('Destinatario no encontrado.');
        }

        $rules = [
            'name'  => 'required|max_length[120]',
            'email' => "required|valid_email|is_unique[comms_recipients.email,id,{$id}]",
        ];

        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $this->model->update($id, [
            'name'   => trim($data['name']),
            'email'  => strtolower(trim($data['email'])),
            'area'   => isset($data['area']) && $data['area'] !== '' ? trim($data['area']) : null,
            'status' => $data['status'] ?? 'active',
        ]);

        return ServiceResult::ok($this->model->find($id));
    }

    public function destroy(int $id): ServiceResult
    {
        if ($this->model->find($id) === null) {
            return ServiceResult::fail('Destinatario no encontrado.');
        }

        $this->model->delete($id);

        return ServiceResult::ok();
    }

    public function importFromFile(UploadedFile $file, ?int $listId = null): ServiceResult
    {
        if (! $file->isValid()) {
            return ServiceResult::fail('El archivo no es válido: ' . $file->getErrorString());
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return ServiceResult::fail('El archivo excede el tamaño máximo de 5 MB.');
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_MIME, true) && ! str_ends_with(strtolower($file->getName()), '.csv')) {
            return ServiceResult::fail("Tipo de archivo no permitido ({$mime}). Sube un CSV.");
        }

        $path   = $file->getTempName();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ServiceResult::fail('No se pudo leer el archivo.');
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return ServiceResult::fail('El archivo está vacío o no tiene encabezados.');
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);
        // Strip UTF-8 BOM from first column if present (common in Excel-exported CSVs)
        if (isset($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }

        if (! in_array('name', $headers, true) || ! in_array('email', $headers, true)) {
            fclose($handle);

            return ServiceResult::fail('El CSV debe tener columnas "name" y "email". Columnas encontradas: ' . implode(', ', $headers));
        }

        $nameIdx  = array_search('name',  $headers, true);
        $emailIdx = array_search('email', $headers, true);
        $areaIdx  = array_search('area',  $headers, true);

        $imported    = 0;
        $skipped     = 0;
        $errors      = [];
        $row         = 1;
        $newIds      = [];

        $this->model->db->transStart();

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;

            if (count($cols) < 2) {
                $errors[] = ['row' => $row, 'reason' => 'Fila incompleta'];
                $skipped++;
                continue;
            }

            $name  = trim($cols[$nameIdx] ?? '');
            $email = strtolower(trim($cols[$emailIdx] ?? ''));
            $area  = $areaIdx !== false ? trim($cols[$areaIdx] ?? '') : null;

            if ($name === '' || $email === '') {
                $errors[] = ['row' => $row, 'reason' => 'Nombre o correo vacío'];
                $skipped++;
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $row, 'reason' => "Correo inválido: {$email}"];
                $skipped++;
                continue;
            }

            $existing = $this->model->findByEmail($email);

            if ($existing !== null) {
                $errors[] = ['row' => $row, 'reason' => "Duplicado: {$email}"];
                $skipped++;

                if ($listId !== null) {
                    $newIds[] = $existing['id'];
                }

                continue;
            }

            $id = $this->model->insert([
                'name'   => $name,
                'email'  => $email,
                'area'   => $area !== '' ? $area : null,
                'status' => 'active',
            ]);

            if ($id !== false) {
                $imported++;
                $newIds[] = (int) $id;
            }
        }

        fclose($handle);
        $this->model->db->transComplete();

        if ($listId !== null && ! empty($newIds)) {
            $listModel = new \App\Modules\Communications\Models\RecipientListModel();
            $listModel->addRecipients($listId, $newIds);
        }

        return ServiceResult::ok([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }
}
