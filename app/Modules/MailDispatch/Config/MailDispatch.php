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
}
