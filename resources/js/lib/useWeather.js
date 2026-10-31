import { useEffect, useState } from 'react';
import { apiFetch } from './api';

export default function useWeather(location, weatherUrl) {
    const [weather, setWeather] = useState(null);
    const [state, setState] = useState('idle');

    useEffect(() => {
        if (!location?.latitude || !location?.longitude || !weatherUrl) {
            setWeather(null);
            setState('idle');

            return undefined;
        }

        let active = true;
        setState('loading');

        const params = new URLSearchParams({
            latitude: location.latitude,
            longitude: location.longitude,
            timezone: location.timezone || 'UTC',
        });

        apiFetch(`${weatherUrl}?${params.toString()}`)
            .then((data) => {
                if (active) {
                    setWeather(data);
                    setState('ready');
                }
            })
            .catch(() => {
                if (active) {
                    setWeather(null);
                    setState('error');
                }
            });

        return () => {
            active = false;
        };
    }, [location?.latitude, location?.longitude, location?.timezone, weatherUrl]);

    return { weather, state };
}
