<script setup lang="ts">
import { LocateFixed, LoaderCircle, MapPin, RotateCcw } from '@lucide/vue';
import type {
    FeatureGroup,
    Map as LeafletMap,
    Marker,
    TileLayer,
} from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../../../css/farm-map.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { FARM_MAP_MAX_ZOOM, FARM_MARKER_HIT_SIZE } from '@/lib/farmMap';
import { listMappedKebun, type MappedKebun } from '@/services/kebunService';
import KebunMapPreview from './KebunMapPreview.vue';

const mapElement = ref<HTMLDivElement>();
const isLoading = ref(true);
const errorMessage = ref('');
const tilesUnavailable = ref(false);
const kebunCount = ref(0);
const selectedKebun = ref<MappedKebun>();
const previewOpen = ref(false);
let lastMarker: HTMLElement | undefined;
const { resolvedAppearance } = useAppearance();
let map: LeafletMap | undefined;
let areas: FeatureGroup | undefined;
let tiles: TileLayer | undefined;
let resizeObserver: ResizeObserver | undefined;
let disposed = false;
let coloredMarkers: Array<{
    marker: Marker;
    status: MappedKebun['healthStatus'];
}> = [];

function healthColor(status: MappedKebun['healthStatus']): string {
    const token =
        status === 'Kritis'
            ? '--error'
            : status === 'Perlu Perhatian'
              ? '--warning'
              : '--success';
    return getComputedStyle(document.documentElement)
        .getPropertyValue(token)
        .trim();
}

function showAllKebun(): void {
    if (map && areas?.getLayers().length) {
        map.fitBounds(areas.getBounds(), {
            paddingTopLeft: [36, 36],
            paddingBottomRight: [60, 72],
            maxZoom: 12,
        });
    }
}

function destroyMap(): void {
    resizeObserver?.disconnect();
    map?.remove();
    map = undefined;
    areas = undefined;
    tiles = undefined;
    coloredMarkers = [];
}

async function initializeMap(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';
    tilesUnavailable.value = false;
    destroyMap();

    try {
        // Import after mount so Leaflet's browser APIs are safe for SSR builds.
        const [leaflet, kebun] = await Promise.all([
            import('leaflet'),
            listMappedKebun(),
        ]);
        if (disposed || !mapElement.value) return;
        kebunCount.value = kebun.length;
        if (!kebun.length) return;

        map = leaflet.map(mapElement.value, {
            scrollWheelZoom: false,
            zoomControl: false,
            maxZoom: FARM_MAP_MAX_ZOOM,
            bounceAtZoomLimits: false,
        });
        leaflet.control
            .zoom({
                position: 'bottomright',
                zoomInTitle: 'Perbesar peta',
                zoomOutTitle: 'Perkecil peta',
            })
            .addTo(map);
        leaflet.control.scale({ imperial: false, maxWidth: 100 }).addTo(map);
        tiles = leaflet.tileLayer(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: FARM_MAP_MAX_ZOOM,
                maxNativeZoom: FARM_MAP_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
            },
        );
        tiles.on('tileerror', () => {
            tilesUnavailable.value = true;
        });
        tiles.addTo(map);
        areas = leaflet.featureGroup().addTo(map);

        for (const item of kebun) {
            const color = healthColor(item.healthStatus);
            const marker = leaflet.marker(item.position, {
                icon: leaflet.divIcon({
                    className: 'farm-map-marker',
                    html: '<span class="farm-map-marker__dot" aria-hidden="true"></span>',
                    iconSize: [FARM_MARKER_HIT_SIZE, FARM_MARKER_HIT_SIZE],
                    iconAnchor: [
                        FARM_MARKER_HIT_SIZE / 2,
                        FARM_MARKER_HIT_SIZE / 2,
                    ],
                }),
                title: item.name,
                keyboard: true,
                riseOnHover: true,
                autoPanOnFocus: true,
            });

            coloredMarkers.push({ marker, status: item.healthStatus });
            const openPreview = () => {
                lastMarker = marker.getElement();
                selectedKebun.value = item;
                previewOpen.value = true;
            };
            marker.on('click', openPreview);
            // Icons only exist after the map has its first view. Configure them
            // on add so the first render also has its color and keyboard label.
            marker.on('add', () => {
                const element = marker.getElement();
                element?.style.setProperty('--farm-marker-color', color);
                element?.setAttribute('role', 'button');
                element?.setAttribute('aria-haspopup', 'dialog');
                element?.setAttribute(
                    'aria-label',
                    `${item.name}, ${item.city}, ${item.healthStatus}`,
                );
                element?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openPreview();
                    }
                });
            });
            marker.addTo(areas);
        }

        showAllKebun();
        resizeObserver = new ResizeObserver(() =>
            map?.invalidateSize({ pan: false }),
        );
        resizeObserver.observe(mapElement.value);
    } catch {
        destroyMap();
        errorMessage.value =
            'Peta kebun belum dapat dimuat. Silakan coba lagi.';
    } finally {
        if (!disposed) isLoading.value = false;
    }
}

function retryTiles(): void {
    tilesUnavailable.value = false;
    tiles?.redraw();
}

watch(
    resolvedAppearance,
    () => {
        for (const { marker, status } of coloredMarkers) {
            marker
                .getElement()
                ?.style.setProperty('--farm-marker-color', healthColor(status));
        }
    },
    { flush: 'post' },
);

onMounted(() => void initializeMap());
onBeforeUnmount(() => {
    disposed = true;
    destroyMap();
});
</script>

<template>
    <section
        class="flex flex-1 flex-col gap-2"
        aria-labelledby="kebun-map-heading"
    >
        <div class="flex items-center justify-between gap-2">
            <h2
                id="kebun-map-heading"
                class="text-base font-black text-foreground"
            >
                Peta Kebun
            </h2>
            <span
                class="rounded-full bg-muted px-2 py-1 text-[10px] font-medium text-muted-foreground"
                >Area demo</span
            >
        </div>

        <div
            class="farm-map relative isolate min-h-[240px] flex-1 overflow-hidden rounded-[20px] border border-border bg-muted"
        >
            <div
                ref="mapElement"
                class="absolute inset-0"
                aria-label="Peta titik lokasi kebun di Jawa Tengah"
                :aria-busy="isLoading"
            />

            <div
                v-if="isLoading"
                class="absolute inset-0 z-[1000] flex items-center justify-center gap-2 bg-card text-sm text-muted-foreground"
                role="status"
            >
                <LoaderCircle
                    :size="18"
                    class="animate-spin"
                    aria-hidden="true"
                />
                Memuat peta kebun…
            </div>
            <div
                v-else-if="errorMessage || kebunCount === 0"
                class="absolute inset-0 z-[1000] flex flex-col items-center justify-center gap-3 bg-card p-5 text-center"
                role="status"
            >
                <MapPin :size="28" class="text-primary" aria-hidden="true" />
                <p class="text-sm text-muted-foreground">
                    {{ errorMessage || 'Belum ada kebun dengan lokasi peta.' }}
                </p>
                <button
                    type="button"
                    class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground"
                    @click="void initializeMap()"
                >
                    Coba lagi
                </button>
            </div>
            <template v-else>
                <button
                    type="button"
                    class="absolute top-3 right-3 z-[1000] flex size-9 items-center justify-center rounded-lg border border-border bg-card text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-ring"
                    title="Tampilkan semua kebun"
                    aria-label="Tampilkan semua kebun"
                    @click="showAllKebun"
                >
                    <LocateFixed :size="18" aria-hidden="true" />
                </button>
                <div
                    v-if="tilesUnavailable"
                    class="absolute top-14 right-3 left-3 z-[1000] flex items-center justify-between gap-2 rounded-lg bg-card p-2 text-xs text-foreground shadow-sm"
                    role="status"
                >
                    <span
                        >Peta dasar gagal dimuat. Periksa koneksi
                        internet.</span
                    >
                    <button
                        type="button"
                        class="shrink-0 rounded p-1 text-primary focus-visible:outline-2 focus-visible:outline-ring"
                        aria-label="Muat ulang peta dasar"
                        @click="retryTiles"
                    >
                        <RotateCcw :size="16" aria-hidden="true" />
                    </button>
                </div>
            </template>
        </div>
        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted-foreground"
            aria-label="Keterangan status kebun"
        >
            <span class="flex items-center gap-1"
                ><span class="size-2 rounded-full bg-success" />Sehat</span
            >
            <span class="flex items-center gap-1"
                ><span class="size-2 rounded-full bg-warning" />Perlu
                perhatian</span
            >
            <span class="flex items-center gap-1"
                ><span class="size-2 rounded-full bg-error" />Kritis</span
            >
        </div>
        <KebunMapPreview
            v-if="selectedKebun"
            v-model:open="previewOpen"
            :kebun="selectedKebun"
            @closed="lastMarker?.focus()"
        />
    </section>
</template>
