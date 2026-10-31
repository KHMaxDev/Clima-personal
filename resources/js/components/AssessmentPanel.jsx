const STATUS_STYLES = {
    favorable: {
        label: 'Favorable',
        className: 'bg-emerald-500/15 text-emerald-700 border-emerald-300/60 dark:text-emerald-300 dark:border-emerald-500/30',
    },
    favorable_with_precautions: {
        label: 'Favorable con precauciones',
        className: 'bg-amber-500/15 text-amber-700 border-amber-300/60 dark:text-amber-200 dark:border-amber-500/30',
    },
    unfavorable: {
        label: 'Desfavorable',
        className: 'bg-red-500/15 text-red-700 border-red-300/60 dark:text-red-300 dark:border-red-500/30',
    },
    high_risk: {
        label: 'Riesgo elevado',
        className: 'bg-red-500/25 text-red-800 border-red-400/70 dark:text-red-200 dark:border-red-500/40',
    },
    insufficient: {
        label: 'Información insuficiente',
        className: 'bg-slate-400/15 text-slate-700 border-slate-300/60 dark:text-slate-300 dark:border-white/15',
    },
};

const SEVERITY_STYLES = {
    info: 'text-slate-600 dark:text-slate-300',
    warning: 'text-amber-600 dark:text-amber-300',
    critical: 'text-red-600 dark:text-red-400',
};

export default function AssessmentPanel({
    assessment,
    loading = false,
    error = null,
    emptyMessage = 'Completa la ubicación y el horario para ver la evaluación.',
}) {
    if (loading) {
        return (
            <div className="glass-card p-4 text-sm text-muted">
                Evaluando condiciones…
            </div>
        );
    }

    if (error) {
        return (
            <div className="glass-card border-red-300/60 bg-red-50/70 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                {error}
            </div>
        );
    }

    if (!assessment) {
        return (
            <div className="glass-card border-dashed p-4 text-sm text-muted">
                {emptyMessage}
            </div>
        );
    }

    const status = STATUS_STYLES[assessment.status] ?? STATUS_STYLES.insufficient;
    const reasons = assessment.reasons ?? [];

    return (
        <div className="glass-card p-4">
            <div className="flex items-center justify-between gap-3">
                <span
                    className={`inline-flex items-center rounded-full border px-3 py-1 text-sm font-medium ${status.className}`}
                >
                    {status.label}
                </span>
                {assessment.score != null && (
                    <span className="text-2xl font-semibold heading">
                        {assessment.score}
                        <span className="text-sm font-normal text-muted">/100</span>
                    </span>
                )}
            </div>

            {assessment.coverage != null && (
                <p className="mt-3 text-xs text-muted">
                    Cobertura del pronóstico: {Math.round(Number(assessment.coverage) * 100)}%
                </p>
            )}

            <ul className="mt-3 space-y-2">
                {reasons.length === 0 && (
                    <li className="text-sm text-muted">Sin observaciones.</li>
                )}
                {reasons.map((reason, index) => (
                    <li key={`${reason.code}-${index}`} className="text-sm">
                        <span
                            className={`font-medium ${SEVERITY_STYLES[reason.severity] ?? 'text-slate-700 dark:text-slate-300'}`}
                        >
                            {reason.message}
                        </span>
                        {reason.threshold && (
                            <span className="text-muted">
                                {' '}
                                ({reason.variable}: {String(reason.value)} · umbral {reason.threshold})
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
