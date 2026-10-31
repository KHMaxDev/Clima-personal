@extends('layouts.app')

@section('title', 'Historial')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold heading">Historial de evaluaciones</h1>
        <p class="text-sm text-muted">
            Registro reproducible de cada evaluación, con su puntaje y cobertura.
        </p>
    </div>

    <div class="table-shell">
        <table class="w-full text-left text-sm">
            <thead class="table-head">
                <tr>
                    <th class="px-4 py-2">Actividad</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">Puntaje</th>
                    <th class="px-4 py-2">Cobertura</th>
                    <th class="px-4 py-2">Evaluada</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                @forelse ($assessments as $assessment)
                    <tr class="table-row">
                        <td class="px-4 py-2">
                            @if ($assessment->activity)
                                <a href="{{ route('activities.show', $assessment->activity) }}" class="font-medium heading hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $assessment->activity->title }}
                                </a>
                                <span class="block text-xs text-muted">
                                    {{ $assessment->activity->category?->name ?? 'Sin categoría' }}
                                </span>
                            @else
                                <span class="text-muted">Actividad eliminada</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                style="background-color: {{ $statusColors[$assessment->status] ?? '#6b7280' }}"
                            >
                                {{ $statusLabels[$assessment->status] ?? 'Sin evaluar' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-slate-700 dark:text-slate-300">
                            {{ $assessment->score !== null ? $assessment->score.'/100' : '—' }}
                        </td>
                        <td class="px-4 py-2 text-slate-700 dark:text-slate-300">
                            {{ $assessment->coverage !== null ? number_format((float) $assessment->coverage * 100).'%' : '—' }}
                        </td>
                        <td class="px-4 py-2 text-muted">
                            {{ optional($assessment->evaluated_at)->isoFormat('D MMM YYYY, HH:mm') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-muted">
                            Aún no hay evaluaciones registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $assessments->links() }}
    </div>
@endsection
