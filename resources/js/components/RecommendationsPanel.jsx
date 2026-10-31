import { BellRing } from 'lucide-react';

export default function RecommendationsPanel({ recommendations = [] }) {
    return (
        <section className="glass-card p-4">
            <h3 className="panel-title mb-3 flex items-center gap-2">
                <BellRing className="h-4 w-4 text-indigo-500 dark:text-indigo-300" strokeWidth={1.75} aria-hidden="true" />
                Recomendaciones
            </h3>

            {recommendations.length === 0 ? (
                <p className="text-sm text-muted">
                    Sin alertas por ahora para tus próximas actividades.
                </p>
            ) : (
                <ul className="space-y-2">
                    {recommendations.map((item) => (
                        <li key={item.id} className="glass-inset lift flex gap-3 p-3">
                            <span
                                className="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: item.color ?? '#6b7280' }}
                                aria-hidden="true"
                            />
                            <div>
                                <a
                                    href={item.url}
                                    className="text-sm font-medium heading transition hover:text-indigo-600 dark:hover:text-indigo-400"
                                >
                                    {item.activityTitle}
                                </a>
                                <p className="text-xs text-muted">{item.message}</p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
