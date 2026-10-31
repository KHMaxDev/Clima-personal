# ClimaQuest — Anexo técnico v1.2

**Documentos base:** ClimaQuest v1.0 (especificación) y Anexo técnico v1.1.
**Contenido de este anexo:** resolución de los pendientes de la sección E del Anexo v1.1.
**Estado:** Diseño — decisiones normativas para despliegue, pruebas e internacionalización.

Al igual que el v1.1, este anexo es **normativo**: ante contradicción con el documento base, prevalece lo aquí definido.

---

## F. Plan de pruebas

### F.1. Herramientas y base de datos

- **Framework:** Pest sobre PHPUnit (incluido en Laravel). Pest por legibilidad; sin plugins exóticos.
- **Base de datos de pruebas:** **PostgreSQL**, no SQLite. Se prueba contra el mismo motor que producción porque el MVP depende de `JSONB`, constraints e índices que SQLite no replica. Se usa una base `climaquest_test` y el trait `RefreshDatabase`.
- **Análisis estático:** Larastan/PHPStan nivel 6. **Formato:** Pint (`pint --test` en CI).
- **Tiempo determinista:** `Carbon::setTestNow()` / `$this->travelTo()` en toda prueba que dependa de hora (DST, ventanas, horizonte).

### F.2. Prohibición de llamadas reales en CI

Ninguna prueba automática llama a Open-Meteo, OpenRouter ni al SMTP real. Todo HTTP externo se sustituye con `Http::fake()` y **fixtures grabadas**.

Estructura de fixtures:

```
tests/Fixtures/
├── open-meteo/
│   ├── forecast_cancun_7d.json
│   ├── forecast_chetumal_dst.json
│   └── geocoding_madrid.json
└── openrouter/
    ├── recommendation_valid.json
    └── recommendation_malformed.json
```

### F.3. Pirámide de pruebas y cobertura mínima

| Nivel | Qué cubre | Cobertura objetivo |
|---|---|---|
| Unitario | Agregación (Anexo v1.1 §A), motor de reglas (§B), normalización de unidades, validación de salida IA | **Ramas: 100%** en agregación y reglas |
| Integración | Laravel ↔ PostgreSQL, servicio Open-Meteo (faked), OpenRouter (faked), caché | Flujos críticos |
| Feature | Rutas, autenticación, autorización, validación, calendario | Criterios §22.4 |
| Seguridad | Propiedad de recursos, endpoints protegidos, claves no expuestas | §22.3 |

### F.4. Pruebas del motor de reglas (table-driven / golden)

Cada caso es un par `entrada → salida esperada` que se conserva como referencia de regresión:

```php
it('marca riesgo elevado cuando la sensación térmica es crítica', function () {
    $rules = rulesFor('running'); // desde fixtures
    $aggregated = aggregated(['apparent_temperature' => 38.1, 'coverage' => 1.0]);

    $result = app(ActivityAssessmentService::class)->evaluate($rules, $aggregated);

    expect($result->status)->toBe('Riesgo elevado')
        ->and($result->score)->toBeNull() // crítico anula puntuación
        ->and($result->reasons)->toContainCode('heat_extreme')
        ->and($result->ruleVersion)->toBe(sha1(/* conjunto aplicado */));
});
```

Casos obligatorios:
- Regla crítica falla → estado crítico aunque el score sería alto.
- `coverage < 0.80` → **Información insuficiente**.
- Variable esencial ausente → **Información insuficiente**, no favorable.
- Reproducibilidad: mismo snapshot + mismo `rule_version` → resultado idéntico (se ejecuta dos veces y se compara).

### F.5. Pruebas de agregación (incluye DST)

Casos obligatorios (Anexo v1.1 §A):
- Hora exacta (actividad 14:00–15:00).
- Solapamiento parcial ≥ 15 min (se incluye) y < 15 min (se excluye).
- `precipitation_sum` ponderada por fracción de hora.
- `weather_code` `worst` con ranking de severidad.
- Actividad que cruza medianoche.
- Actividad en transición DST simulada con fixture `forecast_chetumal_dst.json`.

### F.6. Pruebas de seguridad

```php
it('no permite ver la actividad de otro usuario', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $activity = Activity::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get("/activities/{$activity->id}")
        ->assertNotFound(); // 404, no 403, para no filtrar existencia
});
```

Además: endpoints sin sesión → 401/redirect; `.env`/claves ausentes del HTML renderizado; consultas parametrizadas (verificado por revisión + Larastan).

### F.7. Pruebas de IA y caché

- `Http::fake(openrouter/recommendation_valid.json)` → respuesta validada contra esquema → se muestra.
- `Http::fake(openrouter/recommendation_malformed.json)` → **fallback** determinista, sin error 500.
- Caché meteorológica: primera llamada `Http::assertSentCount(1)`; segunda dentro del TTL `Http::assertSentCount(0)`.
- Búsqueda de alternativas: verifica que **no** se disparan solicitudes adicionales a Open-Meteo (Anexo v1.1 §B.7).

### F.8. CI (GitHub Actions)

Pipeline en cada push/PR:

1. Lint: `pint --test`.
2. Estático: `phpstan analyse`.
3. Tests: PHP 8.3 + servicio PostgreSQL, `php artisan test`.
4. Assets: `npm ci && npm run build` (falla si no compila).

Sin secretos reales: se inyectan valores ficticios (`OPENROUTER_API_KEY=test`). El pipeline nunca llama a servicios externos.

---

## G. Despliegue, CI/CD, colas y scheduler

### G.1. Topología de producción (monolito, simple)

Docker Compose en un VPS, con contenedores separados por rol:

```
nginx            (TLS, reverse proxy)
php-fpm          (Laravel app)
queue-worker     (php artisan queue:work)
scheduler        (php artisan schedule:work)
postgres         (datos + pgvector)
```

Ventajas: reproducible, sin acoplarse a un PaaS, y demuestra competencia de despliegue. Alternativas válidas (Laravel Forge/Ploi) son aceptables si se documentan.

### G.2. Colas

- **Driver MVP:** `database` (sin dependencia de Redis). Apto para el volumen esperado.
- **Migración futura:** Redis + Laravel Horizon si el volumen lo justifica. La interfaz `ShouldQueue` no cambia.
- Todo trabajo lento o externo va a cola: consultas meteorológicas programadas, notificaciones, evaluaciones periódicas, generación IA.
- Reintentos con backoff exponencial y límite (`tries`), y `failed_jobs` monitoreado.

### G.3. Scheduler

- Un único contenedor/proceso ejecuta `php artisan schedule:work` (evita solapamiento entre réplicas).
- Tareas: revisión de actividades próximas (cada hora), actualización de pronósticos (según TTL del Anexo v1.1 §C.3), envío de recordatorios, purga de retención (§C.5), revisión de caducidad de documentos RAG.
- Cada tarea define `withoutOverlapping()` y `onOneServer()`.

### G.4. Despliegue y variables de entorno

- Producción: `APP_DEBUG=false`, `APP_ENV=production`; ejecutar `php artisan config:cache route:cache view:cache optimize`.
- Migraciones con `php artisan migrate --force` tras un `pg_dump` previo. Si la migración es destructiva, ventana de mantenimiento (`php artisan down/up`).
- **Secretos** (`OPENROUTER_API_KEY`, credenciales de correo/DB) inyectados por el gestor de secretos del entorno. Nunca en el repositorio ni en imágenes Docker.
- Healthcheck: endpoint `/up` (Laravel) usado por el balanceador/contenedor.

### G.5. Respaldos y observabilidad (enlaza con Anexo v1.1 §C.8)

- Backup diario de PostgreSQL (`pg_dump`), retención ≥ 7 días, y **prueba de restauración** documentada.
- Logs JSON por canal (`weather`, `assessment`, `ai`, `rag`); rotación. Los secretos nunca se registran.
- Alertas: caída de Open-Meteo/OpenRouter, tasa de `failed_jobs`, coste IA/día excedido.

---

## H. Correo electrónico e idempotencia de notificaciones

### H.1. Proveedor de correo

- **Recomendado:** proveedor transaccional por API (Resend o Mailgun); alternativa: Amazon SES.
- Integración vía Laravel Mail (`Mail` facade + Mailable). El transporte se selecciona por entorno, de modo que local usa `log`/`array` y no envía correos reales.
- Dominio propio con SPF, DKIM y DMARC configurados antes de producción.

### H.2. Modelo de notificaciones

Tabla `notifications` (propia; no confundir con la tabla nativa de Laravel, que puede usarse para canal base):

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK | Destinatario |
| `activity_id` | FK nullable | Actividad relacionada |
| `type` | string | `reminder_24h`, `reminder_2h`, `forecast_change` |
| `channel` | string | `mail`, `database` |
| `dedup_key` | string UNIQUE | Clave de idempotencia |
| `scheduled_for` | timestamptz | Momento previsto de envío (UTC) |
| `sent_at` | timestamptz nullable | |
| `status` | enum | `pending`, `sent`, `skipped`, `failed` |
| `payload` | JSONB | Contenido renderizable |
| `created_at`, `updated_at` | | |

### H.3. Idempotencia

- `dedup_key = sha1(type|activity_id|user_id|ventana)` con **restricción UNIQUE**.
- Antes de enviar, el job hace un `insert ... on conflict do nothing` sobre `dedup_key`. Si ya existe, **no envía**.
- El envío se marca `sent` dentro de una transacción; un reintento del worker no duplica el correo.
- Un cambio de fecha/hora de la actividad invalida las ventanas pendientes (se recalculan; las ya enviadas permanecen).

### H.4. Reglas de envío

- Respetar `notifications_enabled` y `email_notifications_enabled` del usuario (§17.7). Si están desactivadas, `status=skipped`.
- **Horas silenciosas:** no enviar entre la hora local del usuario `22:00` y `07:00` (configurable); la notificación se reprograma.
- **Alertas por cambio de pronóstico:** solo se envía `forecast_change` si la evaluación **empeora** respecto a la última almacenada (comparación de `status`/`score` contra el umbral configurado), evitando ruido.
- Límite de notificaciones por usuario/día. Enlace de preferencias (y baja) en cada correo.

### H.5. Colas y fallos

- Cada notificación se despacha como job en cola con `tries=3` y backoff. Fallos persistentes → `status=failed` y registro para diagnóstico; nunca bloquean la tarea programada principal.

---

## I. Embeddings y promoción a pgvector

### I.1. Decisión de proveedor

OpenRouter está orientado a **chat**, no a embeddings. Se define un proveedor separado y **configurable** detrás de una interfaz:

```php
interface EmbeddingProvider {
    public function embed(array $texts): array; // array de vectores
    public function dimensions(): int;
    public function model(): string;
}
```

- **Opción por defecto sugerida:** un modelo de embeddings cerrado de bajo costo (p. ej. `text-embedding-3-small`, 1536 dims) o un proveedor de embeddings abierto. La elección final se documenta en `.env`.
- **Restricción dura:** la dimensión del vector **debe** coincidir con la dimensión declarada en la columna `vector(N)` de pgvector. No se asumen dimensiones uniformes entre modelos.

### I.2. Esquema de datos

```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE knowledge_chunks (
    id           bigserial PRIMARY KEY,
    document_id  bigint NOT NULL REFERENCES knowledge_documents(id) ON DELETE CASCADE,
    content      text NOT NULL,
    embedding    vector(1536),          -- dimensión = modelo configurado
    embedding_model varchar(80) NOT NULL, -- p. ej. text-embedding-3-small
    token_count  integer,
    content_hash char(40) NOT NULL,     -- sha1(content) para idempotencia
    metadata     jsonb,
    created_at   timestamptz DEFAULT now()
);

CREATE INDEX knowledge_chunks_embedding_idx
    ON knowledge_chunks USING hnsw (embedding vector_cosine_ops);
```

- Búsqueda por distancia coseno (`<=>`), recuperando `top-k` filtrado por categoría.
- `embedding_model` permite **re-embeddings** cuando cambie el modelo sin mezclar vectores de modelos distintos.

### I.3. Estrategia de promoción (coherente con v1.1 §16.5)

1. **Fase inicial (MVP de RAG):** recuperación por categoría, etiquetas y palabras clave (ILIKE / full-text de PostgreSQL). Sin embeddings.
2. **Disparador de migración a vectores:** cuando la base supere un umbral (sugerido **> 100 documentos** o cuando la búsqueda por palabras clave deje de ser precisa). Decisión medible, no anticipada.
3. **Migración:** comando artisan que genera embeddings por lotes con control de coste, escribe `embedding_model` y `content_hash`, y es **reanudable** e idempotente (no re-embebe `content_hash` ya procesados).
4. **Fallback:** si el proveedor de embeddings no está disponible, el servicio RAG degrada a búsqueda por palabras clave; el asistente sigue funcionando.

---

## J. Pipeline de ingesta y revisión de documentos RAG

### J.1. Principios

- Solo se ingieren **documentos propios o con licencia clara**. No se raspa contenido de terceros sin derechos.
- Los documentos se tratan como **datos**, nunca como instrucciones (coherente con §16.6 y Anexo v1.1 §C.7).

### J.2. Formato de origen

Markdown con front-matter YAML:

```yaml
---
title: "Precauciones para actividad física con calor"
category: running
tags: [calor, hidratacion, uv]
source: "Elaboración propia"
author: "Equipo ClimaQuest"
reviewer: null
status: draft          # draft | review | approved | retired
version: 1
effective_from: 2026-07-01
review_date: 2027-01-01
---
```

### J.3. Ciclo de vida y control de publicación

```
draft → review → approved → (retired)
```

- **Solo los documentos con `status = approved`** son recuperables por el RAG.
- La aprobación la realiza un rol administrador (diferido a etapa posterior, §7.2). En el MVP, un comando de consola con confirmación explícita actúa como puerta de aprobación.
- `review_date` dispara una tarea de revisión; los documentos vencidos se marcan (`stale`) y dejan de recuperarse hasta revalidarse.

### J.4. Ingesta

- Comando: `php artisan rag:ingest <ruta>`.
- Pasos: parsear front-matter → validar esquema → dividir en fragmentos → calcular `content_hash` → generar embeddings (si están habilitados) → upsert.
- **Idempotente:** un documento sin cambios (mismo `content_hash`) no se reprocesa. Cambiar el texto crea una nueva versión y retira los fragmentos antiguos.

### J.5. Segmentación (chunking)

- División por encabezados; fragmentos objetivo de **300–800 tokens**, solapamiento de **50–100 tokens**.
- Cada fragmento conserva metadatos (`document_id`, categoría, etiquetas, fuente) para filtrado y **cita de la fuente** en la respuesta del asistente.

### J.6. Seguridad de la ingesta

- Sanitizar el contenido (sin HTML ejecutable, sin secretos, sin PII).
- Limitar la recuperación a fuentes aprobadas y al ámbito autorizado del usuario.
- El asistente **cita** la fuente de cada afirmación documental y distingue datos meteorológicos de explicaciones generadas (§15.2).

---

## K. Accesibilidad, internacionalización y `weather_code`

### K.1. Accesibilidad (objetivo WCAG 2.1 AA)

- **No depender solo del color** para los estados de evaluación: cada estado lleva **icono + etiqueta de texto** además del color (Favorable, Favorable con precauciones, Poco favorable, Riesgo elevado, Información insuficiente).
- Calendario navegable por teclado; foco visible y orden lógico.
- Resultados del asistente (asíncronos) anunciados con región `aria-live="polite"`; estado de carga con `aria-busy`.
- Contraste mínimo 4.5:1; formularios con `<label>` asociados y mensajes de error vinculados por `aria-describedby`.
- Los gráficos (Chart.js) incluyen tabla de datos alternativa o descripción textual.

### K.2. Internacionalización

- Localización con Laravel (`lang/`). **Idioma inicial: español (`es-MX`)**, pero ningún texto queda embebido: todo pasa por `__('climaquest.…')`.
- Fechas y números con Carbon/localización (`d/m/Y`, `H:i` o formato es-MX según preferencia).
- Unidades consistentes: temperatura °C, viento km/h, precipitación mm, UV índice adimensional. Conversión centralizada, nunca en plantillas.
- La UI muestra la hora en la zona de la ubicación (Anexo v1.1 §C.2).

### K.3. Mapeo `weather_code` → texto en español

Archivo `lang/es/weather.php`:

| Código | Texto en español |
|---|---|
| 0 | Cielo despejado |
| 1 | Mayormente despejado |
| 2 | Parcialmente nublado |
| 3 | Nublado |
| 45 | Niebla |
| 48 | Niebla con escarcha |
| 51 | Llovizna ligera |
| 53 | Llovizna moderada |
| 55 | Llovizna intensa |
| 56 | Llovizna helada ligera |
| 57 | Llovizna helada intensa |
| 61 | Lluvia ligera |
| 63 | Lluvia moderada |
| 65 | Lluvia intensa |
| 66 | Lluvia helada ligera |
| 67 | Lluvia helada intensa |
| 71 | Nieve ligera |
| 73 | Nieve moderada |
| 75 | Nieve intensa |
| 77 | Granos de nieve |
| 80 | Chubascos ligeros |
| 81 | Chubascos moderados |
| 82 | Chubascos violentos |
| 85 | Chubascos de nieve ligeros |
| 86 | Chubascos de nieve intensos |
| 95 | Tormenta |
| 96 | Tormenta con granizo ligero |
| 99 | Tormenta con granizo intenso |

También se localizan con claves las **etiquetas de estado** (§RF-06), las **severidades** (`info`, `warning`, `critical`) y los **mensajes de reglas** (la plantilla `message_es` del Anexo v1.1 §B.2 admite interpolación y traducción).

### K.4. Atribución de Open-Meteo

La UI incluye la atribución requerida por Open-Meteo (texto y enlace) en la vista de pronóstico, conforme a sus términos de uso (§10.4).

---

## L. Trazabilidad de este anexo

| Pendiente (Anexo v1.1 §E) | Sección que lo resuelve |
|---|---|
| Plan de pruebas (Pest, `Http::fake`, fixtures) | §F |
| Despliegue, CI/CD, colas y scheduler | §G |
| Correo e idempotencia de notificaciones | §H |
| Modelo de embeddings y promoción a pgvector | §I |
| Pipeline de ingesta y revisión RAG | §J |
| Accesibilidad, i18n y `weather_code` | §K |

Con esto quedan resueltos todos los pendientes declarados en el Anexo v1.1 §E. Próximo paso natural: **migraciones SQL** de `activity_rules`, `weather_snapshots`, `notifications` y `knowledge_*` para iniciar la Fase 2.
