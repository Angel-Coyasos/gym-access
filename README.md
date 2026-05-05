# Gym Access System

Monolito modular para gestión de acceso físico y engagement en una cadena de gimnasios. Implementa DDD, CQRS y el patrón Outbox sobre Laravel 13 + PostgreSQL + Redis.

Consulta [ARCHITECTURE.md](ARCHITECTURE.md) para el diseño completo de bounded contexts, comunicación inter-modular, ACL y CQRS.

---

## Stack

- PHP 8.3-FPM
- Laravel 13
- PostgreSQL 16
- Redis 7
- Nginx
- Supervisor (queue worker + outbox worker)

---

## Levantar el proyecto

**Requisitos:** Docker y Docker Compose instalados.

```bash
# 1. Clonar y preparar variables de entorno
cp .env.example .env

# 2. Registrar el dominio local en el archivo de hosts (requiere sudo)
#    Linux/Mac:
echo "127.0.0.1   gym-access" | sudo tee -a /etc/hosts
#    Windows (PowerShell como Administrador):
#    Add-Content C:\Windows\System32\drivers\etc\hosts "127.0.0.1   gym-access"

# 3. Levantar los contenedores
docker compose up -d

# 4. Instalar dependencias PHP
docker compose exec app composer install

# 5. Generar la app key
docker compose exec app php artisan key:generate

# 6. Correr las migraciones
docker compose exec app php artisan migrate

# 7. Verificar que los workers están corriendo
docker compose exec app php artisan queue:monitor engagement
```

La aplicación queda disponible en `http://gym-access`.

> Supervisor levanta automáticamente el queue worker y el outbox publisher (`outbox:publish --interval=5`). No se necesita ningún paso adicional para activarlos.

---

## Correr los tests

```bash
docker compose exec app php artisan test
```

Los tests cubren:
- Adaptador de API externa (`DummyJsonQuoteAdapterTest`) — DIP, fault handling, contrato JSON
- Handler de check-in (`RegisterCheckInHandlerTest`) — atomicidad Outbox, UUID, endpoint HTTP

---

## APIs

### POST /api/check-in

Registra el acceso físico de un miembro. Operación síncrona y transaccional.

```bash
curl -s -X POST http://gym-access/api/check-in \
  -H "Content-Type: application/json" \
  -d '{"member_id": "member-001"}' | jq
```

Respuesta exitosa (`201 Created`):

```json
{
    "check_in_id": "c17a10cf-48e3-4cde-867e-2474a3c17844",
    "member_id": "member-001",
    "checked_in_at": "2026-05-05 03:39:04"
}
```

Validación fallida (`422 Unprocessable Entity`):

```bash
curl -s -X POST http://gym-access/api/check-in \
  -H "Content-Type: application/json" \
  -d '{}' | jq
```

```json
{
    "message": "The member id field is required.",
    "errors": {
        "member_id": ["The member id field is required."]
    }
}
```

---

### GET /api/dashboard/{memberId}

Historial de accesos combinado con las frases motivacionales asignadas. Lee directamente del read model desnormalizado (`check_in_summaries`) — sin JOINs.

> El campo `quote` puede aparecer como `null` si el worker aún no procesó el evento (consistencia eventual). Refrescar en unos segundos.

```bash
curl -s http://gym-access/api/dashboard/member-001 | jq
```

Respuesta exitosa (`200 OK`):

```json
{
    "member_id": "member-001",
    "total": 3,
    "check_ins": [
        {
            "check_in_id": "c17a10cf-48e3-4cde-867e-2474a3c17844",
            "checked_in_at": "2026-05-05T03:39:04.000000Z",
            "quote": {
                "body": "The desire to know your own soul will end all other desires.",
                "author": "Rumi"
            }
        },
        {
            "check_in_id": "a83f21bc-...",
            "checked_in_at": "2026-05-04T22:13:00.000000Z",
            "quote": {
                "body": "Keep going.",
                "author": "Unknown"
            }
        }
    ]
}
```

Miembro sin check-ins (`200 OK`):

```json
{
    "member_id": "member-999",
    "total": 0,
    "check_ins": []
}
```

---

## Flujo end-to-end

```
POST /api/check-in
  └─ DB::transaction → INSERT check_ins + INSERT outbox_events
       └─ Respuesta 201 inmediata

[cada 5s] outbox:publish
  └─ SELECT outbox_events WHERE published_at IS NULL
       └─ HandleCheckInForEngagement::dispatch → Redis queue
       └─ UPDATE published_at = now()

[queue worker] HandleCheckInForEngagement
  └─ GET dummyjson.com/quotes/random
       └─ INSERT daily_motivations
       └─ INSERT check_in_summaries (read model)

GET /api/dashboard/{memberId}
  └─ SELECT * FROM check_in_summaries WHERE member_id = ?
```

---

## Monitoreo

```bash
# Estado de la queue
docker compose exec app php artisan queue:monitor engagement

# Logs en tiempo real (queue worker + outbox worker)
docker compose logs app -f

# Jobs fallidos
docker compose exec app php artisan queue:failed
```
