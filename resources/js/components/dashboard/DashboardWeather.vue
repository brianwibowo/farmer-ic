<script setup lang="ts">
import {
    Cloud,
    CloudDrizzle,
    CloudFog,
    CloudLightning,
    CloudRain,
    CloudSnow,
    CloudSun,
    Droplets,
    LoaderCircle,
    MapPin,
    Moon,
    RefreshCw,
    Sun,
    Wind,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import type { DashboardData } from '@/types/dashboard';
import type { FarmWeather } from '@/types/weather';

const props = defineProps<{ farms: DashboardData['landMap']['farms'] }>();
const selectedId = ref('');
const weather = ref<FarmWeather | null>(null);
const loading = ref(false);
const error = ref('');
const feedback = ref('');
const selectedFarm = computed(() =>
    props.farms.find((farm) => farm.id === selectedId.value),
);
const forecast = computed(() => weather.value?.forecast);
const upcomingHours = computed(
    () =>
        forecast.value?.hours.filter(
            (hour) => new Date(hour.time).getTime() > Date.now(),
        ) ?? [],
);
const REFRESH_MS = 15 * 60 * 1000;
let request: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;
let lastAttempt = 0;
let disposed = false;

watch(
    () => props.farms,
    (farms) => {
        if (!farms.some((farm) => farm.id === selectedId.value)) {
            selectedId.value =
                farms.find(
                    (farm) => farm.latitude !== null && farm.longitude !== null,
                )?.id ??
                farms[0]?.id ??
                '';
        }
    },
    { immediate: true },
);

function condition(code: number | null, isDay: boolean | null = true) {
    if (code === 0) return { label: 'Cerah', icon: isDay ? Sun : Moon };
    if (code === 1 || code === 2)
        return { label: 'Cerah berawan', icon: isDay ? CloudSun : Cloud };
    if (code === 3) return { label: 'Berawan', icon: Cloud };
    if (code === 45 || code === 48)
        return { label: 'Berkabut', icon: CloudFog };
    if ([51, 53, 55, 56, 57].includes(code ?? -1))
        return { label: 'Gerimis', icon: CloudDrizzle };
    if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code ?? -1))
        return { label: 'Hujan', icon: CloudRain };
    if ([71, 73, 75, 77, 85, 86].includes(code ?? -1))
        return { label: 'Salju', icon: CloudSnow };
    if ([95, 96, 99].includes(code ?? -1))
        return { label: 'Hujan petir', icon: CloudLightning };
    return { label: 'Kondisi belum tersedia', icon: Cloud };
}

const currentCondition = computed(() =>
    condition(forecast.value?.code ?? null, forecast.value?.isDay),
);
function number(value: number | null | undefined, unit = ''): string {
    return value == null
        ? '—'
        : `${Math.round(value).toLocaleString('id-ID')}${unit}`;
}
function time(value: string, includeDate = false): string {
    return new Intl.DateTimeFormat('id-ID', {
        timeZone: forecast.value?.timezone ?? 'Asia/Jakarta',
        ...(includeDate
            ? ({
                  day: 'numeric',
                  month: 'short',
                  timeZoneName: 'short',
              } as const)
            : {}),
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

async function loadWeather(manual = false): Promise<void> {
    request?.abort();
    request = undefined;
    clearTimeout(timer);
    feedback.value = '';
    const farm = selectedFarm.value;
    if (!farm) {
        loading.value = false;
        weather.value = null;
        error.value = '';
        return;
    }
    const current = new AbortController();
    request = current;
    if (weather.value?.farmId !== farm.id) weather.value = null;
    loading.value = true;
    error.value = '';
    lastAttempt = Date.now();
    const timeout = setTimeout(() => current.abort(), 12000);
    try {
        const response = await fetch(
            `/admin/dashboard/weather/${encodeURIComponent(farm.id)}`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: current.signal,
            },
        );
        if (request !== current || disposed) return;
        if (!response.ok || response.redirected) {
            if (
                [401, 403, 404, 419].includes(response.status) ||
                response.redirected
            )
                weather.value = null;
            throw new Error('Cuaca belum dapat diperbarui. Coba lagi.');
        }
        const result = (await response.json()) as { data: FarmWeather };
        if (request !== current || disposed) return;
        weather.value = result.data;
        if (manual && result.data.status === 'ready')
            feedback.value = 'Cuaca berhasil dimuat.';
    } catch {
        if (request !== current || disposed) return;
        error.value = 'Cuaca belum dapat diperbarui. Coba lagi.';
    } finally {
        clearTimeout(timeout);
        if (request === current && !disposed) {
            loading.value = false;
            timer = setTimeout(() => {
                if (!document.hidden) void loadWeather();
            }, REFRESH_MS);
        }
    }
}

function resume(): void {
    if (
        !document.hidden &&
        !loading.value &&
        Date.now() - lastAttempt >= REFRESH_MS
    )
        void loadWeather();
}

onMounted(() => {
    watch(
        () => [
            selectedFarm.value?.id,
            selectedFarm.value?.latitude,
            selectedFarm.value?.longitude,
        ],
        () => {
            weather.value = null;
            void loadWeather();
        },
        { immediate: true },
    );
    document.addEventListener('visibilitychange', resume);
});
onBeforeUnmount(() => {
    disposed = true;
    request?.abort();
    clearTimeout(timer);
    document.removeEventListener('visibilitychange', resume);
});
</script>

<template>
    <section
        class="dashboard-panel flex min-w-0 flex-col"
        aria-labelledby="weather-heading"
        :aria-busy="loading"
    >
        <div class="flex items-start justify-between gap-2">
            <h2 id="weather-heading" class="admin-section-title">
                Cuaca & prakiraan
            </h2>
            <Button
                v-if="selectedFarm"
                type="button"
                variant="ghost"
                size="icon-sm"
                class="shrink-0 text-muted-foreground"
                :disabled="loading"
                aria-label="Perbarui cuaca"
                title="Perbarui cuaca"
                @click="void loadWeather(true)"
            >
                <RefreshCw
                    :size="16"
                    :class="{
                        'animate-spin motion-reduce:animate-none': loading,
                    }"
                    aria-hidden="true"
                />
            </Button>
        </div>
        <div v-if="farms.length > 1" class="mt-3">
            <label for="weather-farm" class="sr-only"
                >Kebun untuk prakiraan cuaca</label
            >
            <AppSelect
                id="weather-farm"
                v-model="selectedId"
                class="admin-field w-full"
                aria-label="Kebun untuk prakiraan cuaca"
            >
                <AppSelectOption
                    v-for="farm in farms"
                    :key="farm.id"
                    :value="farm.id"
                    >{{ farm.name }}</AppSelectOption
                >
            </AppSelect>
        </div>
        <p
            v-else-if="selectedFarm"
            class="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground"
        >
            <MapPin :size="14" class="shrink-0" aria-hidden="true" />{{
                selectedFarm.name
            }}
        </p>

        <div
            v-if="loading && !forecast"
            class="flex min-h-72 flex-1 items-center justify-center gap-2 text-sm text-muted-foreground"
            role="status"
        >
            <LoaderCircle
                :size="18"
                class="animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />Memuat cuaca…
        </div>
        <div
            v-else-if="!forecast"
            class="flex min-h-72 flex-1 flex-col items-center justify-center gap-3 py-6 text-center"
            role="status"
        >
            <Cloud
                :size="32"
                class="text-muted-foreground"
                aria-hidden="true"
            />
            <p class="text-sm text-muted-foreground">
                {{
                    !selectedFarm
                        ? 'Belum ada kebun untuk ditampilkan.'
                        : weather?.status === 'missing_coordinates'
                          ? 'Lengkapi koordinat kebun untuk melihat cuaca.'
                          : error || 'Cuaca belum tersedia. Silakan coba lagi.'
                }}
            </p>
            <Button
                v-if="selectedFarm && weather?.status !== 'missing_coordinates'"
                type="button"
                variant="outline"
                size="sm"
                @click="void loadWeather()"
                >Coba lagi</Button
            >
        </div>
        <template v-else>
            <div class="mt-5 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{
                            error || weather?.status === 'stale'
                                ? 'Kondisi terakhir'
                                : 'Kondisi terkini'
                        }}
                    </p>
                    <p class="mt-1 text-4xl font-bold tabular-nums">
                        {{ number(forecast.temperature)
                        }}<span
                            class="ml-1 text-xl font-medium text-muted-foreground"
                            >°C</span
                        >
                    </p>
                </div>
                <component
                    :is="currentCondition.icon"
                    :size="44"
                    :stroke-width="1.5"
                    class="shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>
            <p class="mt-2 text-sm font-semibold">
                {{ currentCondition.label }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Data {{ time(forecast.observedAt, true) }}
            </p>
            <dl class="mt-5 grid gap-3 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <CloudRain :size="16" aria-hidden="true" />Peluang hujan
                    </dt>
                    <dd class="font-bold tabular-nums">
                        {{ number(forecast.rainProbability, '%') }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <Droplets :size="16" aria-hidden="true" />Kelembapan
                    </dt>
                    <dd class="font-bold tabular-nums">
                        {{ number(forecast.humidity, '%') }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <Wind :size="16" aria-hidden="true" />Angin
                    </dt>
                    <dd class="font-bold tabular-nums">
                        {{ number(forecast.windSpeed, ' km/j') }}
                    </dd>
                </div>
            </dl>
            <div class="mt-5 border-t border-border pt-4">
                <h3 class="text-xs font-semibold">Beberapa jam ke depan</h3>
                <ul
                    v-if="upcomingHours.length"
                    class="mt-3 grid grid-cols-3 gap-2 text-center"
                >
                    <li
                        v-for="hour in upcomingHours"
                        :key="hour.time"
                        class="flex min-w-0 flex-col items-center gap-2"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ time(hour.time) }}
                        </p>
                        <component
                            :is="condition(hour.code, hour.isDay).icon"
                            :size="20"
                            class="text-primary"
                            aria-hidden="true"
                        />
                        <span class="sr-only">{{
                            condition(hour.code, hour.isDay).label
                        }}</span>
                        <p class="text-sm font-bold tabular-nums">
                            {{ number(hour.temperature, '°') }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <span class="sr-only">Peluang hujan </span
                            >{{ number(hour.rainProbability, '%') }}
                        </p>
                    </li>
                </ul>
                <p v-else class="mt-2 text-xs text-muted-foreground">
                    Prakiraan per jam belum tersedia.
                </p>
            </div>
        </template>
        <p
            v-if="(error || weather?.status === 'stale') && forecast"
            class="mt-4 text-xs leading-5 text-warning"
            role="status"
        >
            Pembaruan gagal. Menampilkan data terakhir; kondisi dapat berubah.
        </p>
        <p v-else-if="feedback" class="mt-4 text-xs text-success" role="status">
            {{ feedback }}
        </p>
        <div class="mt-auto pt-4 text-xs leading-5 text-muted-foreground">
            <p v-if="forecast">Diambil {{ time(forecast.fetchedAt, true) }}</p>
            <p>Diperbarui otomatis tiap 15 menit.</p>
            <p>
                Sumber:
                <a
                    href="https://open-meteo.com/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline underline-offset-2 hover:text-foreground focus-visible:rounded focus-visible:outline-2 focus-visible:outline-ring"
                    >Open-Meteo<span class="sr-only"> (buka tab baru)</span></a
                >
            </p>
        </div>
    </section>
</template>
