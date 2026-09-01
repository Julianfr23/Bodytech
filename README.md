# Motor de suscripciones

Prueba técnica — Desarrollador Fullstack PHP + React (Bodytech).

Backend desarrollado en **PHP 8.2+**, utilizando **Slim 4** como micro-framework, **PDO** para acceso directo a MySQL y **MySQL 8** como base de datos.

Frontend desarrollado en **React + Vite** como SPA que consume la API REST del backend.

## Estructura

```text
backend/     API PHP (Slim 4 + PDO)
frontend/    SPA en React (Vite)
```

## Funcionalidades implementadas

* CRUD de clientes.
* Creación y consulta de suscripciones.
* Motor de cobro basado en estado persistido en base de datos.
* Simulación de pasarela de pagos.
* Resultados de pasarela: aprobado, rechazado y timeout.
* Webhook HTTP para notificación de resultados.
* Webhook idempotente.
* Sistema de reintentos de cobro.
* Pausa automática después de tres intentos fallidos.
* Reloj simulado para probar el paso del tiempo.
* Soporte para ciclos mensuales y anuales.
* Frontend SPA en React.
* Proxy de Vite para comunicación con la API durante desarrollo.

## 1. Levantar el backend

### Requisitos

* PHP 8.2+
* Composer
* MySQL 8+

### Instalación

Desde la raíz del proyecto:

```bash
cd backend
composer install
```

Copia el archivo de variables de entorno:

**Windows PowerShell:**

```powershell
Copy-Item .env.example .env
```

**Linux/macOS:**

```bash
cp .env.example .env
```

Ajusta las credenciales de MySQL en `.env` si es necesario.

Crea la base de datos:

```bash
mysql -u root -p -e "CREATE DATABASE subscription_engine"
```

Ejecuta el esquema:

```bash
mysql -u root -p subscription_engine < database/schema.sql
```

Opcionalmente, carga los datos de prueba:

```bash
mysql -u root -p subscription_engine < database/seed.sql
```

Finalmente, inicia el servidor:

```bash
php -S localhost:8080 -t public
```

La API estará disponible en:

```text
http://localhost:8080/api/
```

> **Importante:** el simulador de pasarela se comunica con el webhook de la aplicación mediante HTTP y el motor de cobro se comunica con el simulador de pasarela de la misma forma. La URL base se configura mediante `APP_BASE_URL` en `.env`.
>
> Si cambias el puerto utilizado por `php -S`, actualiza `APP_BASE_URL` para que coincida.

## 2. Levantar el frontend

### Requisitos

* Node.js 18+

Desde la raíz del proyecto:

```bash
cd frontend
npm install
npm run dev
```

Abre:

```text
http://localhost:5173
```

Vite tiene configurado un proxy que reenvía las solicitudes `/api/*` hacia:

```text
http://localhost:8080
```

Por esta razón, durante el desarrollo el frontend no necesita conocer directamente el puerto del backend.

## 3. Flujo de punta a punta

1. Ve a **Clientes → Nuevo cliente** y crea un cliente.
2. Entra al detalle del cliente y selecciona **+ Nueva suscripción**.
3. Ve a **Motor de cobro**.
4. Presiona **Correr motor de cobro**.
5. Como la suscripción todavía no tiene un cobro exitoso, se genera el primer intento.
6. El motor envía el intento al simulador de pasarela.
7. La pasarela notifica el resultado mediante el webhook.
8. Entra al detalle de la suscripción para consultar el historial de intentos y su estado.

## 4. Simular el paso del tiempo

La aplicación utiliza un **reloj simulado** almacenado en la tabla `settings`.

La clase `App\Support\Clock` centraliza el manejo del tiempo utilizado por el motor de cobro. De esta manera, las pruebas no requieren modificar la fecha/hora del sistema operativo.

### Consultar la hora simulada

```http
GET /api/clock
```

### Adelantar el reloj

Ejemplo: adelantar 24 horas:

```http
POST /api/clock/advance
Content-Type: application/json

{
  "seconds": 86400
}
```

### Reiniciar el reloj

```http
POST /api/clock/reset
```

El frontend incluye botones para adelantar:

* 24 horas, para probar reintentos.
* 1 mes, para probar nuevos ciclos mensuales.
* 1 año, para probar nuevos ciclos anuales.

### Ejemplo de ciclo de reintentos

Forzar un rechazo en el primer intento:

```bash
curl -X POST localhost:8080/api/charge-engine/run \
  -d '{"force":"rechazado"}' \
  -H 'Content-Type: application/json'
```

Adelantar 24 horas:

```bash
curl -X POST localhost:8080/api/clock/advance \
  -d '{"seconds":86400}' \
  -H 'Content-Type: application/json'
```

Ejecutar nuevamente el motor:

```bash
curl -X POST localhost:8080/api/charge-engine/run \
  -d '{"force":"rechazado"}' \
  -H 'Content-Type: application/json'
```

Volver a adelantar 24 horas:

```bash
curl -X POST localhost:8080/api/clock/advance \
  -d '{"seconds":86400}' \
  -H 'Content-Type: application/json'
```

Ejecutar el motor por tercera vez:

```bash
curl -X POST localhost:8080/api/charge-engine/run \
  -d '{"force":"rechazado"}' \
  -H 'Content-Type: application/json'
```

Después del tercer intento fallido consecutivo, la suscripción pasa automáticamente a estado `pausada`.

## 5. Simulador de pasarela

El endpoint del simulador es:

```http
POST /api/gateway/charge
```

Por defecto, el simulador genera aleatoriamente:

* 60% aprobado
* 30% rechazado
* 10% timeout

También es posible forzar el resultado para facilitar las pruebas.

### Forzar resultado por request

Ejemplo:

```json
{
  "attempt_id": 12,
  "force": "timeout"
}
```

Los valores soportados son:

```text
aprobado
rechazado
timeout
```

### Forzar resultado mediante variable de entorno

También puede configurarse:

```env
GATEWAY_FORCE_RESULT=rechazado
```

Esta configuración aplica a todas las solicitudes mientras esté definida.

El endpoint del motor de cobro también acepta `force` y lo reenvía al simulador para permitir probar ciclos completos desde el frontend sin modificar el código.

## 6. Diseño del motor de cobro

El motor está diseñado para que el estado de los cobros sea **persistente e idempotente**.

Cada suscripción activa se evalúa consultando su último intento registrado en la base de datos. No se utilizan contadores ni trabajos pendientes almacenados únicamente en memoria.

### Nuevo ciclo

Si una suscripción nunca ha sido cobrada, o ya transcurrió el período correspondiente desde su último cobro exitoso:

* Mensual → un mes.
* Anual → un año.

se genera un nuevo intento `#1`.

### Reintentos

Si el último intento quedó:

* `fallido`, o
* `pendiente` debido a un timeout,

y han transcurrido al menos 24 horas simuladas, se genera el siguiente intento del mismo ciclo.

El ciclo permite hasta tres intentos.

### Pausa automática

Si el tercer intento falla, la suscripción pasa automáticamente a:

```text
pausada
```

También se contempla el caso de un intento pendiente que permanece sin respuesta durante más de 24 horas.

### Comunicación con la pasarela

El motor de cobro se comunica mediante HTTP con el simulador de pasarela.

Cuando la pasarela obtiene un resultado, realiza una solicitud HTTP al webhook:

```http
POST /api/webhooks/gateway
```

El caso `timeout` deliberadamente no genera una notificación, permitiendo probar el flujo de intentos pendientes.

### Idempotencia del webhook

El webhook verifica el estado del intento antes de modificarlo.

Si recibe una notificación para un intento que ya fue resuelto, no vuelve a modificar su estado.

## 7. Uso de inteligencia artificial

Se utilizó **Claude** como herramienta de apoyo para acelerar la implementación, principalmente en tareas repetitivas y de estructura inicial:

* Controladores CRUD.
* Estructura inicial del frontend SPA.
* README.
* Código repetitivo.

El contexto utilizado incluía el enunciado completo de la prueba y las decisiones de diseño del motor de cobro, las cuales fueron definidas previamente.

Las decisiones arquitectónicas y de negocio fueron revisadas antes de incorporar el código generado.

### Decisiones y alternativas descartadas

#### Cola o JOB en memoria

La primera propuesta para los reintentos utilizaba una cola en memoria para programar el siguiente intento.

Se descartó porque el estado no sobreviviría al reinicio del proceso y no se ajustaba al enfoque requerido de recalcular el comportamiento a partir del estado persistido.

La implementación final consulta `charge_attempts` en cada ejecución del motor.

#### Laravel + Eloquent

También se consideró Laravel con Eloquent.

Se optó por **Slim 4 + PDO** porque proporciona una implementación más pequeña y explícita para esta prueba, permitiendo revisar directamente las consultas y separar claramente la lógica HTTP de la lógica de persistencia.

#### Modificación global de `date()`

Para el reloj simulado se consideró utilizar una extensión como `uopz` para modificar el comportamiento global de fecha/hora.

Se descartó por depender de una extensión adicional de PHP.

La solución final utiliza un offset persistido en la tabla `settings`, que permite controlar el tiempo simulado sin modificar el sistema operativo.

## 8. Mejoras futuras

Con más tiempo, se podrían incorporar las siguientes mejoras:

* Integración con una pasarela real, por ejemplo el sandbox de Wompi.
* Validación de firma/secreto en las notificaciones reales de la pasarela.
* Paginación en los listados de clientes y suscripciones.
* Suite de tests automatizados con PHPUnit, especialmente para la lógica de decisión del motor de cobro.
* Normalización de fechas a UTC para un entorno productivo distribuido.

## 9. Nota sobre el entorno de evaluación

El proyecto está pensado para ejecutarse localmente mediante:

```text
Backend  → http://localhost:8080
Frontend → http://localhost:5173
MySQL    → localhost:3306
```

La configuración específica de conexión se encuentra en `.env`.

**No incluir el archivo `.env` real en el repositorio.** Utilizar `.env.example` como referencia para la configuración.
