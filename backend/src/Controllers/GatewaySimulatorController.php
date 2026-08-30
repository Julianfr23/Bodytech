<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\GatewayClient;
use App\Support\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Simulador de la pasarela de pago.
 *
 * No hay integracion real: este endpoint responde de forma aleatoria
 * (60% aprobado / 30% rechazado / 10% timeout) y notifica el resultado
 * llamando al webhook, exactamente como lo pide el punto 4 de la prueba.
 *
 * Se puede forzar el resultado de dos formas (ver README):
 *  - por request:  {"attempt_id": 12, "force": "rechazado"}
 *  - por variable de entorno GATEWAY_FORCE_RESULT (aplica a toda la app)
 */
final class GatewaySimulatorController
{
    private const RESULTS = ['aprobado', 'rechazado', 'timeout'];

    public function charge(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $attemptId = (int) ($data['attempt_id'] ?? 0);

        if ($attemptId <= 0) {
            return Json::error($response, 'attempt_id es obligatorio');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM charge_attempts WHERE id = :id');
        $stmt->execute(['id' => $attemptId]);
        if (!$stmt->fetch()) {
            return Json::error($response, 'El intento de cobro no existe', 404);
        }

        $forced = $data['force'] ?? ($_ENV['GATEWAY_FORCE_RESULT'] ?? null);
        $result = $this->resolveResult($forced);

        if ($result !== 'timeout') {
            // La pasarela "responde" llamando a nuestro propio webhook.
            GatewayClient::notifyWebhook($attemptId, $result);
        }
        // Si es timeout, deliberadamente no llamamos a nadie: el intento
        // queda pendiente hasta que el motor lo trate como fallido tras 24h.

        return Json::write($response, [
            'attempt_id' => $attemptId,
            'result' => $result,
            'note' => $result === 'timeout'
                ? 'La pasarela simulada no respondio (timeout). El intento queda pendiente.'
                : 'La pasarela notifico el resultado al webhook.',
        ]);
    }

    private function resolveResult(?string $forced): string
    {
        if ($forced !== null && $forced !== '' && in_array($forced, self::RESULTS, true)) {
            return $forced;
        }

        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 60 => 'aprobado',
            $roll <= 90 => 'rechazado',
            default => 'timeout',
        };
    }
}
