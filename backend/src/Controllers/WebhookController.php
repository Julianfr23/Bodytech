<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Clock;
use App\Support\Database;
use App\Support\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/webhooks/gateway
 *
 * Recibe la confirmacion del simulador (o de una pasarela real), actualiza
 * el intento correspondiente y refleja el resultado en la suscripcion.
 *
 * Es idempotente a proposito: si el intento ya estaba resuelto (exitoso o
 * fallido) no vuelve a tocar nada, para que una notificacion duplicada de
 * la pasarela no desordene el estado.
 */
final class WebhookController
{
    public function handle(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $attemptId = (int) ($data['attempt_id'] ?? 0);
        $result = $data['result'] ?? null;

        if ($attemptId <= 0 || !in_array($result, ['aprobado', 'rechazado'], true)) {
            return Json::error($response, 'attempt_id y result (aprobado|rechazado) son obligatorios');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM charge_attempts WHERE id = :id');
        $stmt->execute(['id' => $attemptId]);
        $attempt = $stmt->fetch();

        if (!$attempt) {
            return Json::error($response, 'El intento de cobro no existe', 404);
        }

        if ($attempt['status'] !== 'pendiente') {
            // Ya fue resuelto antes (webhook duplicado): no hacemos nada mas.
            return Json::write($response, ['message' => 'El intento ya estaba resuelto, no se modifico nada.']);
        }

        $now = Clock::now()->format('Y-m-d H:i:s');
        $newStatus = $result === 'aprobado' ? 'exitoso' : 'fallido';

        $pdo->beginTransaction();

        $update = $pdo->prepare(
            'UPDATE charge_attempts
             SET status = :status, gateway_response = :response, resolved_at = :resolved_at
             WHERE id = :id'
        );
        $update->execute([
            'status' => $newStatus,
            'response' => $result,
            'resolved_at' => $now,
            'id' => $attemptId,
        ]);

        if ($newStatus === 'exitoso') {
            $subUpdate = $pdo->prepare(
                'UPDATE subscriptions SET last_charge_at = :when WHERE id = :id'
            );
            $subUpdate->execute(['when' => $attempt['attempted_at'], 'id' => $attempt['subscription_id']]);
        } elseif ((int) $attempt['attempt_number'] >= 3) {
            // Se agotaron los 3 reintentos: la suscripcion pasa a pausada.
            $subUpdate = $pdo->prepare('UPDATE subscriptions SET status = "pausada" WHERE id = :id');
            $subUpdate->execute(['id' => $attempt['subscription_id']]);
        }

        $pdo->commit();

        return Json::write($response, ['message' => 'Intento actualizado', 'attempt_id' => $attemptId, 'status' => $newStatus]);
    }
}
