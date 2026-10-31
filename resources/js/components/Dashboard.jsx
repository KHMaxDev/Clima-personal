import { useState } from 'react';
import { Bell, CalendarDays } from 'lucide-react';
import ActivityCalendar from './ActivityCalendar';
import HourlyStrip from './HourlyStrip';
import LocationSearch from './LocationSearch';
import NextActivityCard from './NextActivityCard';
import RecommendationsPanel from './RecommendationsPanel';
import StatusBadge from './StatusBadge';
import WeatherCard from './WeatherCard';
import useWeather from '../lib/useWeather';
import { formatDay } from '../lib/weather';

function NotificationsBell({ notifications = [] }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="relative inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white/70 text-slate-600 transition hover:bg-slate-100 dark:border-white/15 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                aria-label={`Notificaciones (${notifications.length})`}
                aria-expanded={open}
            >
                <Bell className="h-[1.15rem] w-[1.15rem]" strokeWidth={1.75} aria-hidden="true" />
                {notifications.length > 0 && (
                    <span className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-[0.65rem] font-semibold text-white">
                        {notifications.length}
                    </span>
                )}
            </button>

            {open && (
                <div className="glass-card absolute right-0 z-30 mt-2 w-80 p-2">
                    {notifications.length === 0 ? (
                        <p className="p-3 text-sm text-muted">
                            Todo en orden. No hay avisos.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100 dark:divide-white/10">
                            {notifications.map((item) => (
                                <li key={item.id}>
                                    <a
                                        href={item.url}
                                        className="block rounded-lg p-3 transition hover:bg-slate-100/70 dark:hover:bg-white/5"
                                    >
                                        <span className="flex items-center gap-2">
                                            <span
                                                className="h-2 w-2 rounded-full"
                                                style={{ backgroundColor: item.color }}
                                                aria-hidden="true"
                                            />
                                            <span className="text-sm font-medium heading">
                                                {item.title}
                                            </span>
                                        </span>
                                        <span className="mt-0.5 block text-xs text-muted">
                                            {item.statusLabel} · {item.when}
                                        </span>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

function DayAgenda({ activities = [] }) {
    return (
        <section className="glass-card p-4">
            <h3 className="panel-title mb-3">Actividades de hoy</h3>

            {activities.length === 0 ? (
                <p className="text-sm text-muted">
                    No tienes actividades programadas para hoy.
                </p>
            ) : (
                <ul className="space-y-2">
                    {activities.map((activity) => (
                        <li key={activity.id}>
                            <a
                                href={activity.url}
                                className="flex items-center justify-between gap-3 rounded-xl border border-slate-200/60 bg-white/50 px-3 py-2 transition hover:border-indigo-300 hover:bg-indigo-500/5 dark:border-white/10 dark:bg-white/5 dark:hover:border-indigo-400/40"
                            >
                                <span className="flex items-center gap-3">
                                    <span className="text-sm font-semibold heading">
                                        {activity.startLocal}
                                    </span>
                                    <span>
                                        <span className="block text-sm font-medium heading">
                                            {activity.title}
                                        </span>
                                        <span className="block text-xs text-muted">
                                            {activity.category ?? 'Sin categoría'}
                                            {activity.location ? ` · ${activity.location}` : ''}
                                        </span>
                                    </span>
                                </span>
                                <StatusBadge
                                    status={activity.status}
                                    label={activity.statusLabel}
                                    color={activity.color}
                                    icon={activity.icon}
                                />
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function Dashboard({
    dashboard = {},
    weatherUrl = null,
    locationsUrl = null,
    alternativesUrlTemplate = null,
    weatherLabels = {},
    userName = null,
}) {
    const [location, setLocation] = useState(dashboard.weatherLocation ?? null);
    const { weather, state } = useWeather(location, weatherUrl);

    const stats = dashboard.stats ?? {};

    return (
        <div className="space-y-6">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold heading">
                        {userName ? `Hola, ${userName}` : 'Hola'}
                    </h1>
                    <p className="text-sm text-muted capitalize">
                        {formatDay(new Date().toISOString(), location?.timezone)}
                    </p>
                </div>

                <div className="flex items-center gap-3">
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
                    <NotificationsBell notifications={dashboard.notifications ?? []} />
                </div>
            </header>

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <WeatherCard
                    location={location}
                    weather={weather}
                    state={state}
                    labels={weatherLabels}
                />
                <NextActivityCard
                    activity={dashboard.nextActivity}
                    alternativesUrlTemplate={alternativesUrlTemplate}
                />
            </div>

            <HourlyStrip weather={weather} labels={weatherLabels} />

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="space-y-6">
                    <DayAgenda activities={dashboard.dayActivities ?? []} />

                    <section className="space-y-3">
                        <h3 className="panel-title flex items-center gap-2">
                            <CalendarDays className="h-4 w-4 text-indigo-500 dark:text-indigo-300" strokeWidth={1.75} aria-hidden="true" />
                            Calendario semanal
                        </h3>
                        <ActivityCalendar
                            events={dashboard.events ?? []}
                            initialView="timeGridWeek"
                        />
                    </section>
                </div>

                <aside className="space-y-6">
                    <RecommendationsPanel recommendations={dashboard.recommendations ?? []} />

                    <section className="glass-card p-4">
                        <h3 className="panel-title mb-3">Resumen</h3>
                        <dl className="grid grid-cols-3 gap-2 text-center">
                            <div className="glass-inset py-2">
                                <dt className="text-xs text-muted">Actividades</dt>
                                <dd className="text-xl font-semibold heading">
                                    {stats.total ?? 0}
                                </dd>
                            </div>
                            <div className="glass-inset py-2">
                                <dt className="text-xs text-muted">Próximas</dt>
                                <dd className="text-xl font-semibold heading">
                                    {stats.upcoming ?? 0}
                                </dd>
                            </div>
                            <div className="glass-inset border-red-300/50 bg-red-50/60 py-2 dark:border-red-500/30 dark:bg-red-500/10">
                                <dt className="text-xs text-red-600 dark:text-red-300">Atención</dt>
                                <dd className="text-xl font-semibold text-red-700 dark:text-red-300">
                                    {stats.attention ?? 0}
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-3 flex flex-wrap gap-2">
                            {Object.entries(dashboard.statusLabels ?? {}).map(
                                ([status, label]) => (
                                    <span
                                        key={status}
                                        className="glass-inset inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs text-slate-600 dark:text-slate-300"
                                    >
                                        <span
                                            className="h-2 w-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    status === 'favorable'
                                                        ? '#16a34a'
                                                        : status === 'favorable_with_precautions'
                                                          ? '#ca8a04'
                                                          : status === 'unfavorable'
                                                            ? '#dc2626'
                                                            : status === 'high_risk'
                                                              ? '#991b1b'
                                                              : '#6b7280',
                                            }}
                                            aria-hidden="true"
                                        />
                                        {label}: {dashboard.statusCounts?.[status] ?? 0}
                                    </span>
                                ),
                            )}
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    );
}
