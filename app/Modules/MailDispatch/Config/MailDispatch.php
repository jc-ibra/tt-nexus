<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Static maps for the MailDispatch module: conversation states (machine value
 * -> Spanish label + badge tone) and the Graph API base. UI copy is Spanish;
 * the stored/identifier values stay in the machine vocabulary.
 */
class MailDispatch extends BaseConfig
{
    /** Microsoft Graph v1.0 base URL. */
    public string $graphBase = 'https://graph.microsoft.com/v1.0';

    /** OAuth2 token endpoint template (tenant is interpolated). */
    public string $tokenEndpoint = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token';

    /** Scope for client-credentials against Graph. */
    public string $graphScope = 'https://graph.microsoft.com/.default';

    /** Conversation state value => human label (Spanish). */
    public array $statusLabels = [
        'nueva'            => 'Nueva',
        'asignada'         => 'Asignada',
        'en_atencion'      => 'En atención',
        'respondida'       => 'Respondida',
        'esperando_agente' => 'Esperando agente',
        'cerrada'          => 'Cerrada',
    ];

    /** Conversation state value => design-system badge tone. */
    public array $statusTones = [
        'nueva'            => 'info',
        'asignada'         => 'info',
        'en_atencion'      => 'warning',
        'respondida'       => 'success',
        'esperando_agente' => 'warning',
        'cerrada'          => 'neutral',
    ];

    /** States an agent may set manually from the detail view. */
    public array $manualStatuses = ['asignada', 'en_atencion', 'respondida', 'esperando_agente'];

    // -----------------------------------------------------------------------
    // Attachments
    // -----------------------------------------------------------------------

    /** Max total size of the files attached to a single reply (25 MB). */
    public int $maxTotalReplyBytes = 26214400;

    /** Max number of files attached to a single reply. */
    public int $maxReplyAttachments = 15;

    /**
     * Per-attachment cap when ingesting inbound mail. Attachments larger than
     * this are recorded as metadata but their content is not stored, to avoid
     * unbounded memory/disk use on the sync (0 = no cap).
     */
    public int $maxIngestAttachmentBytes = 26214400;

    /**
     * Extensions never served inline and never accepted on replies (executables
     * and scripts). Inbound copies are still stored but always download-only.
     */
    public array $blockedExtensions = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'cpl', 'jar',
        'js', 'jse', 'vbs', 'vbe', 'ws', 'wsf', 'wsh', 'ps1', 'psm1',
        'sh', 'php', 'phtml', 'phar', 'hta', 'reg', 'lnk',
    ];

    /** MIME types safe to render inline in the browser (else forced download). */
    public array $inlineSafeMimes = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp',
        'application/pdf', 'text/plain',
    ];
}
