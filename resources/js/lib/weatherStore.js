import { useSyncExternalStore } from 'react';
import { apiFetch } from './api';

/**
 * Store de clima compartido por el fondo animado y el estado del navbar.
 * Resuelve la ubicación una sola vez (geolocalización si el permiso ya está
 * concedido, y si no la última registrada) y realiza un único fetch a
 * /weather y /weather/place.
 */
const initialState = {
    weatherUrl: null,
    placeUrl: null,
    location: null,
    source: null,
    weather: null,
    place: null,
    status: 'idle',
};

let state = initialState;
const listeners = new Set();
let started = false;

function emit() {
    for (const listener of listeners) {
        listener(state);
    }
}

function patch(changes) {
    state = { ...state, ...changes };
    emit();
}

export function subscribeWeather(listener) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function getWeatherSnapshot() {
    return state;
}

export function useWeatherStore() {
    return useSyncExternalStore(subscribeWeather, getWeatherSnapshot, getWeatherSnapshot);
}

async function geolocationIfGranted() {
    if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
        return null;
    }

    try {
        if (navigator.permissions) {
            const permission = await navigator.permissions.query({ name: 'geolocation' });

            if (permission.state !== 'granted') {
                return null;
            }
        }
    } catch {
        return null;
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => resolve(position),
            () => resolve(null),
            { timeout: 8000, maximumAge: 10 * 60 * 1000 },
        );
    });
}

async function fetchWeather(location) {
    if (location?.latitude == null || location?.longitude == null || !state.weatherUrl) {
        return;
    }

    const params = new URLSearchParams({
        latitude: location.latitude,
        longitude: location.longitude,
        timezone: location.timezone || 'UTC',
    });

    const weather = await apiFetch(`${state.weatherUrl}?${params.toString()}`);
    patch({ weather });
}

async function fetchPlace(location) {
    if (!location?.name || !state.placeUrl) {
        return;
    }

    const params = new URLSearchParams({ name: location.name });

    if (location.latitude != null) {
        params.set('latitude', location.latitude);
    }

    if (location.longitude != null) {
        params.set('longitude', location.longitude);
    }

    const data = await apiFetch(`${state.placeUrl}?${params.toString()}`);
    patch({ place: data?.place ?? null });
}

export async function startWeatherAmbience({ location, weatherUrl, placeUrl } = {}) {
    if (started) {
        return;
    }

    started = true;
    patch({ weatherUrl, placeUrl, location: location ?? null, status: 'loading' });

    let target = location ?? null;
    let source = target ? 'activity' : null;

    const position = await geolocationIfGranted();

    if (position) {
        target = {
            name: null,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
        };
        source = 'geolocation';
    }

    if (!target) {
        patch({ status: 'idle', source: null });

        return;
    }

    patch({ location: target, source });

    try {
        await Promise.all([fetchWeather(target), fetchPlace(target)]);
        patch({ status: 'ready' });
    } catch {
        patch({ status: 'error' });
    }
}
