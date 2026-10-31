import { useEffect, useState } from 'react';
import { apiFetch } from '../lib/api';
import AssessmentPanel from './AssessmentPanel';

const emptyForm = {
    title: '',
    description: '',
    category_id: '',
    location_name: '',
    latitude: '',
    longitude: '',
    timezone: '',
    start_at: '',
    end_at: '',
};

function useDebounced(value, delay) {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delay);

        return () => clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}

export default function ActivityForm({
    action,
    method = 'POST',
    categories = [],
    activity = null,
    assessUrl,
    locationsUrl,
    cancelUrl = '/activities',
}) {
    const [form, setForm] = useState(() => ({ ...emptyForm, ...(activity ?? {}) }));
    const [query, setQuery] = useState(activity?.location_name ?? '');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [assessment, setAssessment] = useState(null);
    const [assessing, setAssessing] = useState(false);
    const [assessError, setAssessError] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const debouncedQuery = useDebounced(query, 350);

    const update = (key, value) =>
        setForm((previous) => ({ ...previous, [key]: value }));

    const buildPayload = () => ({
        title: form.title,
        description: form.description || null,
        category_id: form.category_id === '' ? null : Number(form.category_id),
        location_name: form.location_name,
        latitude: form.latitude === '' ? null : Number(form.latitude),
        longitude: form.longitude === '' ? null : Number(form.longitude),
        timezone: form.timezone,
        start_at: form.start_at,
        end_at: form.end_at,
    });

    useEffect(() => {
        if (!locationsUrl) {
            return;
        }

        const trimmed = debouncedQuery.trim();

        if (trimmed.length < 2 || trimmed === form.location_name) {
            setResults([]);

            return;
        }

        const controller = new AbortController();

        (async () => {
            setSearching(true);

            try {
                const data = await apiFetch(
                    `${locationsUrl}?q=${encodeURIComponent(trimmed)}`,
                    { signal: controller.signal },
                );
                setResults(data?.results ?? []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setResults([]);
                }
            } finally {
                setSearching(false);
            }
        })();

        return () => controller.abort();
    }, [debouncedQuery, locationsUrl, form.location_name]);

    const canAssess = Boolean(
        assessUrl &&
            form.latitude !== '' &&
            form.longitude !== '' &&
            form.timezone &&
            form.start_at &&
            form.end_at,
    );

    const assessSignature = [
        form.latitude,
        form.longitude,
        form.timezone,
        form.start_at,
        form.end_at,
        form.category_id,
    ].join('|');

    useEffect(() => {
        if (!canAssess) {
            setAssessment(null);
            setAssessError(null);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setAssessing(true);
            setAssessError(null);

            try {
                const data = await apiFetch(assessUrl, {
                    method: 'POST',
                    json: buildPayload(),
                    signal: controller.signal,
                });
                setAssessment(data);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setAssessment(null);
                    setAssessError(error.data?.message ?? error.message);
                }
            } finally {
                setAssessing(false);
            }
        }, 500);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [assessSignature, assessUrl, canAssess]);

    const selectLocation = (place) => {
        setForm((previous) => ({
            ...previous,
            location_name: place.name,
            latitude: place.latitude,
            longitude: place.longitude,
            timezone: place.timezone || previous.timezone,
        }));
        setQuery(place.name);
        setResults([]);
    };

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        try {
            const data = await apiFetch(action, {
                method,
                json: buildPayload(),
            });
            window.location.href = data?.redirect ?? cancelUrl;
        } catch (error) {
            if (error.status === 422 && error.data?.errors) {
                setErrors(error.data.errors);
            } else {
                setErrors({
                    title: [error.data?.message ?? error.message],
                });
            }
        } finally {
            setSubmitting(false);
        }
    };

    const fieldError = (name) => errors[name]?.[0];

    return (
        <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div className="glass-card space-y-4 p-5">
                <div>
                    <label className="field-label">Título</label>
                    <input
                        type="text"
                        value={form.title}
                        onChange={(event) => update('title', event.target.value)}
                        className="field-input"
                        required
                        maxLength={160}
                    />
                    {fieldError('title') && (
                        <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                            {fieldError('title')}
                        </p>
                    )}
                </div>

                <div>
                    <label className="field-label">Categoría</label>
                    <select
                        value={form.category_id ?? ''}
                        onChange={(event) => update('category_id', event.target.value)}
                        className="field-input"
                    >
                        <option value="">Sin categoría</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="relative">
                    <label className="field-label">Ubicación</label>
                    <input
                        type="text"
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            update('location_name', '');
                        }}
                        placeholder="Busca una ciudad o lugar"
                        className="field-input"
                        autoComplete="off"
                    />
                    {searching && <p className="mt-1 text-xs text-muted">Buscando…</p>}
                    {results.length > 0 && (
                        <ul className="glass-card absolute z-10 mt-1 max-h-56 w-full overflow-auto p-1">
                            {results.map((place, index) => (
                                <li key={`${place.latitude}-${place.longitude}-${index}`}>
                                    <button
                                        type="button"
                                        onClick={() => selectLocation(place)}
                                        className="block w-full rounded-lg px-3 py-2 text-left text-sm transition hover:bg-indigo-500/10"
                                    >
                                        <span className="font-medium heading">{place.name}</span>
                                        <span className="text-muted">
                                            {' '}
                                            {[place.admin1, place.country].filter(Boolean).join(', ')}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {form.location_name && (
                        <p className="mt-1 text-xs text-muted">
                            {form.location_name} · {form.latitude},{form.longitude} · {form.timezone}
                        </p>
                    )}
                    {fieldError('location_name') && (
                        <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                            {fieldError('location_name')}
                        </p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="field-label">Inicio</label>
                        <input
                            type="datetime-local"
                            value={form.start_at}
                            onChange={(event) => update('start_at', event.target.value)}
                            className="field-input"
                            required
                        />
                        {fieldError('start_at') && (
                            <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                                {fieldError('start_at')}
                            </p>
                        )}
                    </div>
                    <div>
                        <label className="field-label">Fin</label>
                        <input
                            type="datetime-local"
                            value={form.end_at}
                            onChange={(event) => update('end_at', event.target.value)}
                            className="field-input"
                            required
                        />
                        {fieldError('end_at') && (
                            <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                                {fieldError('end_at')}
                            </p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="field-label">Descripción</label>
                    <textarea
                        value={form.description}
                        onChange={(event) => update('description', event.target.value)}
                        rows={3}
                        className="field-input"
                    />
                </div>

                <div className="flex gap-3">
                    <button type="submit" disabled={submitting} className="btn-primary">
                        {submitting ? 'Guardando…' : 'Guardar actividad'}
                    </button>
                    <a href={cancelUrl} className="btn-secondary">
                        Cancelar
                    </a>
                </div>
            </div>

            <aside className="space-y-3">
                <h3 className="panel-title">Evaluación preliminar</h3>
                <AssessmentPanel
                    assessment={assessment}
                    loading={assessing}
                    error={assessError}
                />
            </aside>
        </form>
    );
}
