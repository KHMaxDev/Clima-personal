/**
 * Traduce el `weather_code` (WMO) a una familia visual para el fondo animado.
 */
export function visualForCode(code) {
    if (code === null || code === undefined) {
        return 'clear';
    }

    if (code <= 1) return 'clear';
    if (code === 2) return 'partly';
    if (code === 3) return 'cloudy';
    if (code === 45 || code === 48) return 'fog';
    if (code >= 51 && code <= 57) return 'drizzle';
    if (code >= 61 && code <= 67) return 'rain';
    if (code >= 71 && code <= 77) return 'snow';
    if (code >= 80 && code <= 82) return 'rain';
    if (code === 85 || code === 86) return 'snow';
    if (code >= 95) return 'storm';

    return 'clear';
}

export function localHour(iso, timezone) {
    try {
        const formatted = new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            hour12: false,
            timeZone: timezone || undefined,
        }).format(new Date(iso));

        return Number.parseInt(formatted, 10) % 24;
    } catch {
        return new Date().getHours();
    }
}

export function isDaytime(iso, timezone) {
    if (!iso) {
        return true;
    }

    const hour = localHour(iso, timezone);

    return hour >= 6 && hour < 19;
}
