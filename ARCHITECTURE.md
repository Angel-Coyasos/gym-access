# Arquitectura: Gym Access System

## 1. Mapa de Bounded Contexts

El sistema se divide en dos contextos limitados que conviven en el mismo repositorio pero con fronteras lógicas estrictas:

```
┌─────────────────────────────────────────────────────────────────┐
│                     GYM ACCESS SYSTEM                           │
│                                                                 │
│  ┌──────────────────────────┐    ┌──────────────────────────┐   │
│  │     AccessControl        │    │       Engagement         │   │
│  │                          │    │                          │   │
│  │  OWNS:                   │    │  OWNS:                   │   │
│  │  - CheckIn (Aggregate)   │    │  - DailyMotivation       │   │
│  │  - check_ins (tabla)     │    │  - daily_motivations     │   │
│  │  - outbox_events (tabla) │    │  - check_in_summaries    │   │
│  │                          │    │    (Read Model)          │   │
│  │  FRONTERAS:              │    │                          │   │
│  │  No puede llamar a       │    │  FRONTERAS:              │   │
│  │  repositorios ni         │    │  No puede llamar a       │   │
│  │  servicios de Engagement │    │  repositorios ni         │   │
│  │  directamente            │    │  controladores de        │   │
│  │                          │    │  AccessControl           │   │
│  └──────────┬───────────────┘    └──────────────────────────┘   │
│             │                              ▲                     │
│             │  CheckInRegistered (evento)  │                     │
│             └──────── Redis Queue ─────────┘                     │
│                    (Outbox Pattern)                              │
└─────────────────────────────────────────────────────────────────┘
```

### Propiedad de datos

| Contexto | Tablas propias | Acceso a tablas ajenas |
|---|---|---|
| AccessControl | `check_ins`, `outbox_events` | Ninguno |
| Engagement | `daily_motivations`, `check_in_summaries` | Ninguno |
| Shared | `outbox_events` (solo lectura vía worker) | — |

---

## 2. Patrón de Comunicación Inter-modular

### Regla fundamental
**AccessControl nunca llama directamente a código de Engagement.** La comunicación cruzada ocurre exclusivamente a través del broker de mensajería (Redis), implementado con el patrón Outbox para garantizar atomicidad.

### Flujo completo

```
HTTP POST /api/check-in
        │
        ▼
CheckInController (AccessControl/Infrastructure)
        │
        ▼
RegisterCheckInHandler (AccessControl/Application)
        │
        ├── DB::transaction() ──────────────────────────────────────┐
        │   │                                                        │
        │   ├─ INSERT check_ins                                      │
        │   └─ INSERT outbox_events (published_at = NULL)            │
        │                         └── COMMIT ──────────────────────-┘
        │
        ▼ Respuesta 201 inmediata (sin esperar a Engagement)

[cada 5 segundos] outbox:publish (Artisan Command)
        │
        ├─ SELECT outbox_events WHERE published_at IS NULL
        ├─ HandleCheckInForEngagement::dispatch(payload) → Redis
        └─ UPDATE outbox_events SET published_at = now()

[queue worker] HandleCheckInForEngagement (Engagement/Application)
        │
        ├─ QuoteProviderInterface::getRandom()  ← Port
        │         └─ DummyJsonQuoteAdapter      ← Adapter (ACL)
        │                   └─ GET dummyjson.com/quotes/random
        │
        ├─ INSERT daily_motivations
        └─ INSERT check_in_summaries (Read Model actualizado)
```

### ¿Por qué Outbox y no `DB::afterCommit()`?

El patrón Outbox resuelve el problema de atomicidad entre la escritura en base de datos y la publicación al broker. Si el proceso cae después del COMMIT pero antes de publicar el evento, la entrada en `outbox_events` persiste y el worker la reintentará en el siguiente ciclo. Esto elimina los "accesos fantasma" (check-in guardado pero sin evento publicado).

---

## 3. Anti-Corruption Layer (ACL)

El dominio de Engagement define el concepto de "Frase" mediante su propia abstracción, completamente ignorante de HTTP, JSON o providers externos.

```
Dominio Engagement
┌──────────────────────────────────────────┐
│                                          │
│  interface QuoteProviderInterface        │
│  {                                       │
│      public function getRandom(): Quote; │
│  }                                       │
│                                          │
│  readonly class Quote                    │
│  {                                       │
│      public string $id;                  │
│      public string $body;                │
│      public string $author;              │
│  }                                       │
│                                          │
└──────────────────┬───────────────────────┘
                   │ implements
                   │
Infraestructura (fuera del dominio)
┌──────────────────▼───────────────────────┐
│                                          │
│  class DummyJsonQuoteAdapter             │
│       implements QuoteProviderInterface  │
│  {                                       │
│      // Conoce HTTP, JSON, Guzzle        │
│      // Traduce respuesta externa        │
│      // al modelo de dominio Quote       │
│  }                                       │
│                                          │
└──────────────────────────────────────────┘
```

### Manejo de fallos del proveedor externo

| Escenario | Excepción lanzada | Resultado |
|---|---|---|
| API devuelve 5xx | `QuoteProviderUnavailableException` | Job reintentado (backoff 30/120/300s) |
| Timeout de red | `QuoteProviderUnavailableException` | Job reintentado |
| JSON sin campos esperados | `QuoteProviderContractException` | Job reintentado |
| 3 fallos consecutivos | — | Job movido a `failed_jobs` |

**El check-in ya fue registrado antes de que ocurra cualquier fallo.** La caída del proveedor externo nunca afecta la ruta crítica de acceso físico.

---

## 4. Segregación de Modelos (CQRS)

### Modelo de Escritura (Write Model)

Optimizado para consistencia transaccional. Refleja el estado del negocio.

```sql
-- check_ins: registro autoritativo del acceso físico
CREATE TABLE check_ins (
    id UUID PRIMARY KEY,
    member_id VARCHAR(255) NOT NULL,
    checked_in_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP
);

-- daily_motivations: log de frases asignadas
CREATE TABLE daily_motivations (
    id UUID PRIMARY KEY,
    check_in_id UUID NOT NULL,
    member_id VARCHAR(255) NOT NULL,
    quote_id VARCHAR(255),
    quote_body TEXT NOT NULL,
    quote_author VARCHAR(255) NOT NULL,
    assigned_at TIMESTAMP NOT NULL
);

-- outbox_events: cola transaccional de eventos
CREATE TABLE outbox_events (
    id UUID PRIMARY KEY,
    aggregate_type VARCHAR(255),
    aggregate_id VARCHAR(255),
    event_type VARCHAR(255),
    payload JSONB,
    published_at TIMESTAMP NULL,  -- NULL = pendiente de publicar
    created_at TIMESTAMP
);
```

### Modelo de Lectura (Read Model)

Tabla desnormalizada, actualizada por el proyector (consumer asíncrono). El endpoint del dashboard hace un `SELECT` directo sin JOINs.

```sql
-- check_in_summaries: proyección desnormalizada para el dashboard
CREATE TABLE check_in_summaries (
    id UUID PRIMARY KEY,
    member_id VARCHAR(255) NOT NULL,    -- índice para lookups O(1)
    checked_in_at TIMESTAMP NOT NULL,
    quote_body TEXT NULL,               -- NULL hasta que Engagement procese
    quote_author VARCHAR(255) NULL,
    created_at TIMESTAMP
);
```

```
GET /api/dashboard/{memberId}
        │
        ▼
SELECT * FROM check_in_summaries
WHERE member_id = ?
ORDER BY checked_in_at DESC
        │
        ▼ Sin JOINs. Sin cálculos en tiempo de ejecución.
```

### Flujo de proyección

```
[Queue Worker] HandleCheckInForEngagement::handle()
    │
    ├─ Obtiene quote de DummyJsonQuoteAdapter
    ├─ INSERT daily_motivations (write model)
    └─ INSERT check_in_summaries (read model proyectado)
             └─ member_id, checked_in_at, quote_body, quote_author
```

---

## 5. Tolerancia a Fallos y Consistencia Eventual

### Escenario: API externa caída (error 500 sostenido)

1. `HandleCheckInForEngagement` recibe el job y llama al adaptador
2. El adaptador lanza `QuoteProviderUnavailableException`
3. Laravel reintenta el job con backoff exponencial: 30s → 120s → 300s
4. Si los 3 intentos fallan, el job se mueve a la tabla `failed_jobs`
5. El check-in físico **ya está registrado** — no se ve afectado
6. El `check_in_summaries` tendrá `quote_body = NULL` para ese acceso
7. Se puede implementar un comando de re-procesamiento para `failed_jobs`

### Escenario: Worker del broker caído por horas

1. Los eventos en `outbox_events` permanecen con `published_at = NULL`
2. Cuando el worker se recupera, `outbox:publish` los detecta y los despacha
3. No hay pérdida de eventos — el outbox actúa como buffer durable
4. El orden de procesamiento es FIFO por `created_at`

### Escenario: Micro-caída del broker durante `outbox:publish`

1. Si el dispatch falla antes de actualizar `published_at`, el evento permanece pendiente
2. El siguiente ciclo del outbox worker lo reintentará
3. **Idempotencia**: `HandleCheckInForEngagement` verifica si ya existe un registro en `check_in_summaries` para ese `check_in_id` antes de insertar

---

## 6. Estructura del Repositorio

```
app/
├── Modules/
│   ├── AccessControl/          ← Bounded Context 1
│   │   ├── Domain/             ← Entidades, eventos, interfaces (sin dependencias externas)
│   │   ├── Application/        ← Casos de uso (Commands/Handlers)
│   │   └── Infrastructure/     ← Eloquent, HTTP controllers
│   └── Engagement/             ← Bounded Context 2
│       ├── Domain/             ← Ports, Value Objects, Excepciones
│       ├── Application/        ← Jobs encolados
│       └── Infrastructure/     ← Adapters ACL, Eloquent, HTTP controllers
└── Shared/
    ├── Infrastructure/Outbox/  ← Modelo Outbox compartido
    └── Console/Commands/       ← Artisan commands de infraestructura

bootstrap/providers.php         ← Registro de ServiceProviders por módulo
routes/api.php                  ← Endpoints HTTP
```
