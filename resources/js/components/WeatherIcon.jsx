import {
    Cloud,
    CloudDrizzle,
    CloudFog,
    CloudLightning,
    CloudRain,
    CloudSnow,
    CloudSun,
    Snowflake,
    Sun,
    Thermometer,
} from 'lucide-react';

const ICONS = {
    0: Sun,
    1: CloudSun,
    2: CloudSun,
    3: Cloud,
    45: CloudFog,
    48: CloudFog,
    51: CloudDrizzle,
    53: CloudDrizzle,
    55: CloudRain,
    56: CloudDrizzle,
    57: CloudDrizzle,
    61: CloudRain,
    63: CloudRain,
    65: CloudRain,
    66: CloudSnow,
    67: CloudSnow,
    71: CloudSnow,
    73: CloudSnow,
    75: Snowflake,
    77: Snowflake,
    80: CloudDrizzle,
    81: CloudRain,
    82: CloudLightning,
    85: CloudSnow,
    86: Snowflake,
    95: CloudLightning,
    96: CloudLightning,
    99: CloudLightning,
};

export function weatherIconFor(code) {
    return ICONS[code] ?? Thermometer;
}

export default function WeatherIcon({ code, className = 'h-5 w-5', strokeWidth = 1.75 }) {
    const Icon = weatherIconFor(code);

    return <Icon className={className} strokeWidth={strokeWidth} aria-hidden="true" />;
}
