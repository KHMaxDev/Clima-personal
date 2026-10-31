import { useMemo, useState } from 'react';
import {
    CircleCheck,
    Loader2,
    LocateFixed,
    MapPin,
    Send,
    Sparkles,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import LocationSearch from './LocationSearch';
import { apiFetch } from '../lib/api';
import { toast } from '../lib/toast';

const DAY_LABELS = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

function daySummary(days = []) {
    if (!Array.isArray(days) || days.length === 0) {
        return 'Próxima fecha';
    }

    if (days.length === 7) {
        return 'Todos los días';
    }

    return [1, 2, 3, 4, 5, 6, 7]
        .filter((day) => days.includes(day))
        .map((day) => DAY_LABELS[day])
        .join(' · ');
}

function StepBadge({ active, done, index, label }) {
    return (
        <span className={`inline-flex items-center gap-2 text-xs font-medium ${active || done ? 'text-indigo-600 dark:text-indigo-300' : 'text-muted'}`}>
            <span className={`flex h-5 w-5 items-center justify-center rounded-full text-[0.7rem] ${active || done ? 'bg-indigo-600 text-white' : 'bg-slate-300/70 text-slate-600 dark:bg-white/10 dark:text-slate-400'}`}>
                {done ? <CircleCheck className="h-3.5 w-3.5" strokeWidth={2.4} aria-hidden="true" /> : index}
            </span>
            {label}
        </span>
    );
}

export default function ActivityGenerator({
    interpretUrl,
    storeUrl,
    locationsUrl,
    categories = [],
    defaultTimezone = 'UTC',
    aiEnabled = false,
}) {
    const [step, setStep] = useState('describe');
    const [description, setDescription] = useState('');
    const [conversation, setConversation] = useState([]);
    const [answerDrafts, setAnswerDrafts] = useState({});
    const [summary, setSummary] = useState('');
    const [questions, setQuestions] = useState([]);
    const [activities, setActivities] = useState([]);
    const [loading, setLoading] = useState(false);
    const [creating, setCreating] = useState(false);
    const [location, setLocation] = useState(null);
    const [geolocating, setGeolocating] = useState(false);

    const timezone = location?.timezone || defaultTimezone;
    const canCreate = useMemo(
        () => Boolean(location) && activities.length > 0 && !creating,
        [location, activities, creating],
    );

    async function analyze(appendedAnswers) {
        setLoading(true);

        try {
            const data = await apiFetch(interpretUrl, {
                method: 'POST',
                json: {
                    description,
                    timezone,
                    answers: appendedAnswers ?? conversation,
                },
            });

            setSummary(data.context_summary ?? '');
            setQuestions(data.questions ?? []);
            setActivities(data.activities ?? []);
            setAnswerDrafts({});

            if (data.done) {
                setStep('review');
                toast.success('Listo: revisa las actividades propuestas.');
            } else {
                setStep('clarify');
            }
        } catch (error) {
            toast.error(error.data?.message ?? error.message ?? 'No se pudo analizar la descripción.');
        } finally {
            setLoading(false);
        }
    }

    function startAnalysis(event) {
        event.preventDefault();

        if (description.trim().length < 3) {
            toast.warning('Describe tu rutina con un poco más de detalle.');
            return;
        }

        setConversation([]);
        analyze([]);
    }

    function submitAnswers() {
        const pending = questions.map((question) => ({
            question: question.question,
            answer: (answerDrafts[question.id] ?? '').trim(),
        }));

        if (pending.some((item) => item.answer === '')) {
            toast.warning('Responde todas las preguntas para continuar.');
            return;
        }

        const merged = [...conversation, ...pending];
        setConversation(merged);
        analyze(merged);
    }

    function useCurrentLocation() {
        if (!navigator.geolocation) {
            toast.error('Tu navegador no permite obtener la ubicación.');
            return;
        }

        setGeolocating(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const zone = Intl.DateTimeFormat().resolvedOptions().timeZone || defaultTimezone;
                setLocation({
                    name: 'Mi ubicación actual',
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    timezone: zone,
                });
                setGeolocating(false);
                toast.info('Usando tu ubicación actual.');
            },
            () => {
                setGeolocating(false);
                toast.error('No pudimos obtener tu ubicación. Búscala manualmente.');
            },
            { enableHighAccuracy: false, timeout: 10000 },
        );
    }

    function removeActivity(index) {
        setActivities((current) => current.filter((_, i) => i !== index));
    }

    async function createActivities() {
        if (!location) {
            toast.warning('Elige una ubicación para las actividades.');
            return;
        }

        setCreating(true);

        try {
            const data = await apiFetch(storeUrl, {
                method: 'POST',
                json: {
                    timezone: location.timezone || defaultTimezone,
                    location_name: location.name,
                    latitude: location.latitude,
                    longitude: location.longitude,
                    activities: activities.map((activity) => ({
                        title: activity.title,
                        description: activity.description ?? null,
                        category: activity.category ?? null,
                        days: activity.days ?? [],
                        start_time: activity.start_time,
                        end_time: activity.end_time,
                        location_hint: activity.location_hint ?? null,
                    })),
                },
            });

            toast.success(data.message ?? 'Actividades creadas.', { title: '¡Hecho!' });

            if (data.weather_warning) {
                toast.warning('Algunas actividades no pudieron evaluarse por falta de pronóstico.');
            }

            window.location.href = data.redirect ?? '/activities';
        } catch (error) {
            toast.error(error.data?.message ?? error.message ?? 'No se pudieron crear las actividades.');
            setCreating(false);
        }
    }

    if (!aiEnabled) {
        return (
            <div className="glass-card flex items-start gap-3 border-amber-300/60 bg-amber-50/70 p-5 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <TriangleAlert className="mt-0.5 h-5 w-5 shrink-0" strokeWidth={1.75} aria-hidden="true" />
                <span>
                    La generación con IA no está configurada. Define{' '}
                    <code className="rounded bg-amber-100/70 px-1 dark:bg-amber-500/20">OPENROUTER_API_KEY</code>{' '}
                    para habilitarla, o crea la actividad de forma manual.
                </span>
            </div>
        );
    }

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                <StepBadge index={1} label="Describe" active={step === 'describe'} done={step !== 'describe'} />
                <StepBadge index={2} label="Aclara dudas" active={step === 'clarify'} done={step === 'review'} />
                <StepBadge index={3} label="Revisa y crea" active={step === 'review'} done={false} />
            </div>

            {step === 'describe' && (
                <form onSubmit={startAnalysis} className="glass-card space-y-4 p-5">
                    <div>
                        <label className="field-label" htmlFor="routine-description">
                            Cuéntame tu rutina o tus planes
                        </label>
                        <textarea
                            id="routine-description"
                            value={description}
                            onChange={(event) => setDescription(event.target.value)}
                            rows={6}
                            maxLength={4000}
                            placeholder="Ej: Tengo que ir a la escuela de lunes a viernes, mi hora de entrada es a las 3 hasta las 10 de la noche. De ahí tengo clases de danza una hora y luego voy al gimnasio."
                            className="field-input"
                        />
                        <p className="mt-1 text-xs text-muted">
                            La IA lo analizará, te hará preguntas si algo es ambiguo y propondrá las actividades.
                        </p>
                    </div>

                    <button type="submit" disabled={loading} className="btn-primary">
                        {loading ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <Sparkles className="h-4 w-4" aria-hidden="true" />}
                        {loading ? 'Analizando…' : 'Analizar con IA'}
                    </button>
                </form>
            )}

            {step === 'clarify' && (
                <div className="space-y-4">
                    {summary && (
                        <div className="glass-card p-4">
                            <h3 className="panel-title mb-1">Resumen de tu contexto</h3>
                            <p className="text-sm text-slate-600 dark:text-slate-300">{summary}</p>
                        </div>
                    )}

                    <div className="glass-card space-y-4 p-5">
                        <h3 className="panel-title">Necesito aclarar un par de cosas</h3>

                        {questions.map((question) => (
                            <div key={question.id}>
                                <label className="field-label" htmlFor={`q-${question.id}`}>
                                    {question.question}
                                </label>
                                <input
                                    id={`q-${question.id}`}
                                    type="text"
                                    value={answerDrafts[question.id] ?? ''}
                                    onChange={(event) => setAnswerDrafts((current) => ({
                                        ...current,
                                        [question.id]: event.target.value,
                                    }))}
                                    className="field-input"
                                    placeholder={question.hint ?? 'Tu respuesta'}
                                />
                            </div>
                        ))}

                        <button type="button" onClick={submitAnswers} disabled={loading} className="btn-primary">
                            {loading ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <Send className="h-4 w-4" aria-hidden="true" />}
                            {loading ? 'Enviando…' : 'Enviar respuestas'}
                        </button>
                    </div>

                    {activities.length > 0 && (
                        <div className="glass-card p-4">
                            <h3 className="panel-title mb-2">Borrador hasta ahora</h3>
                            <ul className="space-y-2">
                                {activities.map((activity, index) => (
                                    <li key={`${activity.title}-${index}`} className="glass-inset px-3 py-2 text-sm">
                                        <span className="font-medium heading">{activity.title}</span>
                                        <span className="text-muted">
                                            {' '}· {daySummary(activity.days)} · {activity.start_time}–{activity.end_time}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {step === 'review' && (
                <div className="space-y-4">
                    {summary && (
                        <div className="glass-card p-4">
                            <h3 className="panel-title mb-1">Resumen de tu contexto</h3>
                            <p className="text-sm text-slate-600 dark:text-slate-300">{summary}</p>
                        </div>
                    )}

                    <div className="glass-card p-5">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <h3 className="panel-title">
                                {activities.length} actividad(es) propuesta(s)
                            </h3>
                            <span className="text-xs text-muted">Revisa y elimina lo que no corresponda</span>
                        </div>

                        {activities.length === 0 ? (
                            <p className="text-sm text-muted">
                                No quedaron actividades. Reinicia y describe tu rutina de nuevo.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {activities.map((activity, index) => (
                                    <li
                                        key={`${activity.title}-${index}`}
                                        className="glass-inset flex items-start justify-between gap-3 p-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold heading">
                                                {activity.title}
                                                {activity.assumed && (
                                                    <span className="ml-2 rounded-md border border-amber-300/60 px-1.5 py-0.5 text-[0.6rem] uppercase tracking-wide text-amber-700 dark:border-amber-500/30 dark:text-amber-300">
                                                        supuesto
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-xs text-muted">
                                                {activity.category ?? 'Sin categoría'} · {daySummary(activity.days)} · {activity.start_time}–{activity.end_time}
                                            </p>
                                            {activity.location_hint && (
                                                <p className="mt-0.5 flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300">
                                                    <MapPin className="h-3 w-3" strokeWidth={2} aria-hidden="true" />
                                                    {activity.location_hint}
                                                </p>
                                            )}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeActivity(index)}
                                            className="shrink-0 rounded-lg p-1.5 text-muted transition hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400"
                                            aria-label={`Eliminar ${activity.title}`}
                                        >
                                            <Trash2 className="h-4 w-4" strokeWidth={1.75} aria-hidden="true" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="glass-card space-y-3 p-5">
                        <h3 className="panel-title">Ubicación de las actividades</h3>
                        <p className="text-xs text-muted">
                            Se aplica a todas las actividades generadas. Usa tu ubicación actual o búscala.
                        </p>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <LocationSearch
                                locationsUrl={locationsUrl}
                                onSelect={(selected) => setLocation({
                                    name: selected.name,
                                    latitude: selected.latitude,
                                    longitude: selected.longitude,
                                    timezone: selected.timezone,
                                })}
                            />
                            <button
                                type="button"
                                onClick={useCurrentLocation}
                                disabled={geolocating}
                                className="btn-secondary"
                            >
                                {geolocating ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <LocateFixed className="h-4 w-4" aria-hidden="true" />}
                                {geolocating ? 'Ubicando…' : 'Usar mi ubicación actual'}
                            </button>
                        </div>

                        {location && (
                            <p className="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                                <MapPin className="h-4 w-4 text-indigo-500 dark:text-indigo-300" strokeWidth={1.75} aria-hidden="true" />
                                {location.name} · {Number(location.latitude).toFixed(3)}, {Number(location.longitude).toFixed(3)} · {location.timezone || defaultTimezone}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button type="button" onClick={createActivities} disabled={!canCreate} className="btn-primary">
                            {creating ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <CircleCheck className="h-4 w-4" aria-hidden="true" />}
                            {creating ? 'Creando…' : 'Confirmar y crear actividades'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setStep('describe')}
                            className="btn-secondary"
                            disabled={creating}
                        >
                            Empezar de nuevo
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
