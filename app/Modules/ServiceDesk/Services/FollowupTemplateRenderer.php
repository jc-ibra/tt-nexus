<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

/**
 * Placeholder renderer for the auto-seguimiento email/GLPI-note templates.
 * Own, small variable set (distinct from MailDispatch's TemplateRenderer and
 * AutogenService template — see docs discussion): unknown placeholders are
 * left intact, same convention as the rest of the platform's templating.
 */
class FollowupTemplateRenderer
{
    /** @var array<string,string> placeholder => description, for the admin UI */
    public const VARIABLES = [
        '{{folio}}'        => 'Número de folio (ticket) en GLPI',
        '{{asunto}}'       => 'Título del ticket',
        '{{categoria}}'    => 'Categoría GLPI del ticket',
        '{{asignado}}'     => 'Nombre del técnico asignado',
        '{{solicitante}}'  => 'Nombre del solicitante (agente que generó el ticket)',
        '{{estado}}'       => 'Estado actual del ticket',
        '{{dias_abierto}}' => 'Días transcurridos desde la apertura',
    ];

    /**
     * @param array{id:int,title:string,category:string,assignee_name:string,
     *              requester_name:string,status_label:string,age_days:int} $ticket
     * @return array<string,string>
     */
    public function vars(array $ticket): array
    {
        return [
            '{{folio}}'        => (string) ($ticket['id'] ?? ''),
            '{{asunto}}'       => (string) ($ticket['title'] ?? ''),
            '{{categoria}}'    => (string) ($ticket['category'] ?? ''),
            '{{asignado}}'     => (string) ($ticket['assignee_name'] ?? ''),
            '{{solicitante}}'  => (string) ($ticket['requester_name'] ?? ''),
            '{{estado}}'       => (string) ($ticket['status_label'] ?? ''),
            '{{dias_abierto}}' => (string) ($ticket['age_days'] ?? ''),
        ];
    }

    /** @param array<string,string> $vars */
    public function render(string $text, array $vars): string
    {
        return strtr($text, $vars);
    }
}
