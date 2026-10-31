import { useState } from 'react';
import { BookOpen, Sparkles, TriangleAlert } from 'lucide-react';
import StatusBadge from './StatusBadge';
import { apiFetch } from '../lib/api';
import { formatDateTime } from '../lib/weather';

function Panel({ title, items, tone = 'slate' }) {
    if (!items || items.length === 0) {
        return null;
    }

    const tones = {
        slate: 'glass-card',
        emerald: 'glass-card border-emerald-300/60 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/10',
        amber: 'glass-card border-amber-300/60 bg-amber-50/70 dark:border-amber-500/30 dark:bg-amber-500/10',
    };

    return (
        <div className={`${tones[tone] ?? tones.slate} p-4`}>
            <h3 className="panel-title mb-2">{title}</h3>
            <ul className="list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">
                {items.map((item, index) => (
                    <li key={`${title}-${index}`}>{item}</li>
                ))}
            </ul>
        </div>
    );
}

export default function Assistant({
    activities = [],
    generateUrlTemplate = null,
    aiEnabled = false,
    model = null,
}) {
    const [selectedId, setSelectedId] = useState(activities[0]?.id ?? null);
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const selected = activities.find((item) => item.id === selectedId) ?? null;

    async function generate() {
        if (!selected || !generateUrlTemplate) {
            return;
        }

        setLoading(true);
        setError(null);
        setResult(null);

        try {
            const url = generateUrlTemplate.replace('__ID__', selected.id);
            const data = await apiFetch(url, { method: 'POST' });
            setResult(data);
        } catch (exception) {
            setError(exception.message ?? 'No se pudo generar la recomendación.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="space-y-6">
            <header>
                <h1 className="flex items-center gap-2 text-xl font-semibold heading">
                    <Sparkles className="h-5 w-5 text-indigo-500 dark:text-indigo-300" strokeWidth={1.75} aria-hidden="true" />
                    Asistente IA
                </h1>
                <p className="text-sm text-muted">
                    Recomendaciones para tus actividades, combinando el pronóstico y la
                    evaluación determinista con explicaciones en lenguaje natural.
                </p>
            </header>

            {!aiEnabled && (
                <div className="glass-card flex items-start gap-2 border-amber-300/60 bg-amber-50/70 px-4 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={1.75} aria-hidden="true" />
                    <span>
                        El asistente con IA no está configurado. Se mostrará una explicación
                        determinista. Define{' '}
                        <code className="rounded bg-amber-100/70 px-1 dark:bg-amber-500/20">
                            OPENROUTER_API_KEY
                        </code>{' '}
                        en el archivo <code>.env</code> para habilitar el modelo.
                    </span>
                </div>
            )}

            {activities.length === 0 ? (
                <div className="glass-card border-dashed p-8 text-center text-sm text-muted">
                    No tienes actividades próximas.{' '}
                    <a href="/activities/create" className="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        Crea una
                    </a>{' '}
                    para pedir recomendaciones.
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label className="flex-1 text-sm">
                            <span className="field-label">Actividad</span>
                            <select
                                value={selectedId ?? ''}
                                onChange={(event) => setSelectedId(Number(event.target.value))}
                                className="field-input"
                            >
                                {activities.map((activity) => (
                                    <option key={activity.id} value={activity.id}>
                                        {activity.title} · {activity.startLocal} · {activity.statusLabel}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <button
                            type="button"
                            onClick={generate}
                            disabled={loading || !selected}
                            className="btn-primary"
                        >
                            <Sparkles className="h-4 w-4" strokeWidth={1.75} aria-hidden="true" />
                            {loading ? 'Generando…' : 'Generar recomendación'}
                        </button>
                    </div>

                    {selected && (
                        <div className="glass-card flex flex-wrap items-center gap-3 p-4 text-sm text-slate-600 dark:text-slate-300">
                            <StatusBadge
                                status={selected.status}
                                label={selected.statusLabel}
                                color={selected.color}
                                icon={selected.icon}
                            />
                            <span>
                                {formatDateTime(selected.start, selected.timezone)}
                                {selected.location ? ` · ${selected.location}` : ''}
                            </span>
                            {selected.score !== null && selected.score !== undefined && (
                                <span>Puntaje {selected.score}/100</span>
                            )}
                        </div>
                    )}

                    {error && (
                        <div className="glass-card border-red-300/60 bg-red-50/70 px-4 py-2 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                            {error}
                        </div>
                    )}

                    <div aria-live="polite" aria-busy={loading} className="space-y-4">
                        {result && (
                            <div className="space-y-4">
                                <div className="glass-card p-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span
                                            className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                result.generated_by === 'ai'
                                                    ? 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300'
                                                    : 'bg-slate-400/15 text-slate-700 dark:text-slate-300'
                                            }`}
                                        >
                                            {result.generated_by === 'ai' ? (
                                                <>
                                                    <Sparkles className="h-3 w-3" strokeWidth={2} aria-hidden="true" />
                                                    {`IA · ${result.model}`}
                                                </>
                                            ) : (
                                                'Explicación determinista'
                                            )}
                                        </span>
                                        {result.cached && (
                                            <span className="text-xs text-muted">(en caché)</span>
                                        )}
                                    </div>
                                    <p className="text-sm text-slate-700 dark:text-slate-200">
                                        {result.summary}
                                    </p>
                                    {result.best_time && (
                                        <p className="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                            Mejor franja sugerida: {result.best_time}
                                        </p>
                                    )}
                                </div>

                                <Panel title="Precauciones" items={result.precautions} tone="amber" />
                                <Panel title="Recomendaciones" items={result.recommendations} tone="emerald" />

                                {result.sources?.length > 0 && (
                                    <div className="glass-card p-4 text-xs text-muted">
                                        <h3 className="panel-title mb-1 flex items-center gap-1.5">
                                            <BookOpen className="h-3.5 w-3.5" strokeWidth={1.75} aria-hidden="true" />
                                            Fuentes
                                        </h3>
                                        <ul className="list-disc pl-5">
                                            {result.sources.map((source) => (
                                                <li key={source.document_id}>{source.title}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
