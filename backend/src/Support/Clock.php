<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use PDO;

/**
 * Reloj simulado del sistema.
 *
 * En vez de manipular la fecha del sistema operativo (poco practico y nada
 * portable), guardamos un "offset" en segundos en la tabla `settings`.
 * Clock::now() siempre devuelve la hora real del servidor MAS ese offset.
 *
 * Para simular que pasan 24h y probar el reintento de un cobro fallido,
 * o que pasa un mes/año para que toque cobrar de nuevo, se avanza el offset
 * con POST /api/clock/advance (ver README, seccion "Simular el paso del
 * tiempo"). El offset es compartido por toda la app: todo el motor de
 * cobro razona en base a esta misma hora simulada.
 */
final class Clock
{
    private const KEY = 'clock_offset_seconds';

    public static function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->modify('+' . self::offsetSeconds() . ' seconds');
    }

    public static function offsetSeconds(): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => self::KEY]);
        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    public static function advanceSeconds(int $seconds): int
    {
        $newOffset = self::offsetSeconds() + $seconds;

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE `value` = :value'
        );
        $stmt->execute(['key' => self::KEY, 'value' => (string) $newOffset]);

        return $newOffset;
    }

    public static function reset(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (:key, "0")
             ON DUPLICATE KEY UPDATE `value` = "0"'
        );
        $stmt->execute(['key' => self::KEY]);
    }
}
