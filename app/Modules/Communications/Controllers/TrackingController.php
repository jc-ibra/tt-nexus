<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Models\CommunicationLogModel;
use CodeIgniter\Controller;

class TrackingController extends Controller
{
    public function open(string $token): \CodeIgniter\HTTP\ResponseInterface
    {
        $logModel = new CommunicationLogModel();
        $log      = $logModel->findByToken($token);

        if ($log && $log['opened_at'] === null) {
            $logModel->markOpened((int) $log['id']);
        }

        // 1×1 transparent GIF — inline, no disk I/O
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return $this->response
            ->setHeader('Content-Type', 'image/gif')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setBody($gif);
    }
}
