<?php

return [
    'weather' => [
        'provider' => 'open-meteo',
        'base_url' => env('OPENMETEO_BASE_URL', 'https://api.open-meteo.com/v1'),
        'geocoding_url' => env('OPENMETEO_GEOCODING_URL', 'https://geocoding-api.open-meteo.com/v1'),
        'timeout' => (int) env('OPENMETEO_TIMEOUT', 10),
        'forecast_days' => 16,
        'high_confidence_days' => 7,
        'overlap_min_minutes' => 15,
        'coverage_threshold' => 0.80,
        'cache_ttl_hours' => [
            'lt_24h' => 1,
            'lt_3d' => 3,
            'lt_7d' => 6,
            'default' => 12,
        ],
        'hourly_variables' => [
            'temperature_2m',
            'apparent_temperature',
            'relative_humidity_2m',
            'precipitation_probability',
            'precipitation',
            'wind_speed_10m',
            'wind_gusts_10m',
            'uv_index',
            'weather_code',
            'cloud_cover',
            'shortwave_radiation',
        ],
    ],

    'aggregation' => [
        'temperature_2m' => 'max',
        'apparent_temperature' => 'max',
        'relative_humidity_2m' => 'max',
        'precipitation_probability' => 'max',
        'precipitation' => 'sum',
        'wind_speed_10m' => 'max',
        'wind_gusts_10m' => 'max',
        'uv_index' => 'max',
        'cloud_cover' => 'mean',
        'shortwave_radiation' => 'mean',
        'weather_code' => 'worst',
    ],

    'weather_code_severity' => [
        0, 1, 2, 3,
        45, 48,
        51, 53, 55, 56, 57,
        61, 63, 65, 66, 67,
        71, 73, 75, 77,
        80, 81, 82, 85, 86,
        95, 96, 99,
    ],

    'assessment' => [
        'score_bands' => [
            'favorable' => [80, 100],
            'favorable_with_precautions' => [60, 79],
            'unfavorable' => [40, 59],
            'high_risk' => [0, 39],
        ],
        'alternative_step_minutes' => (int) env('CLIMAQUEST_ALTERNATIVE_STEP', 30),
        'alternatives_limit' => (int) env('CLIMAQUEST_ALTERNATIVES_LIMIT', 3),
    ],

    'ai' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 20),
        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 800),
        'daily_limit_per_user' => (int) env('OPENROUTER_DAILY_LIMIT', 30),
        'prompt_version' => 'assistant-v1',

        /*
        | Generación de actividades a partir de una descripción en lenguaje
        | natural (Anexo v1.2). El modelo pregunta lo que falte antes de
        | proponer el listado final.
        */
        'generation' => [
            'max_activities' => (int) env('CLIMAQUEST_GENERATION_MAX', 12),
            'max_questions' => (int) env('CLIMAQUEST_GENERATION_QUESTIONS', 5),
            'horizon_days' => (int) env('CLIMAQUEST_GENERATION_HORIZON', 7),
            'prompt_version' => 'activity-generation-v1',
        ],
    ],

    /*
    | Proveedor de embeddings independiente del chat (Anexo v1.1 §C.6).
    | No se asume que sea el mismo que OpenRouter. Si no está configurado,
    | la recuperación RAG usa coincidencia por palabras clave.
    */
    'embeddings' => [
        'provider' => env('EMBEDDINGS_PROVIDER'),
        'base_url' => env('EMBEDDINGS_BASE_URL'),
        'api_key' => env('EMBEDDINGS_API_KEY'),
        'model' => env('EMBEDDINGS_MODEL'),
        'dimensions' => (int) env('EMBEDDINGS_DIMENSIONS', 1536),
    ],

    'rag' => [
        'enabled' => (bool) env('RAG_ENABLED', false),
        'top_k' => (int) env('RAG_TOP_K', 4),
        'chunk_target_tokens' => (int) env('RAG_CHUNK_TARGET_TOKENS', 500),
        'chunk_overlap_tokens' => (int) env('RAG_CHUNK_OVERLAP_TOKENS', 80),
    ],

    'retention' => [
        'weather_snapshots_days' => 30,
        'ai_recommendations_days' => 90,
    ],
];
