<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Clock;
use App\Support\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Pequeño panel de control del reloj simulado (ver App\Support\Clock).
 * El frontend usa esto para mostrar "hoy" segun la app y para dar botones
 * de "avanzar 24h" / "avanzar 1 mes" al probar los reintentos y los cobros.
 */
final class ClockController
{
    public function show(Request $request, Response $response): Response
    {
        return Json::write($response, [
            'now' => Clock::now()->format('Y-m-d H:i:s'),
            'offset_seconds' => Clock::offsetSeconds(),
        ]);
    }

    public function advance(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $seconds = (int) ($data['seconds'] ?? 0);

        if ($seconds <= 0) {
            return Json::error($response, 'Debes indicar "seconds" (entero positivo)');
        }

        $newOffset = Clock::advanceSeconds($seconds);

        return Json::write($response, [
            'now' => Clock::now()->format('Y-m-d H:i:s'),
            'offset_seconds' => $newOffset,
        ]);
    }

    public function reset(Request $request, Response $response): Response
    {
        Clock::reset();

        return Json::write($response, [
            'now' => Clock::now()->format('Y-m-d H:i:s'),
            'offset_seconds' => 0,
        ]);
    }
}
