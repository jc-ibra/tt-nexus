<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Controllers;

use CodeIgniter\Controller;

/**
 * Public, unauthenticated CSAT survey at /survey/{token}. Access is gated by
 * SurveyAccessFilter (enabled + rate limit + same-origin on POST). Mirrors the
 * structure of ServiceDesk's Landing controller: a plain Controller, no
 * session, all business rules live in the service.
 *
 *   GET  /survey/{token}   the form, or "esta encuesta ya está cerrada" / "no disponible"
 *   POST /survey/{token}   submits the answer (consumes one slot of the shared quota)
 */
class Survey extends Controller
{
    public function show(string $plainToken): string
    {
        $res = service('mailDispatchSurvey')->open($plainToken, $this->request->getIPAddress(), (string) $this->request->getUserAgent());

        return $this->render($plainToken, $res);
    }

    public function submit(string $plainToken): string
    {
        $rating   = (int) ($this->request->getPost('rating') ?? 0);
        $resolved = (string) ($this->request->getPost('resolved') ?? '');
        $comment  = (string) ($this->request->getPost('comment') ?? '');

        $res = service('mailDispatchSurvey')->submit(
            $plainToken,
            $rating,
            $resolved,
            $comment,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        if ($res['status'] === 'invalid') {
            // Re-render the form with the values the person already typed, plus
            // an error, instead of losing their answer to a validation slip.
            $open = service('mailDispatchSurvey')->resolve($plainToken);

            return view('App\Modules\MailDispatch\Views\public\survey_form', [
                'plainToken'   => $plainToken,
                'conversation' => $open['conversation'] ?? null,
                'error'        => 'Revisa tu respuesta: falta la calificación o si se resolvió tu solicitud.',
                'old'          => ['rating' => $rating, 'resolved' => $resolved, 'comment' => $comment],
            ]);
        }

        if ($res['status'] === 'ok') {
            return view('App\Modules\MailDispatch\Views\public\survey_closed', [
                'title'   => 'Gracias por tu respuesta',
                'message' => 'Tu opinión ya llegó al equipo de la mesa de ayuda.',
            ]);
        }

        return $this->render($plainToken, $res);
    }

    /** @param array{status:string, token?:array, conversation?:array} $res */
    private function render(string $plainToken, array $res): string
    {
        if ($res['status'] === 'ok') {
            return view('App\Modules\MailDispatch\Views\public\survey_form', [
                'plainToken'   => $plainToken,
                'conversation' => $res['conversation'],
                'error'        => null,
                'old'          => null,
            ]);
        }

        if ($res['status'] === 'full') {
            // No personal attribution, so the copy never implies "you already
            // answered" — it could be the person opening the link right now.
            return view('App\Modules\MailDispatch\Views\public\survey_closed', [
                'title'   => 'Esta encuesta ya está cerrada',
                'message' => 'Ya se alcanzó el número máximo de respuestas para este caso. Gracias por tu interés.',
            ]);
        }

        // 'unavailable' — vencido, revocado, inexistente o encuesta apagada:
        // siempre el mismo mensaje genérico, para no confirmar si un token existe.
        return view('App\Modules\MailDispatch\Views\public\survey_closed', [
            'title'   => 'Este enlace ya no está disponible',
            'message' => 'Si necesitas ayuda, responde el correo de la mesa de ayuda.',
        ]);
    }
}
