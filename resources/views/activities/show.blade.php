@extends('layouts.app')

@section('title', $activity->title)

@section('content')
    @php
        $assessment = $activity->latestAssessment;
        $status = $assessment?->status;
    @endphp

    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold heading">{{ $activity->title }}</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $activity->location_name }} ·
                {{ $activity->start_at->setTimezone($activity->timezone)->translatedFormat('d M Y, H:i') }}
                → {{ $activity->end_at->setTimezone($activity->timezone)->translatedFormat('H:i') }}
                ({{ $activity->timezone }})
            </p>
            @if ($activity->category)
                <p class="mt-1 text-xs text-muted">Categoría: {{ $activity->category->name }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('activities.edit', $activity) }}" class="btn-secondary">
                Editar
            </a>
            <form method="POST" action="{{ route('activities.destroy', $activity) }}"
                onsubmit="return confirm('¿Eliminar esta actividad?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    @if ($activity->description)
        <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">{{ $activity->description }}</p>
    @endif

    <div class="glass-card p-5">
        <div class="flex items-center justify-between gap-3">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium text-white"
                style="background-color: {{ \App\Support\AssessmentStatus::color($status) }}">
                {{ \App\Support\AssessmentStatus::label($status) }}
            </span>
            @if ($assessment?->score !== null)
                <span class="text-2xl font-semibold heading">
                    {{ $assessment->score }}<span class="text-sm font-normal text-muted">/100</span>
                </span>
            @endif
        </div>

        @if ($assessment)
            <p class="mt-3 text-xs text-muted">
                Cobertura del pronóstico: {{ number_format((float) $assessment->coverage * 100, 0) }}%
            </p>

            <ul class="mt-4 space-y-2">
                @forelse (($assessment->reasons ?? []) as $reason)
                    <li class="text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $reason['message'] ?? '' }}</span>
                        @if (! empty($reason['threshold']))
                            <span class="text-muted">
                                ({{ $reason['variable'] }}: {{ $reason['value'] }} · umbral {{ $reason['threshold'] }})
                            </span>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-muted">Sin observaciones.</li>
                @endforelse
            </ul>
        @else
            <p class="mt-3 text-sm text-muted">
                Esta actividad aún no tiene evaluación.
            </p>
        @endif
    </div>
@endsection
