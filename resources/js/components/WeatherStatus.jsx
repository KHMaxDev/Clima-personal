import { useWeatherStore } from '../lib/weatherStore';
import { weatherLabel } from '../lib/weather';
import WeatherIcon from './WeatherIcon';

export default function WeatherStatus({ weatherLabels = {} }) {
    const { weather, place, location, status } = useWeatherStore();

    const values = weather?.current?.values ?? null;

    if (!values) {
        return null;
    }

    const temperature = Math.round(values.temperature_2m);
    const code = values.weather_code;
    const city = place?.city ?? location?.name ?? 'Ubicación actual';
    const region = place?.state ?? place?.country ?? '';
    const label = weatherLabel(code, weatherLabels);

    return (
        <div
            className="glass-soft flex items-center gap-2.5 rounded-xl px-2.5 py-1.5 shadow-sm"
            title={`${label} · ${city}${region ? `, ${region}` : ''}`}
            data-status={status}
        >
            <WeatherIcon code={code} className="h-5 w-5 text-indigo-500 dark:text-indigo-300" strokeWidth={1.75} />

            <span className="text-sm font-semibold text-slate-900 tabular-nums dark:text-white">
                {temperature}°
            </span>

            <span className="flex min-w-0 flex-col leading-tight">
                <span className="truncate text-[11px] font-medium text-slate-600 dark:text-slate-300">
                    {label}
                </span>
                <span className="truncate text-[11px] text-slate-500 dark:text-slate-400">
                    {city}
                    {region ? `, ${region}` : ''}
                </span>
            </span>
        </div>
    );
}
