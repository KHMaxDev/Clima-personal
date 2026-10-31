import { useState } from 'react';
import { CalendarClock, Info, MapPin } from 'lucide-react';
import StatusBadge from './StatusBadge';
import { apiFetch } from '../lib/api';
import { formatDateTime } from '../lib/weather';

const SEVERITY_STYLES = {
    info: 'border-sky-300/60 bg-sky-50/70 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300',
    warning: 'border-amber-300/60 bg-amber-50/70 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    critical: 'border-red-300/60 bg-red-50/70 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
};

function countdown(iso) {
    const diff = new Date(iso).getTime() - Date.now();

    if (diff <= 0) {
        return 'en curso';
    }

    const totalMinutes = Math.floor(diff / 60000);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;

    if (days >= 1) {
        return `en ${days} día${days > 1 ? 's' : ''}`;
    }

    if (hours >= 1) {
        return `en ${hours} h ${minutes} min`;
    }

    return `en ${minutes} min`;
}

export default function NextActivityCard({ activity, alternativesUrlTemplate, className = '' }) {
    const [alternatives, setAlternatives] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    if (!activity) {
        return (
            <section className={`glass-card flex items-center gap-3 border-dashed p-5 text-sm text-muted ${className}`}>
                <Info className="h-4 w-4 shrink-0" strokeWidth={1.75} aria-hidden="true" />
                <span>
                    No tienes actividades próximas.{' '}
                    <a href="/activities/create" className="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        Crea una
                    </a>{' '}
                    para ver su pronóstico.
                </span>
            </section>
        );
    }

    async function loadAlternatives() {
        if (!alternativesUrlTemplate) {
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const url = alternativesUrlTemplate.replace('__ID__', activity.id);
            const data = await apiFetch(url);
            setAlternatives(data.alternatives ?? []);
        } catch (exception) {
            setError(exception.message ?? 'No se pudieron calcular alternativas.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <section className={`glass-card lift p-5 ${className}`}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs uppercase tracking-wide text-muted">
                        Próxima actividad · {activity.category ?? 'Sin categoría'}
                    </p>
                    <h3 className="mt-0.5 text-lg font-semibold heading">
                        {activity.title}
                    </h3>
                </div>
                <StatusBadge
                    status={activity.status}
                    label={activity.statusLabel}
                    color={activity.color}
                    icon={activity.icon}
                />
            </div>

            <p className="mt-2 flex items-center gap-1.5 text-sm text-muted">
                <MapPin className="h-3.5 w-3.5" strokeWidth={1.75} aria-hidden="true" />
                {formatDateTime(activity.start, activity.timezone)}
                {activity.location ? ` · ${activity.location}` : ''}
            </p>
            <p className="mt-1 flex items-center gap-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                <CalendarClock className="h-3.5 w-3.5" strokeWidth={1.75} aria-hidden="true" />
                {countdown(activity.start)}
                {activity.score !== null && activity.score !== undefined
                    ? ` · Puntaje ${activity.score}/100`
                    : ''}
            </p>

            {activity.reasons?.length > 0 && (
                <ul className="mt-3 space-y-1.5">
                    {activity.reasons.slice(0, 4).map((reason, index) => (
                        <li
                            key={`${reason.code}-${index}`}
                            className={`rounded-lg border px-3 py-1.5 text-xs ${SEVERITY_STYLES[reason.severity] ?? SEVERITY_STYLES.info}`}
                        >
                            {reason.message}
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <a href={activity.url} className="btn-primary">
                    Ver detalle
                </a>
                <button type="button" onClick={loadAlternatives} disabled={loading} className="btn-secondary">
                    {loading ? 'Buscando…' : 'Buscar horarios alternativos'}
                </button>
            </div>

            {error && (
                <p className="mt-3 rounded-lg border border-amber-300/60 bg-amber-50/70 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    {error}
                </p>
            )}

            {alternatives && (
                <div className="mt-3">
                    {alternatives.length === 0 ? (
                        <p className="glass-inset border-dashed px-3 py-2 text-xs text-muted">
                            No se encontró una alternativa mejor con los datos disponibles.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {alternatives.map((alternative) => (
                                <li
                                    key={`${alternative.start}-${alternative.score}`}
                                    className="flex items-center justify-between gap-3 rounded-lg border border-emerald-300/60 bg-emerald-50/70 px-3 py-2 text-sm dark:border-emerald-500/30 dark:bg-emerald-500/10"
                                >
                                    <span className="font-medium text-emerald-900 dark:text-emerald-200">
                                        {alternative.start_local}–{alternative.end_local}
                                    </span>
                                    <span className="text-xs text-emerald-800 dark:text-emerald-300">
                                        {alternative.status_label} · {alternative.score}/100
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </section>
    );
}
