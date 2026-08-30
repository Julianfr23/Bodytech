<?php

declare(strict_types=1);

namespace App\Support;

/**
 * El simulador de pasarela (GatewaySimulatorController) usa esta clase para
 * notificar el resultado del cobro llamando de verdad, por HTTP, al webhook
 * expuesto en POST /api/webhooks/gateway -- tal como lo haria una pasarela
 * real. Si el resultado simulado es "timeout" nunca se llama a este metodo:
 * eso es justamente lo que se simula (la pasarela nunca respondio).
 */
final class GatewayClient
{
    public static function notifyWebhook(int $attemptId, string $result): bool
    {
        $baseUrl = rtrim($_ENV['APP_BASE_URL'] ?? 'http://localhost:8080', '/');
        $url = $baseUrl . '/api/webhooks/gateway';

        $payload = json_encode(['attempt_id' => $attemptId, 'result' => $result]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}
