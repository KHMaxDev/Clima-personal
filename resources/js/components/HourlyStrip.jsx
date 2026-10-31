import WeatherIcon from './WeatherIcon';
import { formatHour, round } from '../lib/weather';

export default function HourlyStrip({ weather, hours = 12, labels = {} }) {
    const hourly = weather?.hourly ?? [];
    const timezone = weather?.timezone;

    const now = Date.now();
    const upcoming = hourly
        .filter((entry) => new Date(entry.time).getTime() >= now - 30 * 60 * 1000)
        .slice(0, hours);

    if (upcoming.length === 0) {
        return null;
    }

    return (
        <section className="glass-card p-4">
            <h3 className="panel-title mb-3">Próximas horas</h3>
            <ul className="flex gap-2 overflow-x-auto pb-1">
                {upcoming.map((entry) => (
                    <li
                        key={entry.time}
                        className="flex min-w-[4.5rem] flex-col items-center gap-1.5 rounded-xl border border-slate-200/60 bg-white/50 px-2 py-2.5 dark:border-white/10 dark:bg-white/5"
                    >
                        <span className="text-xs text-muted">
                            {formatHour(entry.time, timezone)}
                        </span>
                        <WeatherIcon code={entry.weather_code} className="h-5 w-5 text-indigo-500 dark:text-indigo-300" />
                        <span className="text-sm font-semibold heading">
                            {round(entry.temperature_2m)}°
                        </span>
                        <span className="text-[0.7rem] text-indigo-500 dark:text-indigo-300">
                            {round(entry.precipitation_probability) ?? 0}%
                        </span>
                    </li>
                ))}
            </ul>
            <p className="sr-only">
                Pronóstico por horas con condición, temperatura y probabilidad de precipitación.
            </p>
        </section>
    );
}
