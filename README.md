# Motor de suscripciones

Prueba técnica — Desarrollador Fullstack PHP + React (Bodytech).

Backend en PHP 8.2 con **Slim 4** (micro-framework, sin ORM: PDO directo para
que las consultas queden explícitas) + MySQL. Frontend en **React** (Vite)
como SPA que consume la API.

## Estructura

```
backend/    API PHP (Slim 4 + PDO)
frontend/   SPA en React (Vite)
```

## 1. Levantar el backend

Requisitos: PHP 8.2+, Composer, MySQL 8.

```bash
cd backend
composer install
cp .env.example .env        # ajusta credenciales de MySQL si hace falta
mysql -u root -p -e "CREATE DATABASE subscription_engine"
mysql -u root -p subscription_engine < database/schema.sql
mysql -u root -p subscription_engine < database/seed.sql   # datos de prueba, opcional

php -S localhost:8080 -t public
```

La API queda en `http://localhost:8080/api/...`.

> **Importante:** el simulador de pasarela llama a su propio webhook por
> HTTP (`APP_BASE_URL` en `.env`), y el motor de cobro llama al simulador de
> la misma forma. Si cambias el puerto en el que corres `php -S`, actualiza
> `APP_BASE_URL` en `.env` para que coincida.

## 2. Levantar el frontend

Requisitos: Node 18+.

```bash
cd frontend
npm install
npm run dev
```

Abre `http://localhost:5173`. Vite tiene un proxy configurado (`vite.config.js`)
que reenvía `/api/*` a `http://localhost:8080`, así que el frontend no
necesita saber el puerto del backend en desarrollo.

## 3. Flujo de punta a punta para probar

1. Crea un cliente en **Clientes → Nuevo cliente**.
2. Entra al cliente y crea una suscripción (**+ Nueva suscripción**).
3. Ve a **Motor de cobro** y presiona **Correr motor de cobro**: como la
   suscripción nunca se ha cobrado, se genera su primer intento de inmediato
   y se resuelve contra el simulador de pasarela.
4. Entra al detalle de la suscripción para ver el intento reflejado en su
   historial (y su estado, si fue aprobado/rechazado).

## Simular el paso del tiempo

En vez de tocar la fecha del sistema operativo, la app tiene un **reloj
simulado** guardado en la tabla `settings` (`App\Support\Clock`). Todo el
motor de cobro razona con `Clock::now()`, nunca con la hora real directamente.

- `GET /api/clock` — consulta la hora simulada actual.
- `POST /api/clock/advance { "seconds": 86400 }` — adelanta el reloj.
- `POST /api/clock/reset` — lo vuelve a poner en la hora real.

Desde el frontend, la pantalla **Motor de cobro** tiene botones para
adelantar 24 horas (para forzar un reintento), 1 mes o 1 año (para forzar
que a una suscripción mensual/anual ya le toque cobrar de nuevo).

Ejemplo de ciclo completo de reintentos por línea de comandos:

```bash
curl -X POST localhost:8080/api/charge-engine/run -d '{"force":"rechazado"}' -H 'Content-Type: application/json'
curl -X POST localhost:8080/api/clock/advance -d '{"seconds":86400}' -H 'Content-Type: application/json'
curl -X POST localhost:8080/api/charge-engine/run -d '{"force":"rechazado"}' -H 'Content-Type: application/json'
curl -X POST localhost:8080/api/clock/advance -d '{"seconds":86400}' -H 'Content-Type: application/json'
curl -X POST localhost:8080/api/charge-engine/run -d '{"force":"rechazado"}' -H 'Content-Type: application/json'
# tras el 3er intento fallido seguido, la suscripción queda "pausada"
```

## Forzar el resultado del simulador de pasarela

El simulador (`POST /api/gateway/charge`) responde por defecto
60% aprobado / 30% rechazado / 10% timeout. Hay dos formas de forzarlo:

1. **Por request**, mandando `force` en el body:
   `{"attempt_id": 12, "force": "timeout"}`.
2. **Por variable de entorno**, `GATEWAY_FORCE_RESULT=rechazado` en `.env`,
   que aplica a toda la app mientras esté seteada.

El motor de cobro (`POST /api/charge-engine/run`) acepta el mismo parámetro
`force` y lo reenvía a cada intento que genera en esa corrida — así se puede
forzar todo un ciclo de prueba desde la pantalla **Motor de cobro** del
frontend sin tocar el backend.

## Cómo funciona el motor de cobro (resumen del diseño)

- Cada suscripción **activa** se evalúa mirando únicamente su **último
  intento de cobro** en base de datos (nunca un contador en memoria), así que
  correr el motor varias veces seguidas sin que avance el reloj no genera
  intentos duplicados.
- Si nunca se le ha cobrado, o ya pasó un mes/año desde su último cobro
  **exitoso**, se genera el intento #1 de un ciclo nuevo.
- Si el último intento quedó **pendiente** (timeout de la pasarela) o
  **fallido**, y ya pasaron 24h simuladas, se genera el siguiente intento del
  mismo ciclo (hasta el intento #3).
- Si el intento #3 también falla (o queda pendiente más de 24h sin
  respuesta), la suscripción pasa a **pausada** automáticamente.
- El simulador de pasarela notifica el resultado llamando de verdad, por
  HTTP, al webhook (`App\Support\GatewayClient`) — igual que lo haría una
  pasarela real — salvo en el caso de timeout, donde deliberadamente no
  llama a nadie.
- El webhook es **idempotente**: si le llega una notificación para un
  intento que ya estaba resuelto, no vuelve a tocar el estado.

## Uso de inteligencia artificial

Usé Claude para acelerar la escritura de este proyecto (controladores CRUD
repetitivos, el esqueleto del SPA en React, este README). Contexto que le di
en cada caso: el enunciado completo de la prueba y las decisiones de diseño
del motor de cobro (esquema de tablas, cómo simular el reloj, cómo modelar
los reintentos) ya definidas por mí antes de generar código.

Qué descarté y por qué:

- La primera versión que generó el ciclo de reintentos usaba un `JOB`/cola en
  memoria para reprogramar el siguiente intento. Lo descarté porque no
  sobrevive a que el proceso PHP termine entre requests (cada request de
  `php -S` es un proceso nuevo) y porque la prueba pide que el motor sea
  re-ejecutable de forma idempotente mirando el estado persistido, no un
  temporizador en memoria. Por eso el diseño final recalcula todo a partir
  de la fila más reciente en `charge_attempts` cada vez que corre.
- Se sugirió usar Laravel con Eloquent para el CRUD. Lo descarté por tiempo:
  con Slim + PDO directo el código es más corto de revisar línea por línea
  en la entrevista, y la prueba no exige un framework completo.
- Para el "reloj simulado" se sugirió mockear `date()` globalmente con una
  extensión tipo `uopz`. Lo descarté porque depende de una extensión que no
  siempre está instalada; el offset guardado en la tabla `settings` funciona
  en cualquier instalación de PHP estándar.

Todo el código fue revisado y entendido por mí antes de incluirlo; puedo
explicar cualquier decisión en la entrevista.

## Pendientes / lo que haría distinto con más tiempo

- No implementé la integración real con una pasarela (punto opcional):
  con más tiempo integraría el sandbox de Wompi, exponiendo el mismo
  `POST /api/webhooks/gateway` como su webhook de confirmación y firmando/
  validando la notificación con el secreto de eventos de Wompi.
- No hay paginación en los listados de clientes/suscripciones; para un
  volumen real de datos la agregaría en `index()` de ambos controladores.
- No hay tests automatizados. Con más tiempo cubriría con PHPUnit
  principalmente `ChargeEngineController::decide()`, que es donde vive toda
  la lógica de negocio del ciclo de reintentos.
- El manejo de zona horaria asume que el servidor MySQL y PHP están en la
  misma zona horaria; en producción normalizaría todo a UTC.
