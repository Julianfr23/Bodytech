<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CustomerController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::connection();
        $customers = $pdo->query('SELECT * FROM customers ORDER BY created_at DESC')->fetchAll();

        return Json::write($response, $customers);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
        $stmt->execute(['id' => $args['id']]);
        $customer = $stmt->fetch();

        if (!$customer) {
            return Json::error($response, 'Cliente no encontrado', 404);
        }

        $subs = $pdo->prepare('SELECT * FROM subscriptions WHERE customer_id = :id ORDER BY created_at DESC');
        $subs->execute(['id' => $args['id']]);
        $customer['subscriptions'] = $subs->fetchAll();

        return Json::write($response, $customer);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);

        if ($errors) {
            return Json::error($response, 'Datos invalidos', 422, ['fields' => $errors]);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO customers (name, email, document, phone) VALUES (:name, :email, :document, :phone)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'document' => $data['document'],
            'phone' => $data['phone'],
        ]);

        $id = (int) $pdo->lastInsertId();

        return $this->show($request, $response, ['id' => $id])->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT id FROM customers WHERE id = :id');
        $exists->execute(['id' => $args['id']]);

        if (!$exists->fetch()) {
            return Json::error($response, 'Cliente no encontrado', 404);
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);

        if ($errors) {
            return Json::error($response, 'Datos invalidos', 422, ['fields' => $errors]);
        }

        $stmt = $pdo->prepare(
            'UPDATE customers SET name = :name, email = :email, document = :document, phone = :phone WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'document' => $data['document'],
            'phone' => $data['phone'],
            'id' => $args['id'],
        ]);

        return $this->show($request, $response, $args);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        $stmt->execute(['id' => $args['id']]);

        if ($stmt->rowCount() === 0) {
            return Json::error($response, 'Cliente no encontrado', 404);
        }

        return $response->withStatus(204);
    }

    /** @return array<string,string> */
    private function validate(array $data): array
    {
        $errors = [];

        foreach (['name', 'email', 'document', 'phone'] as $field) {
            if (empty(trim((string) ($data[$field] ?? '')))) {
                $errors[$field] = 'Este campo es obligatorio';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Correo invalido';
        }

        return $errors;
    }
}
