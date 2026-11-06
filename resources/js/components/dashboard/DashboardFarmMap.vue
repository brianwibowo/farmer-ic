<script setup lang="ts">
import { LoaderCircle, LocateFixed, MapPinned, RotateCcw } from '@lucide/vue';
import type { FeatureGroup, Map as LeafletMap, TileLayer } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../../../css/farm-map.css';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import {
    ESRI_WORLD_IMAGERY_ATTRIBUTION,
    ESRI_WORLD_IMAGERY_URL,
} from '@/lib/esriBasemap';

type FarmPoint = {
    id: string;
    name: string;
    location: string;
    area: string;
    latitude: number | null;
    longitude: number | null;
};

const props = withDefaults(
    defineProps<{
        farms: FarmPoint[];
        selectable?: boolean;
        fitOnResize?: boolean;
    }>(),
    {
        selectable: false,
        fitOnResize: false,
    },
);
const emit = defineEmits<{ select: [farmId: string] }>();
const container = ref<HTMLDivElement>();
const loading = ref(true);
const error = ref('');
const tilesUnavailable = ref(false);
const locatedFarms = computed(() =>
    props.farms.filter(
        (farm): farm is FarmPoint & { latitude: number; longitude: number } =>
            farm.latitude !== null && farm.longitude !== null,
    ),
);
let map: LeafletMap | undefined;
let markers: FeatureGroup | undefined;
let tiles: TileLayer | undefined;
let observer: ResizeObserver | undefined;
let disposed = false;
let initialization = 0;

function showAllFarms(): void {
    if (!map || !markers?.getLayers().length) return;

    const bounds = markers.getBounds();
    if (locatedFarms.value.length === 1) {
        map.setView(bounds.getCenter(), 14);
        return;
    }

    map.fitBounds(bounds, { padding: [28, 28], maxZoom: 13 });
}

function destroyMap(): void {
    observer?.disconnect();
    map?.remove();
    map = undefined;
    markers = undefined;
    tiles = undefined;
}

function popupContent(farm: FarmPoint): HTMLDivElement {
    const content = document.createElement('div');
    const name = document.createElement('strong');
    const location = document.createElement('div');
    const area = document.createElement('div');

    name.textContent = farm.name;
    location.textContent = farm.location;
    area.textContent = `${farm.area} ha`;
    content.append(name, location, area);

    return content;
}

async function initialize(): Promise<void> {
    if (disposed) return;
    const current = ++initialization;
    loading.value = true;
    error.value = '';
    tilesUnavailable.value = false;
    destroyMap();

    if (!locatedFarms.value.length) {
        loading.value = false;
        return;
    }

    try {
        const leaflet = await import('leaflet');
        if (disposed || current !== initialization) return;
        await nextTick();
        if (disposed || current !== initialization || !container.value) return;

        map = leaflet.map(container.value, {
            scrollWheelZoom: false,
            zoomControl: false,
            maxZoom: 18,
            bounceAtZoomLimits: false,
        });
        leaflet.control
            .zoom({
                position: 'bottomright',
                zoomInTitle: 'Perbesar peta kebun',
                zoomOutTitle: 'Perkecil peta kebun',
            })
            .addTo(map);
        leaflet.control.scale({ imperial: false, maxWidth: 100 }).addTo(map);
        tiles = leaflet.tileLayer(ESRI_WORLD_IMAGERY_URL, {
            maxZoom: 18,
            maxNativeZoom: 19,
            attribution: ESRI_WORLD_IMAGERY_ATTRIBUTION,
        });
        tiles.on('tileerror', () => {
            if (!disposed && current === initialization)
                tilesUnavailable.value = true;
        });
        tiles.addTo(map);
        markers = leaflet.featureGroup().addTo(map);

        for (const farm of locatedFarms.value) {
            const pointStyle = {
                color: getComputedStyle(container.value)
                    .getPropertyValue('--card')
                    .trim(),
                fillColor: getComputedStyle(container.value)
                    .getPropertyValue('--primary')
                    .trim(),
                fillOpacity: 1,
                weight: 3,
            };
            const marker = leaflet
                .circleMarker([farm.latitude, farm.longitude], {
                    ...pointStyle,
                    radius: props.selectable ? 22 : 8,
                    ...(props.selectable
                        ? { fillOpacity: 0, stroke: false }
                        : {}),
                })
                .bindTooltip(`${farm.name} · ${farm.area} ha`, {
                    direction: 'top',
                    offset: [0, -8],
                });

            if (props.selectable) {
                // Keep the dot small while giving touch/keyboard users a 44px target.
                const dot = leaflet.circleMarker(
                    [farm.latitude, farm.longitude],
                    {
                        ...pointStyle,
                        radius: 8,
                        interactive: false,
                    },
                );
                dot.once('add', () =>
                    dot.getElement()?.setAttribute('aria-hidden', 'true'),
                );
                dot.addTo(markers);
                const select = (): void => {
                    const element = marker.getElement();
                    if (
                        element instanceof SVGElement ||
                        element instanceof HTMLElement
                    ) {
                        element.focus({ preventScroll: true });
                    }
                    marker.closeTooltip();
                    emit('select', farm.id);
                };
                // Paths are created only once Leaflet receives its initial view.
                marker.once('add', () => {
                    const element = marker.getElement();
                    element?.setAttribute('role', 'button');
                    element?.setAttribute('tabindex', '0');
                    element?.setAttribute(
                        'aria-label',
                        `Lihat ringkasan ${farm.name}`,
                    );
                    element?.addEventListener('keydown', (event) => {
                        if (
                            !(event instanceof KeyboardEvent) ||
                            (event.key !== 'Enter' && event.key !== ' ')
                        )
                            return;
                        event.preventDefault();
                        event.stopPropagation();
                        select();
                    });
                });
                marker.on('click', select);
            } else {
                marker.bindPopup(popupContent(farm));
            }
            marker.addTo(markers);
        }

        showAllFarms();
        observer = new ResizeObserver(() => {
            if (disposed || current !== initialization) return;
            map?.invalidateSize({ pan: false });
            if (props.fitOnResize) showAllFarms();
        });
        observer.observe(container.value);
    } catch {
        if (disposed || current !== initialization) return;
        destroyMap();
        error.value = 'Peta kebun belum dapat dimuat. Silakan coba lagi.';
    } finally {
        if (!disposed && current === initialization) loading.value = false;
    }
}

function retryTiles(): void {
    tilesUnavailable.value = false;
    tiles?.redraw();
}

onMounted(() => void initialize());
watch(
    () => [props.farms, props.selectable],
    () => void initialize(),
);
onBeforeUnmount(() => {
    disposed = true;
    initialization++;
    destroyMap();
});
</script>

<template>
    <div
        class="farm-map relative isolate mt-6 h-72 overflow-hidden rounded-xl border border-border bg-muted/40 sm:h-80 lg:h-96"
    >
        <div
            ref="container"
            class="absolute inset-0"
            aria-label="Peta lokasi kebun"
            :aria-busy="loading"
        />
        <div
            v-if="loading"
            class="absolute inset-0 z-[1000] flex items-center justify-center gap-2 bg-card/80 text-sm text-muted-foreground"
            role="status"
        >
            <LoaderCircle
                :size="18"
                class="animate-spin"
                aria-hidden="true"
            />Memuat peta kebun…
        </div>
        <div
            v-else-if="error || locatedFarms.length === 0"
            class="absolute inset-0 z-[1000] flex flex-col items-center justify-center gap-2 bg-card p-5 text-center"
            role="status"
        >
            <MapPinned
                :size="26"
                class="text-muted-foreground"
                aria-hidden="true"
            />
            <p class="text-sm font-medium text-foreground">
                {{ error || 'Belum ada kebun dengan koordinat.' }}
            </p>
            <p v-if="!error" class="text-xs text-muted-foreground">
                Lengkapi latitude dan longitude pada data kebun untuk
                menampilkan peta.
            </p>
            <Button
                v-if="error"
                type="button"
                size="sm"
                class="rounded-xl"
                @click="void initialize()"
            >
                Coba lagi
            </Button>
        </div>
        <template v-else>
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="absolute top-3 right-3 z-[1000] rounded-xl bg-card"
                aria-label="Tampilkan semua kebun"
                title="Tampilkan semua kebun"
                @click="showAllFarms"
            >
                <LocateFixed :size="18" aria-hidden="true" />
            </Button>
            <div
                v-if="tilesUnavailable"
                class="absolute top-3 right-14 left-3 z-[1000] flex items-center justify-between gap-2 rounded-lg bg-card p-2 text-xs text-foreground shadow-sm"
                role="status"
            >
                <span>Peta dasar gagal dimuat.</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="rounded p-1 text-primary focus-visible:outline-2 focus-visible:outline-ring"
                    aria-label="Muat ulang peta dasar"
                    @click="retryTiles"
                >
                    <RotateCcw :size="16" aria-hidden="true" />
                </Button>
            </div>
        </template>
    </div>
</template>
