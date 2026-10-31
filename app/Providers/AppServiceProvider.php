<?php

namespace App\Providers;

use App\Services\Ai\ActivityGenerationService;
use App\Services\Ai\AssistantService;
use App\Services\Ai\EmbeddingService;
use App\Services\Ai\KnowledgeRetriever;
use App\Services\Ai\OpenRouterService;
use App\Services\Assessment\ActivityAlternativeService;
use App\Services\Assessment\ActivityAssessmentService;
use App\Services\Assessment\ActivityEvaluationService;
use App\Services\Weather\OpenMeteoService;
use App\Services\Weather\WeatherAggregator;
use App\Services\Weather\WeatherSnapshotStore;
use App\Services\Weather\WeatherSummaryService;
use App\Support\ActivityScheduleExpander;
use App\Support\PlaceLabelResolver;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenMeteoService::class, fn () => new OpenMeteoService(
            $this->app->make(Factory::class),
            config('climaquest.weather'),
        ));

        $this->app->singleton(WeatherSnapshotStore::class, fn () => new WeatherSnapshotStore(
            $this->app->make(OpenMeteoService::class),
            config('climaquest.weather'),
        ));

        $this->app->singleton(WeatherSummaryService::class, fn () => new WeatherSummaryService(
            $this->app->make(OpenMeteoService::class),
            config('climaquest.weather'),
        ));

        $this->app->singleton(WeatherAggregator::class, fn () => new WeatherAggregator(
            config('climaquest.aggregation'),
            config('climaquest.weather_code_severity'),
            (int) config('climaquest.weather.overlap_min_minutes'),
        ));

        $this->app->singleton(ActivityAssessmentService::class, fn () => new ActivityAssessmentService(
            (float) config('climaquest.weather.coverage_threshold'),
            config('climaquest.assessment.score_bands'),
        ));

        $this->app->singleton(ActivityAlternativeService::class, fn () => new ActivityAlternativeService(
            $this->app->make(WeatherSnapshotStore::class),
            $this->app->make(WeatherAggregator::class),
            $this->app->make(ActivityAssessmentService::class),
            config('climaquest.assessment'),
        ));

        $this->app->singleton(OpenRouterService::class, fn () => new OpenRouterService(
            $this->app->make(Factory::class),
            config('climaquest.ai'),
        ));

        $this->app->singleton(EmbeddingService::class, fn () => new EmbeddingService(
            $this->app->make(Factory::class),
            config('climaquest.embeddings'),
        ));

        $this->app->singleton(KnowledgeRetriever::class, fn () => new KnowledgeRetriever(
            $this->app->make(EmbeddingService::class),
            config('climaquest.rag'),
        ));

        $this->app->singleton(AssistantService::class, fn () => new AssistantService(
            $this->app->make(ActivityEvaluationService::class),
            $this->app->make(ActivityAlternativeService::class),
            $this->app->make(WeatherSnapshotStore::class),
            $this->app->make(WeatherAggregator::class),
            $this->app->make(OpenRouterService::class),
            $this->app->make(KnowledgeRetriever::class),
            config('climaquest.ai'),
        ));

        $this->app->singleton(ActivityGenerationService::class, fn () => new ActivityGenerationService(
            $this->app->make(OpenRouterService::class),
            config('climaquest.ai'),
        ));

        $this->app->singleton(ActivityScheduleExpander::class, fn () => new ActivityScheduleExpander(
            (int) config('climaquest.ai.generation.horizon_days', 7),
        ));

        $this->app->singleton(PlaceLabelResolver::class, fn () => new PlaceLabelResolver(
            $this->app->make(OpenMeteoService::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
