<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SubscriptionController
{
    private const PERIODICITIES = ['mensual', 'anual'];
    private const STATUSES = ['activa', 'pausada', 'cancelada'];

    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::connection();
        $status = $request->getQueryParams()['status'] ?? null;

        if ($status !== null) {
            if (!in_array($status, self::STATUSES, true)) {
                return Json::error($response, 'Estado invalido');
            }
            $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE status = :status ORDER BY created_at DESC');
            $stmt->execute(['status' => $status]);
        } else {
            $stmt = $pdo->query('SELECT * FROM subscriptions ORDER BY created_at DESC');
        }

        return Json::write($response, $stmt->fetchAll());
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $subscription = $this->find((int) $args['id']);

        if (!$subscription) {
            return Json::error($response, 'Suscripcion no encontrada', 404);
        }

        $pdo = Database::connection();
        $attempts = $pdo->prepare(
            'SELECT * FROM charge_attempts WHERE subscription_id = :id ORDER BY attempted_at DESC, attempt_number DESC'
        );
        $attempts->execute(['id' => $args['id']]);
        $subscription['charge_attempts'] = $attempts->fetchAll();

        return Json::write($response, $subscription);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data, requireCustomer: true);

        if ($errors) {
            return Json::error($response, 'Datos invalidos', 422, ['fields' => $errors]);
        }

        $pdo = Database::connection();

        $customerExists = $pdo->prepare('SELECT id FROM customers WHERE id = :id');
        $customerExists->execute(['id' => $data['customer_id']]);
        if (!$customerExists->fetch()) {
            return Json::error($response, 'Datos invalidos', 422, ['fields' => ['customer_id' => 'El cliente no existe']]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions (customer_id, name, description, price, periodicity, status)
             VALUES (:customer_id, :name, :description, :price, :periodicity, :status)'
        );
        $stmt->execute([
            'customer_id' => $data['customer_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'periodicity' => $data['periodicity'],
            'status' => $data['status'] ?? 'activa',
        ]);

        $id = (int) $pdo->lastInsertId();

        return $this->show($request, $response, ['id' => $id])->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->find((int) $args['id'])) {
            return Json::error($response, 'Suscripcion no encontrada', 404);
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data, requireCustomer: false);

        if ($errors) {
            return Json::error($response, 'Datos invalidos', 422, ['fields' => $errors]);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE subscriptions
             SET name = :name, description = :description, price = :price, periodicity = :periodicity
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'periodicity' => $data['periodicity'],
            'id' => $args['id'],
        ]);

        return $this->show($request, $response, $args);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        if (!$this->find((int) $args['id'])) {
            return Json::error($response, 'Suscripcion no encontrada', 404);
        }

        $data = (array) $request->getParsedBody();
        $status = $data['status'] ?? null;

        if (!in_array($status, self::STATUSES, true)) {
            return Json::error($response, 'Datos invalidos', 422, ['fields' => ['status' => 'Estado invalido']]);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE subscriptions SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $args['id']]);

        return $this->show($request, $response, $args);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM subscriptions WHERE id = :id');
        $stmt->execute(['id' => $args['id']]);

        if ($stmt->rowCount() === 0) {
            return Json::error($response, 'Suscripcion no encontrada', 404);
        }

        return $response->withStatus(204);
    }

    private function find(int $id): array|false
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    /** @return array<string,string> */
    private function validate(array $data, bool $requireCustomer): array
    {
        $errors = [];

        if ($requireCustomer && empty($data['customer_id'])) {
            $errors['customer_id'] = 'Debes indicar a que cliente pertenece';
        }

        if (empty(trim((string) ($data['name'] ?? '')))) {
            $errors['name'] = 'Este campo es obligatorio';
        }

        if (!isset($data['price']) || !is_numeric($data['price']) || (float) $data['price'] <= 0) {
            $errors['price'] = 'El precio debe ser un numero mayor a cero';
        }

        if (!in_array($data['periodicity'] ?? null, self::PERIODICITIES, true)) {
            $errors['periodicity'] = 'Debe ser mensual o anual';
        }

        if (isset($data['status']) && !in_array($data['status'], self::STATUSES, true)) {
            $errors['status'] = 'Estado invalido';
        }

        return $errors;
    }
}
