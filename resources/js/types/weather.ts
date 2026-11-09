export type WeatherHour = {
    time: string;
    temperature: number | null;
    rainProbability: number | null;
    code: number | null;
    isDay: boolean | null;
};

export type FarmWeather = {
    farmId: string;
    status: 'ready' | 'stale' | 'unavailable' | 'missing_coordinates';
    forecast: {
        timezone: string;
        fetchedAt: string;
        observedAt: string;
        temperature: number;
        humidity: number;
        windSpeed: number;
        code: number;
        isDay: boolean;
        rainProbability: number | null;
        hours: WeatherHour[];
    } | null;
};
