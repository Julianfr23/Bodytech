<?php

declare(strict_types=1);

use App\Controllers\ChargeEngineController;
use App\Controllers\ClockController;
use App\Controllers\CustomerController;
use App\Controllers\GatewaySimulatorController;
use App\Controllers\SubscriptionController;
use App\Controllers\WebhookController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$app = AppFactory::create();

// Body parsing (JSON) para Slim.
$app->addBodyParsingMiddleware();

// CORS simple para que el React SPA (otro puerto) pueda consumir la API.
$app->add(function (Request $request, $handler): Response {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->addErrorMiddleware(true, true, true);

// ----- Clientes -----
$app->get('/api/customers', [CustomerController::class, 'index']);
$app->post('/api/customers', [CustomerController::class, 'store']);
$app->get('/api/customers/{id}', [CustomerController::class, 'show']);
$app->put('/api/customers/{id}', [CustomerController::class, 'update']);
$app->delete('/api/customers/{id}', [CustomerController::class, 'destroy']);

// ----- Suscripciones -----
$app->get('/api/subscriptions', [SubscriptionController::class, 'index']);
$app->post('/api/subscriptions', [SubscriptionController::class, 'store']);
$app->get('/api/subscriptions/{id}', [SubscriptionController::class, 'show']);
$app->put('/api/subscriptions/{id}', [SubscriptionController::class, 'update']);
$app->patch('/api/subscriptions/{id}/status', [SubscriptionController::class, 'updateStatus']);
$app->delete('/api/subscriptions/{id}', [SubscriptionController::class, 'destroy']);

// ----- Motor de cobro -----
$app->post('/api/charge-engine/run', [ChargeEngineController::class, 'run']);

// ----- Simulador de pasarela + webhook -----
$app->post('/api/gateway/charge', [GatewaySimulatorController::class, 'charge']);
$app->post('/api/webhooks/gateway', [WebhookController::class, 'handle']);

// ----- Reloj simulado -----
$app->get('/api/clock', [ClockController::class, 'show']);
$app->post('/api/clock/advance', [ClockController::class, 'advance']);
$app->post('/api/clock/reset', [ClockController::class, 'reset']);

$app->run();
