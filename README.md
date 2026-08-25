# Challenge MSI — Sistema de Reservas de Restaurante

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-Cache%20%2B%20Queue-DC382D?logo=redis&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)
![Pest](https://img.shields.io/badge/Tested%20with-Pest-B7178C)
![License](https://img.shields.io/badge/license-MIT-green)

Aplicación web de reservas de mesas para restaurante: el cliente elige **fecha, hora, cantidad de personas, ubicación y sección**; la solicitud se procesa de forma **asíncrona** mediante colas, combinando automáticamente las mesas disponibles según su capacidad.

---

## 📑 Tabla de Contenido

- [Descripción](#-descripción)
- [Características](#-características)
- [Tecnologías Utilizadas](#-tecnologías-utilizadas)
- [Arquitectura](#-arquitectura)
- [Prerrequisitos](#-prerrequisitos)
- [Instalación y Configuración](#-instalación-y-configuración)
- [API REST](#-api-rest)
- [Tests](#-tests)

## 💡 Descripción

Challenge MSI resuelve la gestión de reservas de un restaurante con múltiples ubicaciones (Salón, Terraza) divididas en secciones (Bar, Salón Principal, Jardín, Área Infantil).

El flujo es completamente **asíncrono**: al enviar el formulario, la petición devuelve un identificador de intento (`attempt_url`) y queda encolada en Redis. Un worker de **Horizon** procesa la reserva consultando la disponibilidad del turno, calculando la mejor combinación de mesas que cubra a los comensales y persistiendo el resultado. El frontend consulta periódicamente el estado del intento hasta obtener la confirmación (con las mesas asignadas) o el rechazo (con su motivo).

La lógica de negocio vive desacoplada del framework en módulos de dominio (`src/features`), comunicándose con Laravel únicamente a través de puertos (interfaces) e implementaciones concretas (adapters).

## ✨ Características

- **Reservas asíncronas** — Alta en cola con respuesta `202 Accepted` + *polling* del estado del intento (`pending → confirmed | rejected | failed`) con idempotencia garantizada.
- **Combinador óptimo de mesas** — Algoritmo *best-fit decreasing* que combina hasta N mesas respetando capacidades mínimas y desperdicio mínimo; rechazo temprano si ningún conjunto alcanza el cupo.
- **Ubicaciones y secciones obligatorias** — Selección en cascada (la sección depende de la ubicación); una reserva nunca carece de sección ni hace *fallback* entre ubicaciones.
- **Cache de disponibilidad en Redis** — Lectura de disponibilidad por sección con TTL de 120s e invalidación dirigida por eventos al confirmar/rechazar reservas.
- **Agenda sin filtros** — Página `/agenda` que lista todas las reservas (ubicación, sección y mesas) usando **una única consulta SQL optimizada** con `GROUP_CONCAT`.
- **API REST v1** — Endpoints para crear reservas, consultar intentos y listar reservas por fecha.
- **UI responsive** — Bootstrap 5 con componentes Blade reutilizables (atoms/molecules/organisms), modal de resultado y validaciones traducidas al español.

## 🛠 Tecnologías Utilizadas

| Capa | Tecnología |
|------|------------|
| Backend | PHP 8.3+ · Laravel 13 |
| Colas & Workers | Laravel Horizon · Redis |
| Base de Datos | MySQL 8.4 |
| Cache | Redis (TTL + invalidación por eventos) |
| Frontend | Blade · Bootstrap 5.3 · Vanilla JS (ES modules) |
| Build | Vite 8 · Sass |
| Testing | Pest 5 · Mockery · Faker |
| Entorno | Laravel Sail (Docker) |

## 🏗 Arquitectura

```
├── app/                        # Capa Laravel (HTTP, Jobs, Listeners, Infraestructura)
│   ├── Http/Controllers/       #   API v1 + páginas web
│   ├── Infrastructure/         #   Adapters: readers/writers Eloquent, cache y SQL puro
│   ├── Jobs/                   #   CreateReservationJob (cola `reservations`)
│   └── Listeners/              #   Invalidación de cache y logging de rechazos
├── resources/
│   ├── js/reservations.js      # Flujo asíncrono del formulario (fetch + polling)
│   └── views/                  # Componentes atom/molecule/organism + pages
├── src/
│   ├── features/Reservation/   # CreateReservation (handler/command/port/result)
│   │                           # ValidateReservation (horarios, cutoff, slot)
│   ├── features/Table/         # CombinateTable (algoritmo de combinación)
│   └── test/                   # Tests unitarios del dominio (in-memory doubles)
└── tests/Feature/              # Tests de integración HTTP, Jobs, Events, Infrastructure
```

El dominio no conoce Eloquent ni HTTP: los handlers dependen de interfaces (`AvailabilityReaderInterface`, `ReservationWriterInterface`) que los adapters implementan, lo que permite testear la lógica con dobles en memoria.

## 📋 Prerrequisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (con WSL2 en Windows)
- [Composer](https://getcomposer.org/) 2.x
- Node.js 20+ y npm (para compilar assets)
- Git

> Alternativa: PHP 8.3+, Composer, MySQL 8.4 y Redis locales sin Docker.

## 🚀 Instalación y Configuración

### 1. Clonar el repositorio

```bash
git clone <url-del-repositorio> challenge-msi
cd challenge-msi
```

### 2. Instalar dependencias PHP

```bash
composer install
```

### 3. Configurar el entorno

```bash
cp .env.example .env
```

Variables clave (ya provistas por defecto):

```dotenv
APP_PORT=9090            # Puerto público de la app
DB_CONNECTION=mysql
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
```

Generar la clave de aplicación:

```bash
./vendor/bin/sail artisan key:generate
```

### 4. Levantar los contenedores

```bash
./vendor/bin/sail up -d
```

Esto inicia cuatro servicios: **app** (nginx+php-fpm en el puerto `9090`), **horizon** (worker de la cola `reservations`), **mysql** (8.4) y **redis**.

### 5. Migraciones y datos semilla

```bash
./vendor/bin/sail artisan migrate --seed
```

El seeder crea las ubicaciones y secciones junto con sus mesas:

| Ubicación | Sección | Mesas |
|-----------|---------|-------|
| Salón | Bar | S01–S03 |
| Salón | Salón Principal | S04–S10 |
| Terraza | Jardín | T01–T03 |
| Terraza | Área Infantil | T04–T05 |

### 6. Compilar assets del frontend

```bash
npm install && npm run build     # producción
npm run dev                      # desarrollo (watch)
```

### 7. Verificar la instalación

Abrir <http://localhost:9090> y crear una reserva de prueba; el modal confirmará las mesas asignadas. La agenda completa está disponible en <http://localhost:9090/agenda>.

## 🔌 API REST

Base URL: `http://localhost:9090/api/v1`

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/reserve` | Crea un intento de reserva y lo encola. Devuelve `202` con `attempt_url`. Rate-limit 30/min. |
| `GET` | `/reserve/attempts/{uuid}` | Estado del intento y resultado (mesas asignadas o motivo del rechazo). |
| `GET` | `/reservations?date=Y-m-d` | Reservas de una fecha con ubicación, sección y mesas (consulta SQL única). |

Ejemplo de creación:

```bash
curl -X POST http://localhost:9090/api/v1/reserve \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"reservation_date":"2026-08-28","reservation_time":"20:00","reservation_people_count":"4","reservation_location":"1","reservation_section":"1"}'
```

## 🧪 Tests

La suite incluye pruebas unitarias del dominio (con dobles en memoria) y de integración (HTTP, jobs, eventos, infraestructura):

```bash
./vendor/bin/sail pest          # suite completa
./vendor/bin/sail pest --parallel
./vendor/bin/sail pint          # estilo de código
```
