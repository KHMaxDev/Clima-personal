import { CloudOff, Droplets, MapPin, Wind } from 'lucide-react';
import WeatherIcon from './WeatherIcon';
import { round, weatherLabel, windDirection, formatDateTime } from '../lib/weather';

function Metric({ label, value, unit }) {
    return (
        <div className="glass-inset px-3 py-2">
            <p className="text-[0.7rem] uppercase tracking-wide text-muted">{label}</p>
            <p className="mt-0.5 text-sm font-semibold heading">
                {value ?? '—'}
                {value !== null && value !== undefined && unit ? ` ${unit}` : ''}
            </p>
        </div>
    );
}

function currentHourPrecipitation(weather) {
    const target = weather?.current?.time;
    const hourly = weather?.hourly ?? [];

    if (!target || hourly.length === 0) {
        return null;
    }

    const targetTime = new Date(target).getTime();
    let closest = null;
    let distance = Infinity;

    for (const entry of hourly) {
        const diff = Math.abs(new Date(entry.time).getTime() - targetTime);
        if (diff < distance) {
            distance = diff;
            closest = entry;
        }
    }

    return closest;
}

export default function WeatherCard({ location, weather, state, labels = {}, className = '' }) {
    if (!location) {
        return (
            <div className={`glass-card flex items-center gap-3 border-dashed p-6 text-sm text-muted ${className}`}>
                <MapPin className="h-4 w-4 shrink-0" strokeWidth={1.75} aria-hidden="true" />
                Crea una actividad con ubicación para ver el clima de tu zona.
            </div>
        );
    }

    if (state === 'loading' && !weather) {
        return (
            <div className={`glass-card animate-pulse p-6 ${className}`}>
                <div className="h-4 w-32 rounded bg-slate-200/70 dark:bg-white/10" />
                <div className="mt-4 h-12 w-24 rounded bg-slate-200/70 dark:bg-white/10" />
            </div>
        );
    }

    if (state === 'error' || !weather) {
        return (
            <div className={`glass-card flex items-center gap-3 border-amber-300/60 bg-amber-50/70 p-6 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200 ${className}`}>
                <CloudOff className="h-4 w-4 shrink-0" strokeWidth={1.75} aria-hidden="true" />
                No se pudo obtener el pronóstico. Intenta de nuevo.
            </div>
        );
    }

    const current = weather.current?.values ?? {};
    const currentHour = currentHourPrecipitation(weather);
    const timezone = weather.timezone ?? location.timezone;

    return (
        <section className={`glass-card lift p-5 ${className}`} aria-live="polite">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted">
                        <MapPin className="h-3.5 w-3.5" strokeWidth={1.75} aria-hidden="true" />
                        {location.name ?? 'Ubicación'}
                    </p>
                    <p className="mt-1 text-sm text-muted">
                        {formatDateTime(weather.updated_at, timezone)}
                    </p>
                </div>
                <span className="rounded-2xl bg-indigo-500/10 p-3 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                    <WeatherIcon code={current.weather_code} className="h-8 w-8" />
                </span>
            </div>

            <div className="mt-3 flex items-end gap-3">
                <p className="text-5xl font-semibold tracking-tight heading">
                    {round(current.temperature_2m)}°
                </p>
                <div className="pb-1">
                    <p className="text-sm font-medium heading">
                        {weatherLabel(current.weather_code, labels)}
                    </p>
                    <p className="text-xs text-muted">
                        Sensación {round(current.apparent_temperature)}°C
                    </p>
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                <span className="inline-flex items-center gap-1.5">
                    <Wind className="h-3.5 w-3.5" strokeWidth={1.75} aria-hidden="true" />
                    {round(current.wind_speed_10m)} km/h {windDirection(current.wind_direction_10m)}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <Droplets className="h-3.5 w-3.5" strokeWidth={1.75} aria-hidden="true" />
                    {round(current.relative_humidity_2m)}%
                </span>
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <Metric label="Viento" value={round(current.wind_speed_10m)} unit="km/h" />
                <Metric label="Rachas" value={round(current.wind_gusts_10m)} unit="km/h" />
                <Metric label="Humedad" value={round(current.relative_humidity_2m)} unit="%" />
                <Metric label="UV" value={round(current.uv_index, 1)} />
                <Metric label="Lluvia" value={round(currentHour?.precipitation_probability)} unit="%" />
                <Metric label="Precipitación" value={round(current.precipitation, 1)} unit="mm" />
                <Metric label="Dirección" value={windDirection(current.wind_direction_10m) || '—'} />
                <Metric label="Nubes" value={round(current.cloud_cover)} unit="%" />
            </dl>

            <p className="mt-3 text-right text-[0.7rem] text-muted">
                Datos:{' '}
                <a
                    href="https://open-meteo.com/"
                    target="_blank"
                    rel="noreferrer"
                    className="underline transition hover:text-indigo-500"
                >
                    Open-Meteo
                </a>
            </p>
        </section>
    );
}
