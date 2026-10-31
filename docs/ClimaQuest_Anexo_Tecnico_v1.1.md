# ClimaQuest — Anexo técnico v1.1

**Documento base:** ClimaQuest — Análisis, requisitos y especificaciones técnicas, v1.0
**Contenido de este anexo:** agregación temporal del pronóstico, formato y motor de reglas, y políticas transversales del sistema.
**Estado:** Diseño — decisiones normativas para la implementación del MVP (Laravel + PostgreSQL).

Este anexo resuelve los tres huecos de mayor impacto de la v1.0 y tiene carácter **normativo**: ante contradicción con el documento base, prevalece lo aquí definido.

---

## A. Agregación temporal del pronóstico

### A.1. Problema

Las actividades tienen un intervalo `[start_at, end_at)` arbitrario, mientras que Open-Meteo entrega datos **horarios** marcados al inicio de cada hora. Sin una regla explícita, cada desarrollador elegirá un valor distinto (máximo, mínimo, promedio, hora de inicio), y la evaluación dejará de ser reproducible.

### A.2. Definiciones

- **Hora candidata:** toda hora `h` del pronóstico cuyo intervalo `[h, h+1)` solape con `[start_at, end_at)`.
- **Solapamiento (`overlap`):** minutos de intersección entre la hora y la actividad, en `[0, 60]`.
- **Umbral de inclusión (`overlap_min`):** una hora parcial solo se incluye si `overlap >= max(15 min, 25% del hueco de la hora)`. Valor configurable por categoría; por defecto 15 min.
- **Cobertura (`coverage`):** `minutos_de_horas_candidatas / duración_total`, acotado a `[0, 1]`.

Regla de frontera: los intervalos son **semiabiertos** `[inicio, fin)`. Un evento que termina a las 15:00 no incluye la hora 15:00–16:00.

### A.3. Operador de agregación por variable

Cada variable se reduce a **un único valor** representativo del intervalo según su naturaleza. El operador no es intercambiable: expresa el peor caso físico, no un promedio ciego.

| Variable Open-Meteo | Operador | Justificación |
|---|---|---|
| `temperature_2m` | `max` | El calor se evalúa por su pico |
| `apparent_temperature` | `max` | Sensación térmica pico |
| `relative_humidity_2m` | `max` | La humedad alta agrava el calor |
| `precipitation_probability` | `max` | Probabilidad pico de lluvia |
| `precipitation` | `sum` (ponderada) | Volumen acumulado en el intervalo |
| `wind_speed_10m` | `max` | Ráfaga sostenida peor |
| `wind_gusts_10m` | `max` | Ráfaga máxima |
| `uv_index` | `max` | Exposición solar pico |
| `cloud_cover` | `mean` | Condición predominante |
| `shortwave_radiation` | `mean` | Luz predominante (fotografía) |
| `weather_code` | `worst` | Estado más severo del intervalo |

**Suma ponderada** (para acumulados como `precipitation`):

```
precipitation_sum = Σ (valor_hora * overlap_hora / 60)
```

**Ranking de severidad de `weather_code`** (de menor a mayor; los códigos coinciden con WMO):
`0` despejado `1` mayormente despejado `2` parcialmente nublado `3` nublado
`45,48` niebla `51,53,55` llovizna `56,57` llovizna helada
`61,63,65` lluvia `66,67` lluvia helada `71,73,75,77` nieve
`80,81,82` chubascos `85,86` chubascos de nieve `95` tormenta
`96,99` tormenta con granizo.
`worst` = el código con mayor rango de severidad presente; a igual rango, el mayor valor numérico.

### A.4. Estructura de salida del agregador

```php
$aggregated = [
    'values' => [
        'temperature_2m' => 33.5,
        'apparent_temperature' => 38.1,
        'precipitation' => 2.4,
        'precipitation_probability' => 70,
        'wind_speed_10m' => 22.0,
        'wind_gusts_10m' => 41.0,
        'uv_index' => 9.0,
        'weather_code' => 95,
        // ... solo las variables con datos
    ],
    'coverage' => 0.75,                 // 45 de 60 minutos
    'missing' => ['relative_humidity_2m'],
    'hours_used' => 2,
    'interval' => ['2026-07-01T14:00:00-05:00', '2026-07-01T15:00:00-05:00'],
];
```

### A.5. Cobertura y datos faltantes (enlaza con RF-05)

- `coverage < 0.80` → la evaluación se marca como **parcial**.
- Variable **esencial** ausente → estado **Información insuficiente** para la categoría (ver B.4).
- Variable **no esencial** ausente → se emite un aviso informativo, no altera el estado.
- **Nunca** se sustituye un valor desconocido por un valor favorable por defecto.

### A.6. Ventana extendida (opcional por categoría)

Para categorías sensibles a la transición (p. ej. *viaje*, *senderismo*), la ventana puede extenderse a la hora previa y posterior y evaluarse por separado como **contexto**, sin contaminar los valores del intervalo de la actividad. Se almacena en `aggregated['context']`.

---

## B. Formato y motor de reglas

### B.1. Principio

Las reglas son **datos**, no código disperso. Viven en PostgreSQL y son evaluadas por un servicio determinista. Esto satisface RF-06, RF-07 y RNF-06 (nuevas categorías/reglas sin reescribir el núcleo).

### B.2. Tabla `activity_rules`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `category_id` | FK nullable | `NULL` = regla global aplicable a todas las categorías |
| `code` | string | Identificador legible, único por categoría (p. ej. `heat_extreme`) |
| `variable` | string | Variable agregada de A.3 |
| `operator` | string | `lt`, `lte`, `gt`, `gte`, `between`, `outside` |
| `threshold_a` | numeric | Primer umbral |
| `threshold_b` | numeric nullable | Segundo umbral (para `between`/`outside`) |
| `severity` | enum | `info`, `warning`, `critical` |
| `score_impact` | integer | Puntos restados del score si la regla **falla** (0–100) |
| `essential` | boolean | Si la variable es esencial para la categoría |
| `message_es` | text | Plantilla en español, admite `{value}` y umbrales |
| `active` | boolean | |
| `version` | integer | Versión de la regla; incrementa al editarla |
| `created_at`, `updated_at` | timestamps | |

Índice recomendado: `activity_rules(category_id, active)`. Se incluye en el **MVP** (no diferir a §17.8).

### B.3. Evaluación de una regla

Entrada: `category_id`, `aggregated` (A.4), conjunto de reglas activas (de la categoría + globales).

Para cada regla:
1. Si `variable` ∈ `aggregated['missing']`:
   - regla `essential=true` → produce un `Finding` marcado como `missing` que bloquea la evaluación y fuerza el estado **Información insuficiente**.
   - regla `essential=false` → `Finding` informativo, no puntúa.
2. En caso contrario, aplicar el operador al valor. La regla **falla** si la condición se cumple.
3. Si falla, emitir:

```php
$finding = [
    'code' => 'heat_extreme',
    'severity' => 'critical',
    'variable' => 'apparent_temperature',
    'value' => 38.1,
    'threshold' => 'gte 38',
    'message' => 'La sensación térmica supera 38 °C; riesgo de golpe de calor.',
    'score_impact' => 30,
];
```

### B.4. Algoritmo de puntuación y estado

El estado se decide en este **orden de precedencia** (una condición crítica nunca puede ocultarse por el promedio, tal como exige §14.2):

1. Si algún `Finding` de **fallo** tiene severidad `critical` (condición cumplida con datos) → **Riesgo elevado**.
2. Si falta una variable esencial (`essential=true` sin dato), si `coverage < 0.80` o si no hay datos evaluables → **Información insuficiente**.
3. En otro caso, calcular score y mapear:

```
score = clamp(100 - Σ score_impact(fallos no críticos), 0, 100)

80–100  → Favorable
60–79   → Favorable con precauciones
40–59   → Poco favorable
0–39    → Riesgo elevado
```

Los `Finding` se ordenan por severidad descendente para su presentación. La respuesta incluye siempre: `status`, `score` (nullable si ind. insuficiente), `reasons[]`, `coverage`, `rule_version`.

### B.5. Versionado de reglas

`rule_version` = hash estable del conjunto de reglas aplicadas:

```
rule_version = sha1(join(sorted( "{code}:{version}" para cada regla aplicada )))
```

Se persiste en `activity_assessments.rule_version` para trazabilidad (RNF-08). Cambiar cualquier regla produce un hash distinto.

### B.6. Ejemplo: reglas iniciales de la categoría *correr*

| code | variable | operator | umbral | severity | impact | essential |
|---|---|---|---|---|---|---|
| `rain_high` | `precipitation_probability` | `gte` | 60 | warning | 20 | no |
| `heat_extreme` | `apparent_temperature` | `gte` | 38 | critical | 30 | sí |
| `cold_extreme` | `apparent_temperature` | `lte` | 0 | critical | 30 | sí |
| `wind_strong` | `wind_speed_10m` | `gte` | 35 | warning | 15 | no |
| `uv_high` | `uv_index` | `gte` | 8 | info | 5 | no |
| `awa_missing` | `temperature_2m` | — | — | (esencial) | — | sí |

### B.7. Búsqueda de horarios alternativos (corrige §14.3)

No se realizan llamadas adicionales a la API por candidato. Se usa **una sola serie horaria en caché**:

1. Definir ventana `[window_start, window_end]` (hora local de la ubicación).
2. Generar intervalos candidatos deslizantes del tamaño de la actividad (paso configurable, p. ej. 30 min).
3. Para cada candidato, ejecutar A (agregación) y B (reglas) sobre la serie ya obtenida.
4. Descartar candidatos con estado **Riesgo elevado** o **Información insuficiente**.
5. Ordenar por `(score desc)`, desempatando por afinidad con las preferencias del usuario y cercanía al horario original.
6. Devolver los N mejores (configurable, por defecto 3) con sus ventajas (`reasons` favorables) y limitaciones (`findings`).

Si no hay candidatos válidos, la respuesta indica explícitamente que no se encontró una alternativa mejor con los datos disponibles.

---

## C. Políticas transversales del sistema

### C.1. Horizonte de pronóstico

- Máximo planificable y evaluable: **16 días** (límite de Open-Meteo).
- Días 0–7: confianza **normal**.
- Días 8–16: confianza **reducida**; se agrega `confidence: low` y un aviso visible.
- Actividad más allá del horizonte → estado **Fuera de horizonte** (no se intenta evaluar).

### C.2. Zonas horarias y horario de verano (DST)

- Persistencia: `start_at`, `end_at` en **UTC** (`timestamptz`).
- Cada actividad guarda el **IANA tz** de su ubicación (p. ej. `America/Cancun`).
- La UI muestra horas en la zona de la **ubicación** por defecto; si difiere de la preferida del usuario, se muestra un indicador con ambas.
- Notificaciones y tareas programadas ejecutan en UTC y disparan según la **hora local del usuario**; el scheduler recorre zonas, no asume un offset fijo.
- Conversiones con Carbon/timezone database. **Prohibido** usar offsets fijos o `+05:00` manual.
- Las comparaciones de agregación (A) se hacen en hora local de la ubicación; el almacenamiento permanece en UTC.

### C.3. Caché meteorológica

- **Clave de caché:** `provider|round(lat,2)|round(lon,2)|fecha_local|forecast_days`.
- **TTL escalonado** según antigüedad del objetivo:

| Distancia al objetivo | TTL |
|---|---|
| < 24 h | 1 h |
| 1–3 días | 3 h |
| 3–7 días | 6 h |
| > 7 días | 12 h |

- **Antigüedad aceptable:** si el snapshot supera su TTL y el proveedor falla, se muestra el dato con sello `última actualización: <fetched_at>` y la evaluación se marca **degradada** (RF-13). Nunca se presenta un dato viejo como actualizado.
- Se registra `fetched_at` y `expires_at` en `weather_snapshots`.
- Se respeta la licencia y los límites de Open-Meteo: la caché es obligatoria, no opcional.

### C.4. Esquema mínimo de `preferences`

Para que ordenar alternativas (B.7) sea realizable, `preferences` se documenta como JSON con esquema validado:

```json
{
  "preferred_start_time": "06:00",
  "preferred_end_time": "11:00",
  "max_wind_kmh": 25,
  "avoid_rain": true,
  "uv_protection": true,
  "notes": "texto libre"
}
```

Validación mediante `FormRequest`; campos desconocidos se rechazan. Se promueve a tabla propia (`user_preferences`, §17.7) lo que sea consultable; el resto permanece en JSONB documentado.

### C.5. Retención y privacidad de datos

- `weather_snapshots`: purga automática a los **30 días** (job programado).
- `ai_recommendations`: retención configurable (por defecto **90 días**).
- Eliminación de cuenta: borrado en cascada de actividades, evaluaciones, snapshots propios y recomendaciones.
- Exportación de datos del usuario: endpoint de exportación JSON (RGPD-friendly).
- Solo se envía a OpenRouter el contexto mínimo necesario; no se incluyen email, nombre completo ni identificadores personales.

### C.6. Presupuesto y control de IA

- Límite por usuario: **N recomendaciones/día** y **M solicitudes/minuto** (configurable).
- Caché semántica: reutilizar una recomendación si la huella `(activity_id, rule_version, snapshot_id)` no cambió.
- Timeout de solicitud a OpenRouter configurable (p. ej. 20 s) con reintento acotado y **fallback** a la explicación determinista.
- Registro de tokens y coste por solicitud; alerta al superar un umbral diario.
- El modelo (`OPENROUTER_MODEL`) es configurable por entorno; el proveedor de **embeddings** (para futura RAG/pgvector) debe definirse explícitamente y no asumirse igual al de chat.

### C.7. Defensa contra inyección de prompt

- La descripción libre de la actividad se inserta como **dato delimitado**, nunca como instrucción.
- El prompt de sistema declara que los datos recuperados (incluidos documentos RAG) no son instrucciones privilegiadas.
- La respuesta del modelo se valida contra el esquema JSON esperado antes de mostrarse; salida inválida → fallback determinista.

### C.8. Observabilidad

- Logging estructurado por módulo (`weather`, `assessment`, `ai`, `rag`).
- Métricas mínimas: latencia y tasa de error por proveedor externo, coste IA/día, aciertos de caché.
- Alertas ante caída sostenida de Open-Meteo/OpenRouter. Los secretos nunca se escriben en logs.

---

## D. Impacto en el MVP (criterios de aceptación adicionales)

Se añaden a §25 del documento base:

- Toda evaluación es **reproducible**: dado un `weather_snapshot` y un `rule_version`, el resultado es idéntico.
- La UI distingue claramente estado **completo**, **parcial (coverage < 0.80)**, **degradado (dato vencido)** e **información insuficiente**.
- Las reglas se administran como datos en `activity_rules`; añadir una regla no requiere desplegar código.
- La búsqueda de alternativas no genera llamadas adicionales al proveedor meteorológico.
- Las fechas se muestran correctas en la zona horaria de la ubicación, incluidas transiciones DST.

---

## E. Pendientes que este anexo NO resuelve

- Plan de pruebas concreto (Pest/PHPUnit, `Http::fake`, fixtures de Open-Meteo).
- Despliegue, CI/CD, driver de colas y worker del scheduler.
- Proveedor de correo e idempotencia de notificaciones.
- Modelo de embeddings y estrategia de promoción a pgvector.
- Pipeline de ingesta y revisión de documentos RAG.
- Requisitos de accesibilidad, i18n y mapeo `weather_code` → texto en español.
