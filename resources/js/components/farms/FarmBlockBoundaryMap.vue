<script setup lang="ts">
import {
    Crosshair,
    ExternalLink,
    LoaderCircle,
    MapPin,
    RotateCcw,
} from '@lucide/vue';
import type {
    Circle as LeafletCircle,
    LayerGroup,
    Map as LeafletMap,
    Marker,
    TileLayer,
} from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import '../../../css/farm-map.css';
import { Button } from '@/components/ui/button';
import {
    ESRI_REFERENCE_PLACES_URL,
    ESRI_REFERENCE_TRANSPORTATION_URL,
    ESRI_WORLD_IMAGERY_ATTRIBUTION,
    ESRI_WORLD_IMAGERY_URL,
} from '@/lib/esriBasemap';
import {
    centroidOfPolygons,
    formatAreaHa,
    ringToLatLngs,
    type GeoJsonPolygon,
    type LatLngPoint,
} from '@/lib/geoPolygon';

export type BoundaryMapBlock = {
    id: string;
    name: string;
    area_ha: string;
    is_active: boolean;
    boundary_geojson?: GeoJsonPolygon | null;
    mapped_area_ha?: string | null;
    batches_count?: number;
    batches?: { id: string; status: string }[];
};

const props = withDefaults(
    defineProps<{
        farm: {
            id: string;
            name: string;
            latitude: string | number | null;
            longitude: string | number | null;
            address?: string | null;
        };
        blocks: BoundaryMapBlock[];
        selectedBlockId?: string | null;
        showGpsButton?: boolean;
        prominent?: boolean;
        initialZoom?: number;
        showHint?: boolean;
    }>(),
    {
        selectedBlockId: null,
        showGpsButton: false,
        prominent: false,
        initialZoom: 17,
        showHint: true,
    },
);

const mapElement = ref<HTMLDivElement>();
const loading = ref(true);
const ready = ref(false);
const mapError = ref('');
const gpsLoading = ref(false);
const gpsError = ref('');
const activeBlockId = ref<string | null>(props.selectedBlockId ?? null);

const farmCoordinates = computed<LatLngPoint | null>(() => {
    if (props.farm.latitude === null || props.farm.longitude === null) {
        return null;
    }
    const lat = Number(props.farm.latitude);
    const lng = Number(props.farm.longitude);
    if (
        !Number.isFinite(lat) ||
        !Number.isFinite(lng) ||
        Math.abs(lat) > 90 ||
        Math.abs(lng) > 180
    ) {
        return null;
    }
    return [lat, lng];
});

const mapsUrl = computed(() =>
    farmCoordinates.value
        ? `https://www.google.com/maps/search/?api=1&query=${farmCoordinates.value[0]},${farmCoordinates.value[1]}`
        : null,
);

const mappedBlocks = computed(() =>
    props.blocks.filter(
        (block) => ringToLatLngs(block.boundary_geojson).length >= 3,
    ),
);

const shouldRenderMap = computed(
    () => Boolean(farmCoordinates.value) || mappedBlocks.value.length > 0,
);

const activeBlock = computed(
    () =>
        mappedBlocks.value.find((block) => block.id === activeBlockId.value) ??
        mappedBlocks.value[0] ??
        null,
);

let leaflet: typeof import('leaflet') | undefined;
let map: LeafletMap | undefined;
let tiles: TileLayer | undefined;
let farmMarker: Marker | undefined;
let savedPolygonsLayer: LayerGroup | undefined;
let gpsLayer: LayerGroup | undefined;
let observer: ResizeObserver | undefined;
let timeout: ReturnType<typeof setTimeout> | undefined;
let disposed = false;

function blockSubblockCount(block: BoundaryMapBlock): number {
    if (typeof block.batches_count === 'number') return block.batches_count;
    return block.batches?.length ?? 0;
}

function stopLoading(): void {
    loading.value = false;
    clearTimeout(timeout);
}

function startLoading(): void {
    loading.value = true;
    mapError.value = '';
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        mapError.value =
            'Peta belum dapat dimuat. Periksa koneksi internet lalu coba lagi.';
        stopLoading();
    }, 12000);
}

function syncFarmMarker(): void {
    if (!leaflet || !map) return;

    const pinPosition =
        centroidOfPolygons(
            mappedBlocks.value.map((b) => b.boundary_geojson),
        ) ?? farmCoordinates.value;

    if (!pinPosition) {
        farmMarker?.remove();
        farmMarker = undefined;
        return;
    }

    const icon = leaflet.divIcon({
        className: 'farm-location-pin',
        html: '<svg viewBox="0 0 32 40" width="28" height="36" aria-hidden="true"><path d="M16 38S2 24 2 16a14 14 0 0 1 28 0c0 8-14 22-14 22Z" fill="currentColor" stroke="var(--card)" stroke-width="2"/><circle cx="16" cy="16" r="5" fill="var(--card)"/></svg>',
        iconSize: [28, 36],
        iconAnchor: [14, 34],
    });

    if (!farmMarker) {
        farmMarker = leaflet
            .marker(pinPosition, {
                title: `Titik lokasi ${props.farm.name}`,
                alt: `Titik lokasi ${props.farm.name}`,
                interactive: false,
                icon,
            })
            .addTo(map);
    } else {
        farmMarker.setLatLng(pinPosition);
    }
}

function renderSavedPolygons(): void {
    if (!leaflet || !map || !savedPolygonsLayer) return;
    savedPolygonsLayer.clearLayers();

    for (const block of mappedBlocks.value) {
        const points = ringToLatLngs(block.boundary_geojson);
        if (points.length < 3) continue;

        const isSelected = block.id === activeBlock.value?.id;
        const polygon = leaflet.polygon(points, {
            color: block.is_active ? '#facc15' : '#cbd5e1',
            weight: isSelected ? 3.5 : 2.5,
            opacity: 0.95,
            fillColor: block.is_active ? '#fef08a' : '#cbd5e1',
            fillOpacity: isSelected ? 0.34 : block.is_active ? 0.22 : 0.14,
            dashArray: block.is_active ? undefined : '6 4',
        });

        const areaLabel = formatAreaHa(block.mapped_area_ha ?? block.area_ha);
        polygon.bindTooltip(`${block.name} · ${areaLabel} ha`, {
            permanent: true,
            direction: 'center',
            className: isSelected
                ? 'farm-block-label farm-block-label--selected'
                : 'farm-block-label',
        });

        polygon.on('click', () => {
            activeBlockId.value = block.id;
        });

        polygon.addTo(savedPolygonsLayer);
    }
}

function fitToInitialView(): void {
    if (!leaflet || !map) return;

    const allPoints: LatLngPoint[] = [];
    for (const block of mappedBlocks.value) {
        allPoints.push(...ringToLatLngs(block.boundary_geojson));
    }

    if (allPoints.length >= 3) {
        map.fitBounds(leaflet.latLngBounds(allPoints), {
            padding: [36, 36],
            maxZoom: 19,
            animate: false,
        });
        return;
    }

    if (farmCoordinates.value) {
        map.setView(farmCoordinates.value, props.initialZoom, {
            animate: false,
        });
        return;
    }

    map.setView([-2.5, 118], 5, { animate: false });
}

function focusBlock(block: BoundaryMapBlock): void {
    activeBlockId.value = block.id;
    if (!leaflet || !map) return;
    const points = ringToLatLngs(block.boundary_geojson);
    if (points.length >= 3) {
        map.fitBounds(leaflet.latLngBounds(points), {
            padding: [48, 48],
            maxZoom: 19,
            animate: true,
        });
    } else if (farmCoordinates.value) {
        map.setView(farmCoordinates.value, Math.max(props.initialZoom, 18), {
            animate: true,
        });
    }
}

function locateUser(): void {
    gpsError.value = '';
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
        gpsError.value = 'Perangkat ini tidak mendukung deteksi lokasi GPS.';
        return;
    }

    gpsLoading.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            gpsLoading.value = false;
            if (!leaflet || !map || !gpsLayer) return;

            const point: LatLngPoint = [
                position.coords.latitude,
                position.coords.longitude,
            ];
            gpsLayer.clearLayers();

            const accuracy = Math.min(
                Math.max(position.coords.accuracy || 15, 5),
                120,
            );
            const circle: LeafletCircle = leaflet.circle(point, {
                radius: accuracy,
                color: '#2563eb',
                weight: 1,
                fillColor: '#3b82f6',
                fillOpacity: 0.14,
                interactive: false,
            });
            circle.addTo(gpsLayer);

            leaflet
                .marker(point, {
                    title: 'Lokasi Anda saat ini',
                    interactive: false,
                    icon: leaflet.divIcon({
                        className: 'farm-block-gps-marker',
                        html: '<span class="farm-block-gps-marker__dot"></span>',
                        iconSize: [22, 22],
                        iconAnchor: [11, 11],
                    }),
                })
                .addTo(gpsLayer);

            map.setView(point, Math.max(map.getZoom(), 18), { animate: true });
        },
        () => {
            gpsLoading.value = false;
            gpsError.value =
                'Lokasi Anda belum dapat dibaca. Pastikan izin GPS browser aktif.';
        },
        { enableHighAccuracy: true, timeout: 10000 },
    );
}

async function initialize(): Promise<void> {
    if (!shouldRenderMap.value) {
        stopLoading();
        return;
    }

    startLoading();
    try {
        leaflet = await import('leaflet');
        if (disposed) return;
        if (!mapElement.value) {
            stopLoading();
            return;
        }

        map = leaflet.map(mapElement.value, {
            scrollWheelZoom: false,
            worldCopyJump: true,
            maxZoom: 20,
        });
        map.zoomControl.setPosition('bottomright');
        leaflet.control
            .scale({ metric: true, imperial: false, position: 'bottomleft' })
            .addTo(map);
        mapElement.value.setAttribute(
            'aria-label',
            'Peta lokasi dan batas blok lahan',
        );

        tiles = leaflet.tileLayer(ESRI_WORLD_IMAGERY_URL, {
            maxNativeZoom: 18,
            maxZoom: 20,
            attribution: ESRI_WORLD_IMAGERY_ATTRIBUTION,
        });
        tiles.on('load', stopLoading);
        tiles.on('tileerror', () => {
            mapError.value =
                'Sebagian peta gagal dimuat. Periksa koneksi internet lalu coba lagi.';
            stopLoading();
        });
        tiles.addTo(map);

        leaflet
            .tileLayer(ESRI_REFERENCE_TRANSPORTATION_URL, {
                maxNativeZoom: 18,
                maxZoom: 20,
                opacity: 0.85,
            })
            .addTo(map);
        leaflet
            .tileLayer(ESRI_REFERENCE_PLACES_URL, {
                maxNativeZoom: 18,
                maxZoom: 20,
                opacity: 0.9,
            })
            .addTo(map);

        savedPolygonsLayer = leaflet.layerGroup().addTo(map);
        gpsLayer = leaflet.layerGroup().addTo(map);

        syncFarmMarker();
        renderSavedPolygons();
        fitToInitialView();

        ready.value = true;
        observer = new ResizeObserver(() =>
            map?.invalidateSize({ pan: false }),
        );
        observer.observe(mapElement.value);
    } catch {
        mapError.value = 'Peta belum dapat dibuka. Silakan coba lagi.';
        stopLoading();
    }
}

function retry(): void {
    if (tiles) {
        startLoading();
        tiles.redraw();
    } else {
        map?.remove();
        map = undefined;
        farmMarker = undefined;
        savedPolygonsLayer = undefined;
        gpsLayer = undefined;
        ready.value = false;
        void initialize();
    }
}

watch(
    () => props.selectedBlockId,
    (value) => {
        if (value) {
            activeBlockId.value = value;
            const block = mappedBlocks.value.find((item) => item.id === value);
            if (block) {
                focusBlock(block);
            }
        }
    },
);

watch(
    () => [props.blocks, activeBlockId.value],
    () => {
        renderSavedPolygons();
    },
    { deep: true },
);

watch(farmCoordinates, syncFarmMarker);
watch(mappedBlocks, syncFarmMarker, { deep: true });

onMounted(() => void initialize());
onBeforeUnmount(() => {
    disposed = true;
    clearTimeout(timeout);
    observer?.disconnect();
    map?.remove();
});
</script>

<template>
    <div class="min-w-0 space-y-3">
        <p v-if="showHint" class="text-xs leading-5 text-muted-foreground">
            {{
                mappedBlocks.length > 0
                    ? 'Klik bidang blok di peta untuk melihat ringkasan luas dan statusnya.'
                    : 'Pin menunjukkan titik lokasi lahan, bukan batas atau luas lahan.'
            }}
        </p>

        <div
            v-if="mappedBlocks.length > 1"
            class="flex flex-wrap items-center gap-2"
        >
            <button
                v-for="block in mappedBlocks"
                :key="block.id"
                type="button"
                class="inline-flex min-h-9 items-center gap-2 rounded-lg border px-3 py-1 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                :class="
                    activeBlock?.id === block.id
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-border bg-card text-foreground hover:bg-muted'
                "
                @click="focusBlock(block)"
            >
                <span>{{ block.name }}</span>
                <span class="text-muted-foreground tabular-nums">
                    {{
                        formatAreaHa(block.mapped_area_ha ?? block.area_ha)
                    }}
                    ha
                </span>
            </button>
        </div>

        <div
            v-if="shouldRenderMap"
            class="farm-map relative isolate overflow-hidden rounded-xl border border-border bg-muted"
            :class="prominent ? 'h-80 sm:h-[26rem]' : 'h-72 sm:h-80'"
        >
            <div
                ref="mapElement"
                class="absolute inset-0"
                :aria-busy="loading"
            />
            <div
                v-if="loading"
                role="status"
                class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center gap-2 bg-card/80 text-sm text-muted-foreground"
            >
                <LoaderCircle class="size-4 animate-spin" />Memuat peta…
            </div>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed border-border bg-muted/40 p-5 text-sm text-muted-foreground"
        >
            Lokasi lahan belum dipilih. Lengkapi pada halaman ubah lahan.
        </div>

        <div
            v-if="mapError"
            role="status"
            class="flex flex-wrap items-center gap-2 rounded-xl bg-muted p-3 text-xs text-muted-foreground"
        >
            <p class="flex-1">{{ mapError }}</p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="loading"
                @click="retry"
                ><RotateCcw />Coba lagi</Button
            >
        </div>

        <div
            v-if="activeBlock"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-muted/30 px-4 py-3"
        >
            <div class="min-w-0 space-y-0.5">
                <p class="text-sm font-semibold text-foreground">
                    {{ activeBlock.name }}
                    <span
                        class="ml-1.5 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="
                            activeBlock.is_active
                                ? 'bg-success/15 text-success'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ activeBlock.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    Luas tercatat:
                    <strong class="text-foreground"
                        >{{ formatAreaHa(activeBlock.area_ha) }} ha</strong
                    >
                    <template v-if="activeBlock.mapped_area_ha">
                        · Luas peta:
                        <strong class="text-foreground"
                            >{{
                                formatAreaHa(activeBlock.mapped_area_ha)
                            }}
                            ha</strong
                        >
                    </template>
                    <template v-if="blockSubblockCount(activeBlock) > 0">
                        · {{ blockSubblockCount(activeBlock) }} subblok
                    </template>
                </p>
            </div>
        </div>

        <div
            v-if="showGpsButton || mapsUrl"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <div v-if="showGpsButton" class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!ready || gpsLoading"
                    @click="locateUser"
                >
                    <LoaderCircle
                        v-if="gpsLoading"
                        class="size-4 animate-spin"
                    />
                    <Crosshair v-else class="size-4" />
                    Lokasi saya
                </Button>
                <span v-if="gpsError" role="status" class="text-xs text-error">
                    {{ gpsError }}
                </span>
            </div>
            <a
                v-if="mapsUrl"
                :href="mapsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded text-sm font-medium text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
                ><MapPin class="size-4" />Buka di Google Maps<ExternalLink
                    class="size-4"
            /></a>
        </div>
    </div>
</template>

<style scoped>
:deep(.farm-location-pin) {
    color: var(--primary);
    background: transparent;
}
</style>
