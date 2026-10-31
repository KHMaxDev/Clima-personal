@extends('layouts.app')

@section('title', 'Actividades')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold heading">Actividades</h1>
        <a href="{{ route('activities.create') }}" class="btn-primary">
            Nueva actividad
        </a>
    </div>

    @if ($activities->isEmpty())
        <div class="glass-card border-dashed p-8 text-center text-sm text-muted">
            Todavía no tienes actividades.
            <a href="{{ route('activities.create') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Crea la primera</a>.
        </div>
    @else
        <div class="table-shell">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                <thead class="table-head">
                    <tr>
                        <th class="px-4 py-3">Título</th>
                        <th class="px-4 py-3">Ubicación</th>
                        <th class="px-4 py-3">Inicio</th>
                        <th class="px-4 py-3">Estado climático</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                    @foreach ($activities as $activity)
                        @php
                            $assessment = $activity->latestAssessment;
                            $status = $assessment?->status;
                        @endphp
                        <tr class="table-row">
                            <td class="px-4 py-3">
                                <a href="{{ route('activities.show', $activity) }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ $activity->title }}
                                </a>
                                @if ($activity->category)
                                    <span class="ml-2 text-xs text-muted">{{ $activity->category->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $activity->location_name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                {{ $activity->start_at->setTimezone($activity->timezone)->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                    style="background-color: {{ \App\Support\AssessmentStatus::color($status) }}">
                                    {{ \App\Support\AssessmentStatus::label($status) }}
                                </span>
                                @if ($assessment?->score !== null)
                                    <span class="ml-2 text-xs text-muted">{{ $assessment->score }}/100</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('activities.edit', $activity) }}" class="text-muted hover:text-indigo-600 dark:hover:text-indigo-400">Editar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $activities->links() }}
        </div>
    @endif
@endsection
