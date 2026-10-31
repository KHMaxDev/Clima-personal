export function weatherLabel(code, labels = {}) {
    if (code === null || code === undefined) {
        return 'Sin datos';
    }

    return labels[code] ?? labels[String(code)] ?? 'Condición desconocida';
}

export function windDirection(degrees) {
    if (degrees === null || degrees === undefined) {
        return '';
    }

    const points = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];

    return points[Math.round(degrees / 45) % 8];
}

export function round(value, digits = 0) {
    if (value === null || value === undefined) {
        return null;
    }

    const factor = 10 ** digits;

    return Math.round(value * factor) / factor;
}

export function formatHour(iso, timezone) {
    try {
        return new Intl.DateTimeFormat('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
            timeZone: timezone || undefined,
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

export function formatDateTime(iso, timezone) {
    try {
        return new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: timezone || undefined,
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

export function formatDay(iso, timezone) {
    try {
        return new Intl.DateTimeFormat('es-MX', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            timeZone: timezone || undefined,
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}
