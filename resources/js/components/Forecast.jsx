import { useState } from 'react';
import HourlyStrip from './HourlyStrip';
import LocationSearch from './LocationSearch';
import WeatherCard from './WeatherCard';
import WeatherIcon from './WeatherIcon';
import useWeather from '../lib/useWeather';
import { formatHour, round, weatherLabel } from '../lib/weather';

export default function Forecast({
    location: initialLocation = null,
    weatherUrl = null,
    locationsUrl = null,
    weatherLabels = {},
}) {
    const [location, setLocation] = useState(initialLocation);
    const { weather, state } = useWeather(location, weatherUrl);

    const hourly = (weather?.hourly ?? []).slice(0, 24);
    const timezone = weather?.timezone ?? location?.timezone;

    return (
        <div className="space-y-6">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold heading">Pronóstico</h1>
                    <p className="text-sm text-muted">
                        Consulta el clima actual y por horas de cualquier ubicación.
                    </p>
                </div>
                <LocationSearch
                    locationsUrl={locationsUrl}
                    onSelect={(selected) =>
                        setLocation({
                            name: selected.name,
                            latitude: selected.latitude,
                            longitude: selected.longitude,
                            timezone: selected.timezone,
                        })
                    }
                />
            </header>

            {!location ? (
                <div className="glass-card border-dashed p-8 text-center text-sm text-muted">
                    Busca una ubicación para ver su pronóstico.
                </div>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[1fr_1fr]">
                    <WeatherCard
                        location={location}
                        weather={weather}
                        state={state}
                        labels={weatherLabels}
                    />
                    <HourlyStrip weather={weather} hours={12} labels={weatherLabels} />
                </div>
            )}

            {hourly.length > 0 && (
                <section className="table-shell">
                    <h3 className="border-b border-slate-200/70 px-4 py-3 text-sm font-semibold heading dark:border-white/10">
                        Detalle por horas
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[36rem] text-left text-sm">
                            <thead className="table-head">
                                <tr>
                                    <th className="px-4 py-2">Hora</th>
                                    <th className="px-4 py-2">Condición</th>
                                    <th className="px-4 py-2">Temp.</th>
                                    <th className="px-4 py-2">Sensación</th>
                                    <th className="px-4 py-2">Lluvia</th>
                                    <th className="px-4 py-2">Viento</th>
                                    <th className="px-4 py-2">UV</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-white/10">
                                {hourly.map((entry) => (
                                    <tr key={entry.time} className="table-row">
                                        <td className="px-4 py-2 font-medium heading">
                                            {formatHour(entry.time, timezone)}
                                        </td>
                                        <td className="px-4 py-2 text-slate-600 dark:text-slate-300">
                                            <span className="mr-1.5 inline-flex align-middle text-indigo-500 dark:text-indigo-300">
                                                <WeatherIcon code={entry.weather_code} className="h-4 w-4" />
                                            </span>
                                            {weatherLabel(entry.weather_code, weatherLabels)}
                                        </td>
                                        <td className="px-4 py-2">
                                            {round(entry.temperature_2m)}°C
                                        </td>
                                        <td className="px-4 py-2">
                                            {round(entry.apparent_temperature)}°C
                                        </td>
                                        <td className="px-4 py-2">
                                            {round(entry.precipitation_probability) ?? 0}%
                                        </td>
                                        <td className="px-4 py-2">
                                            {round(entry.wind_speed_10m)} km/h
                                        </td>
                                        <td className="px-4 py-2">
                                            {round(entry.uv_index, 1)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </div>
    );
}
