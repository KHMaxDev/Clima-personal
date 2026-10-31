import { useEffect, useMemo } from 'react';
import { startWeatherAmbience, useWeatherStore } from '../lib/weatherStore';
import { isDaytime, visualForCode } from '../lib/weatherVisual';

function particles(count, seed) {
    return Array.from({ length: count }, (_, index) => {
        const a = Math.sin((index + 1) * seed) * 43758.5453;
        const b = Math.sin((index + 3) * seed * 1.7) * 24634.6345;
        const c = Math.sin((index + 7) * seed * 2.3) * 12453.1234;
        const frac = (value) => value - Math.floor(value);

        return {
            x: frac(a) * 100,
            delay: frac(b) * 6,
            duration: 3.6 + frac(c) * 3.4,
            drift: (frac(b) - 0.5) * 24,
        };
    });
}

export default function WeatherAmbience({ location, weatherUrl, placeUrl }) {
    const { weather, status } = useWeatherStore();

    useEffect(() => {
        startWeatherAmbience({ location, weatherUrl, placeUrl });
        // Solo se inicializa una vez por carga de página.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const code = weather?.current?.values?.weather_code ?? null;
    const bucket = status === 'ready' && code !== null ? visualForCode(code) : null;
    const period = weather && !isDaytime(weather.current?.time, weather.timezone) ? 'night' : 'day';

    useEffect(() => {
        const { body } = document;

        if (!bucket) {
            body.classList.remove('has-weather-bg');
            delete body.dataset.weather;
            delete body.dataset.period;

            return undefined;
        }

        body.classList.add('has-weather-bg');
        body.dataset.weather = bucket;
        body.dataset.period = period;

        return () => {
            body.classList.remove('has-weather-bg');
            delete body.dataset.weather;
            delete body.dataset.period;
        };
    }, [bucket, period]);

    const rain = useMemo(() => particles(44, 2.399), []);
    const snow = useMemo(() => particles(34, 5.117), []);
    const stars = useMemo(() => particles(30, 3.731), []);

    if (!bucket) {
        return null;
    }

    const showRain = bucket === 'rain' || bucket === 'storm';
    const showDrizzle = bucket === 'drizzle';
    const showSnow = bucket === 'snow';
    const showClouds = bucket === 'cloudy' || bucket === 'partly' || bucket === 'rain' || bucket === 'storm' || bucket === 'snow' || bucket === 'fog';
    const showSun = (bucket === 'clear' || bucket === 'partly') && period === 'day';
    const showStars = (bucket === 'clear' || bucket === 'partly') && period === 'night';
    const showFog = bucket === 'fog';

    return (
        <div className="weather-scene" data-weather={bucket} data-period={period} aria-hidden="true">
            <div className="weather-scene__sky" />

            {showStars && (
                <div className="weather-scene__stars">
                    {stars.map((star, index) => (
                        <span
                            key={index}
                            className="weather-scene__star"
                            style={{
                                left: `${star.x}%`,
                                top: `${(star.delay / 6) * 60}%`,
                                animationDelay: `${star.delay}s`,
                                animationDuration: `${2.4 + star.duration / 3}s`,
                            }}
                        />
                    ))}
                </div>
            )}

            {showSun && (
                <div className="weather-scene__sun">
                    <div className="weather-scene__sun-core" />
                    <div className="weather-scene__sun-glow" />
                </div>
            )}

            <div className="weather-scene__moon" />

            {showClouds && (
                <>
                    <div className="weather-scene__cloud weather-scene__cloud--a" />
                    <div className="weather-scene__cloud weather-scene__cloud--b" />
                    <div className="weather-scene__cloud weather-scene__cloud--c" />
                </>
            )}

            {showFog && (
                <>
                    <div className="weather-scene__fog weather-scene__fog--a" />
                    <div className="weather-scene__fog weather-scene__fog--b" />
                </>
            )}

            {(showRain || showDrizzle) && (
                <div className="weather-scene__rain">
                    {rain.map((drop, index) => (
                        <span
                            key={index}
                            className="weather-scene__drop"
                            style={{
                                left: `${drop.x}%`,
                                animationDelay: `${drop.delay}s`,
                                animationDuration: `${drop.duration}s`,
                                '--drift': `${drop.drift}px`,
                            }}
                        />
                    ))}
                </div>
            )}

            {showSnow && (
                <div className="weather-scene__snow">
                    {snow.map((flake, index) => (
                        <span
                            key={index}
                            className="weather-scene__flake"
                            style={{
                                left: `${flake.x}%`,
                                animationDelay: `${flake.delay}s`,
                                animationDuration: `${flake.duration + 3}s`,
                                '--drift': `${flake.drift}px`,
                            }}
                        />
                    ))}
                </div>
            )}

            {bucket === 'storm' && <div className="weather-scene__lightning" />}
        </div>
    );
}
