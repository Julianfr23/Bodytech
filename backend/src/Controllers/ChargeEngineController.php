<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Clock;
use App\Support\Database;
use App\Support\GatewayClient;
use App\Support\Json;
use DateInterval;
use DateTimeImmutable;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/charge-engine/run
 *
 * Este es el corazon de la prueba. Cada vez que se ejecuta:
 *
 *   1. Recorre las suscripciones ACTIVAS.
 *   2. Para cada una, mira su ultimo intento de cobro (si tiene) y decide,
 *      usando la hora simulada (Clock::now), si:
 *        a) le toca iniciar un cobro nuevo (nunca se le ha cobrado, o ya
 *           paso un mes/año desde el ultimo cobro EXITOSO),
 *        b) le toca REINTENTAR el cobro del ciclo actual (el intento
 *           anterior fallo o se quedo en timeout hace mas de 24h y todavia
 *           no lleva 3 intentos),
 *        c) o si no le toca hacer nada todavia.
 *   3. Por cada intento nuevo, lo crea en estado "pendiente" y llama de
 *      inmediato al simulador de pasarela (punto 4), que a su vez llamara
 *      al webhook (punto 5) para resolverlo -- salvo que la pasarela
 *      simule un timeout, en cuyo caso el intento se queda pendiente y el
 *      propio motor lo tratara como fallido la proxima vez que corra y ya
 *      hayan pasado 24h.
 *
 * El motor es seguro de correr varias veces seguidas: cada decision se toma
 * mirando el estado actual en base de datos, nunca un contador en memoria,
 * asi que dos ejecuciones seguidas sin que haya pasado tiempo simplemente no
 * generan intentos duplicados.
 */
final class ChargeEngineController
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_WAIT_HOURS = 24;

    public function run(Request $request, Response $response): Response
    {
        $now = Clock::now();
        $pdo = Database::connection();
        $body = (array) $request->getParsedBody();
        // Permite forzar el resultado de TODOS los cobros generados en esta
        // corrida, util para pruebas manuales desde el frontend.
        $force = $body['force'] ?? null;

        $subscriptions = $pdo->query(
            "SELECT * FROM subscriptions WHERE status = 'activa'"
        )->fetchAll();

        $generated = [];

        foreach ($subscriptions as $subscription) {
            $attempt = $this->decide($pdo, $subscription, $now);

            if ($attempt !== null) {
                $generated[] = $this->createAttemptAndCharge($pdo, $subscription, $attempt, $now, $force);
            }
        }

        return Json::write($response, [
            'ran_at' => $now->format('Y-m-d H:i:s'),
            'subscriptions_evaluated' => count($subscriptions),
            'attempts_generated' => count($generated),
            'attempts' => $generated,
        ]);
    }

    /**
     * Devuelve ['cycle_started_at' => DateTimeImmutable, 'attempt_number' => int]
     * si a esta suscripcion le toca un intento de cobro ahora mismo, o null
     * si todavia no le toca.
     */
    private function decide(PDO $pdo, array $subscription, DateTimeImmutable $now): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM charge_attempts WHERE subscription_id = :id
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $subscription['id']]);
        $last = $stmt->fetch();

        // Nunca se le ha cobrado: le toca el primer intento ya mismo.
        if (!$last) {
            return ['cycle_started_at' => $now, 'attempt_number' => 1];
        }

        if ($last['status'] === 'exitoso') {
            $nextDue = $this->addPeriod(new DateTimeImmutable($last['attempted_at']), $subscription['periodicity']);

            return $now >= $nextDue ? ['cycle_started_at' => $now, 'attempt_number' => 1] : null;
        }

        // status pendiente o fallido: puede que le toque reintentar.
        $attemptedAt = new DateTimeImmutable($last['attempted_at']);
        $retryAt = $attemptedAt->add(new DateInterval('PT' . self::RETRY_WAIT_HOURS . 'H'));

        if ($now < $retryAt) {
            // Si esta pendiente y aun no se cumplen las 24h, seguimos
            // esperando una respuesta de la pasarela (no es timeout todavia).
            return null;
        }

        // Pasaron 24h sin resolverse (timeout) o fallo explicitamente.
        if ((int) $last['attempt_number'] >= self::MAX_ATTEMPTS) {
            // Ya se agoto el tercer intento; si seguia "pendiente" (timeout
            // nunca resuelto) lo cerramos como fallido y pausamos aqui.
            if ($last['status'] === 'pendiente') {
                $this->resolveAsTimedOutFailure($pdo, $last, $now);
            }

            return null;
        }

        if ($last['status'] === 'pendiente') {
            // Se agoto el tiempo de espera del intento pendiente: se marca
            // fallido por timeout y se agenda el reintento en el mismo ciclo.
            $this->resolveAsTimedOutFailure($pdo, $last, $now);
        }

        return [
            'cycle_started_at' => new DateTimeImmutable($last['cycle_started_at']),
            'attempt_number' => (int) $last['attempt_number'] + 1,
        ];
    }

    private function resolveAsTimedOutFailure(PDO $pdo, array $attempt, DateTimeImmutable $now): void
    {
        $stmt = $pdo->prepare(
            "UPDATE charge_attempts
             SET status = 'fallido', gateway_response = 'timeout', resolved_at = :resolved_at
             WHERE id = :id AND status = 'pendiente'"
        );
        $stmt->execute(['resolved_at' => $now->format('Y-m-d H:i:s'), 'id' => $attempt['id']]);

        if ((int) $attempt['attempt_number'] >= self::MAX_ATTEMPTS) {
            $pdo->prepare('UPDATE subscriptions SET status = "pausada" WHERE id = :id')
                ->execute(['id' => $attempt['subscription_id']]);
        }
    }

    private function addPeriod(DateTimeImmutable $date, string $periodicity): DateTimeImmutable
    {
        return $periodicity === 'anual'
            ? $date->add(new DateInterval('P1Y'))
            : $date->add(new DateInterval('P1M'));
    }

    private function createAttemptAndCharge(PDO $pdo, array $subscription, array $decision, DateTimeImmutable $now, ?string $force): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO charge_attempts (subscription_id, cycle_started_at, attempt_number, status, attempted_at)
             VALUES (:subscription_id, :cycle_started_at, :attempt_number, "pendiente", :attempted_at)'
        );
        $stmt->execute([
            'subscription_id' => $subscription['id'],
            'cycle_started_at' => $decision['cycle_started_at']->format('Y-m-d H:i:s'),
            'attempt_number' => $decision['attempt_number'],
            'attempted_at' => $now->format('Y-m-d H:i:s'),
        ]);

        $attemptId = (int) $pdo->lastInsertId();

        // Llama al simulador de pasarela en el mismo request para que el
        // ciclo "generar intento -> cobrar -> webhook" quede completo de
        // punta a punta con solo ejecutar el motor.
        $payload = ['attempt_id' => $attemptId];
        if ($force !== null) {
            $payload['force'] = $force;
        }
        $this->callGatewaySimulator($payload);

        return [
            'attempt_id' => $attemptId,
            'subscription_id' => $subscription['id'],
            'attempt_number' => $decision['attempt_number'],
        ];
    }

    private function callGatewaySimulator(array $payload): void
    {
        $baseUrl = rtrim($_ENV['APP_BASE_URL'] ?? 'http://localhost:8080', '/');
        $ch = curl_init($baseUrl . '/api/gateway/charge');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
