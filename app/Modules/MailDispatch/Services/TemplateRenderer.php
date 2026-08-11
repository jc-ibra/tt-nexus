<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

/**
 * Expande las variables de una plantilla de respuesta ({{requester_name}} y
 * compañía) con los datos de la conversación.
 *
 * Se usa en dos momentos:
 *  - Al insertar la plantilla en el compositor (los valores viajan a la vista y
 *    el reemplazo ocurre en el navegador, para que el agente vea el texto final
 *    y pueda editarlo antes de enviar).
 *  - Como red de seguridad al momento de responder, por si el agente escribió
 *    una variable a mano o editó el texto insertado.
 *
 * Ojo: no comparte código con AutogenService::renderTemplate(), que expande
 * otro juego de variables ({{ticket_id}}, {{titulo}}) sobre los acuses
 * automáticos de autogestión.
 */
final class TemplateRenderer
{
    /** Variables soportadas => descripción mostrada al administrar plantillas. */
    public const VARIABLES = [
        '{{requester_name}}'  => 'Nombre del solicitante',
        '{{requester_email}}' => 'Correo del solicitante',
        '{{asunto}}'          => 'Asunto del hilo',
        '{{folio}}'           => 'Folio GLPI ligado al hilo',
        '{{agent_name}}'      => 'Tu nombre',
    ];

    /**
     * Valores crudos (sin escapar) de cada variable para una conversación.
     *
     * @param array<string,mixed> $conv Fila de maildispatch_conversations.
     * @return array<string,string>     Mapa variable => valor.
     */
    public static function vars(array $conv, string $agentName = ''): array
    {
        return [
            '{{requester_name}}'  => self::requesterName($conv),
            '{{requester_email}}' => trim((string) ($conv['requester_email'] ?? '')),
            '{{asunto}}'          => trim((string) ($conv['subject'] ?? '')),
            '{{folio}}'           => trim((string) ($conv['glpi_folio'] ?? '')),
            '{{agent_name}}'      => trim($agentName),
        ];
    }

    /** Igual que vars(), con los valores escapados para insertarse en HTML. */
    public static function htmlVars(array $conv, string $agentName = ''): array
    {
        return array_map(
            static fn (string $v): string => esc($v, 'html'),
            self::vars($conv, $agentName)
        );
    }

    /**
     * Reemplaza las variables de $text. Cualquier variable desconocida se deja
     * intacta (no queremos borrar texto del agente por un typo).
     *
     * @param array<string,string> $vars Mapa de vars()/htmlVars().
     */
    public static function render(string $text, array $vars): string
    {
        return strtr($text, $vars);
    }

    /** Atajo para expandir un cuerpo HTML ya compuesto (red de seguridad). */
    public static function renderHtml(string $html, array $conv, string $agentName = ''): string
    {
        return self::render($html, self::htmlVars($conv, $agentName));
    }

    /**
     * Nombre del solicitante con respaldo: si el hilo no trae nombre, se usa la
     * parte local del correo (mejor "Hola jperez" que "Hola ,").
     */
    private static function requesterName(array $conv): string
    {
        $name = trim((string) ($conv['requester_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($conv['requester_email'] ?? ''));
        if ($email === '') {
            return '';
        }
        $local = strstr($email, '@', true);

        return $local === false ? $email : $local;
    }
}
