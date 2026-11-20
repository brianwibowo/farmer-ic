<script setup lang="ts">
import {
    Check,
    LoaderCircle,
    RotateCcw,
    Trash2,
    Undo2,
} from '@lucide/vue';
import type {
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
    hasSelfIntersection,
    latLngsToGeoJson,
    polygonAreaHectaresString,
    ringToLatLngs,
    type GeoJsonPolygon,
    type LatLngPoint,
} from '@/lib/geoPolygon';
import type { BlockFarm } from '@/types/blocks';
import type { BoundaryMapBlock } from '@/components/farms/FarmBlockBoundaryMap.vue';

const props = withDefaults(
    defineProps<{
        modelValue: GeoJsonPolygon | null;
        farm: BlockFarm;
        existingBlocks?: BoundaryMapBlock[];
        currentBlockId?: string | null;
        blockName?: string;
        disabled?: boolean;
        error?: string;
    }>(),
    {
        existingBlocks: () => [],
        currentBlockId: null,
        blockName: '',
        disabled: false,
        error: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: GeoJsonPolygon | null];
    'update:invalid': [message: string | null];
    'use-area': [areaHa: string];
}>();

const mapElement = ref<HTMLDivElement>();
const loading = ref(true);
const mapError = ref('');
const points = ref<LatLngPoint[]>(ringToLatLngs(props.modelValue));
let lastClickAt = 0;

const farmCoordinates = computed<LatLngPoint | null>(() => {
    if (
        props.farm.latitude === undefined ||
        props.farm.longitude === undefined ||
        props.farm.latitude === null ||
        props.farm.longitude === null
    ) {
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

const otherMappedBlocks = computed(() =>
    props.existingBlocks.filter(
        (block) =>
            block.id !== props.currentBlockId &&
            ringToLatLngs(block.boundary_geojson).length >= 3,
    ),
);

const selfIntersecting = computed(() => hasSelfIntersection(points.value));

const localError = computed<string | null>(() => {
    if (points.value.length > 0 && points.value.length < 3) {
        return 'Tandai minimal 3 titik sudut di peta, atau klik Hapus batas jika ingin mengosongkan.';
    }
    if (selfIntersecting.value) {
        return 'Garis batas saling bersilangan. Tandai titik sudut secara berurutan mengelilingi lahan.';
    }
    return null;
});

const mappedAreaHa = computed<string | null>(() => {
    if (points.value.length < 3 || selfIntersecting.value) return null;
    const ha = polygonAreaHectaresString(points.value);
    return Number(ha) > 0 ? ha : null;
});

let leaflet: typeof import('leaflet') | undefined;
let map: LeafletMap | undefined;
let tiles: TileLayer | undefined;
let farmMarker: Marker | undefined;
let contextLayer: LayerGroup | undefined;
let draftLayer: LayerGroup | undefined;
let observer: ResizeObserver | undefined;
let timeout: ReturnType<typeof setTimeout> | undefined;
let disposed = false;

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

function emitModel(): void {
    emit('update:invalid', localError.value);
    if (points.value.length >= 3 && !selfIntersecting.value) {
        emit('update:modelValue', latLngsToGeoJson(points.value));
    } else if (points.value.length === 0) {
        emit('update:modelValue', null);
    }
}

function syncFarmMarker(): void {
    if (!leaflet || !map) return;

    const allPolygons = [
        ...otherMappedBlocks.value.map((b) => b.boundary_geojson),
        ...(points.value.length >= 3 && !selfIntersecting.value
            ? [latLngsToGeoJson(points.value)]
            : []),
    ];
    const pinPosition =
        centroidOfPolygons(allPolygons) ?? farmCoordinates.value;

    if (!pinPosition) {
        farmMarker?.remove();
        farmMarker = undefined;
        return;
    }

    const icon = leaflet.divIcon({
        className: 'farm-location-pin',
        html: '<svg viewBox="0 0 32 40" width="26" height="34" aria-hidden="true"><path d="M16 38S2 24 2 16a14 14 0 0 1 28 0c0 8-14 22-14 22Z" fill="currentColor" stroke="var(--card)" stroke-width="2"/><circle cx="16" cy="16" r="5" fill="var(--card)"/></svg>',
        iconSize: [26, 34],
        iconAnchor: [13, 32],
    });

    if (!farmMarker) {
        farmMarker = leaflet
            .marker(pinPosition, {
                title: `Titik lokasi ${props.farm.name}`,
                interactive: false,
                icon,
            })
            .addTo(map);
    } else {
        farmMarker.setLatLng(pinPosition);
    }
}

function renderContextBlocks(): void {
    if (!leaflet || !map || !contextLayer) return;
    contextLayer.clearLayers();

    for (const block of otherMappedBlocks.value) {
        const ring = ringToLatLngs(block.boundary_geojson);
        if (ring.length < 3) continue;

        const polygon = leaflet.polygon(ring, {
            color: '#94a3b8',
            weight: 2,
            opacity: 0.75,
            fillColor: '#cbd5e1',
            fillOpacity: 0.16,
            dashArray: '5 4',
            interactive: false,
        });
        polygon.bindTooltip(block.name, {
            permanent: true,
            direction: 'center',
            className: 'farm-block-label',
        });
        polygon.addTo(contextLayer);
    }
}

function renderDraft(): void {
    if (!leaflet || !map || !draftLayer) return;
    draftLayer.clearLayers();

    const current = points.value;
    const strokeColor = selfIntersecting.value ? '#ef4444' : '#facc15';
    const fillColor = selfIntersecting.value ? '#fca5a5' : '#fef08a';

    let previewShape:
        | ReturnType<typeof leaflet.polygon>
        | ReturnType<typeof leaflet.polyline>
        | null = null;

    if (current.length >= 3) {
        previewShape = leaflet
            .polygon(current, {
                color: strokeColor,
                weight: 3,
                fillColor,
                fillOpacity: 0.34,
                interactive: false,
            })
            .addTo(draftLayer);
    } else if (current.length === 2) {
        previewShape = leaflet
            .polyline(current, {
                color: strokeColor,
                weight: 3,
                dashArray: '6 4',
                interactive: false,
            })
            .addTo(draftLayer);
    }

    current.forEach((point, index) => {
        if (!leaflet || !draftLayer) return;
        const vertexMarker = leaflet
            .marker(point, {
                draggable: !props.disabled,
                title: `Titik sudut ${index + 1}`,
                alt: `Titik sudut ${index + 1}`,
                icon: leaflet.divIcon({
                    className: 'farm-block-vertex',
                    html: '<span class="farm-block-vertex__dot"></span>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12],
                }),
            })
            .addTo(draftLayer);

        vertexMarker.on('drag', () => {
            const moved = vertexMarker.getLatLng().wrap();
            const next = [...points.value];
            next[index] = [
                Number(moved.lat.toFixed(7)),
                Number(moved.lng.toFixed(7)),
            ];
            points.value = next;
            if (previewShape && 'setLatLngs' in previewShape) {
                previewShape.setLatLngs(next);
            }
        });

        vertexMarker.on('dragend', () => {
            renderDraft();
            emitModel();
        });
    });
}

function fitInitialView(): void {
    if (!leaflet || !map) return;

    if (points.value.length >= 3) {
        map.fitBounds(leaflet.latLngBounds(points.value), {
            padding: [48, 48],
            maxZoom: 19,
            animate: false,
        });
        return;
    }

    const contextPoints: LatLngPoint[] = [];
    for (const block of otherMappedBlocks.value) {
        contextPoints.push(...ringToLatLngs(block.boundary_geojson));
    }
    if (contextPoints.length >= 3) {
        map.fitBounds(leaflet.latLngBounds(contextPoints), {
            padding: [48, 48],
            maxZoom: 18,
            animate: false,
        });
        return;
    }

    if (farmCoordinates.value) {
        map.setView(farmCoordinates.value, 18, { animate: false });
        return;
    }

    map.setView([-2.5, 118], 5, { animate: false });
}

function undoPoint(): void {
    if (!points.value.length || props.disabled) return;
    points.value = points.value.slice(0, -1);
    emitModel();
}

function clearPoints(): void {
    if (!points.value.length || props.disabled) return;
    points.value = [];
    emitModel();
}

function applyMappedArea(): void {
    if (!mappedAreaHa.value || props.disabled) return;
    emit('use-area', mappedAreaHa.value);
}

async function initialize(): Promise<void> {
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
            doubleClickZoom: false,
            worldCopyJump: true,
            maxZoom: 20,
        });
        map.zoomControl.setPosition('bottomright');
        leaflet.control
            .scale({ metric: true, imperial: false, position: 'bottomleft' })
            .addTo(map);
        mapElement.value.setAttribute(
            'aria-label',
            'Tandai titik sudut batas blok di peta',
        );

        map.on('click', (event) => {
            if (props.disabled) return;
            // Guard against accidental double-clicks stacking two identical points.
            const now = Date.now();
            if (now - lastClickAt < 250) return;
            lastClickAt = now;

            const point = event.latlng.wrap();
            const nextPoint: LatLngPoint = [
                Number(point.lat.toFixed(7)),
                Number(point.lng.toFixed(7)),
            ];
            const prev = points.value[points.value.length - 1];
            if (prev && prev[0] === nextPoint[0] && prev[1] === nextPoint[1]) {
                return;
            }

            points.value = [...points.value, nextPoint];
            emitModel();
        });

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

        contextLayer = leaflet.layerGroup().addTo(map);
        draftLayer = leaflet.layerGroup().addTo(map);

        syncFarmMarker();
        renderContextBlocks();
        renderDraft();
        fitInitialView();

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
        contextLayer = undefined;
        draftLayer = undefined;
        void initialize();
    }
}

watch(
    () => points.value,
    () => {
        renderDraft();
        syncFarmMarker();
    },
    { deep: true },
);

watch(otherMappedBlocks, () => {
    renderContextBlocks();
    syncFarmMarker();
}, { deep: true });

onMounted(() => {
    emit('update:invalid', localError.value);
    void initialize();
});

onBeforeUnmount(() => {
    disposed = true;
    clearTimeout(timeout);
    observer?.disconnect();
    map?.remove();
});
</script>

<template>
    <div id="block-boundary" class="min-w-0 space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 space-y-0.5">
                <p class="text-sm font-medium text-foreground">
                    Batas blok di peta
                    <span class="font-normal text-muted-foreground"
                        >(opsional)</span
                    >
                </p>
                <p class="text-xs leading-5 text-muted-foreground">
                    Klik sudut-sudut lahan untuk
                    <strong class="font-medium text-foreground">{{
                        blockName.trim() || 'blok ini'
                    }}</strong>
                    secara berurutan (minimal 3 titik). Geser bulatan sudut jika
                    perlu dirapikan.
                </p>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-muted/30 px-3 py-2.5"
        >
            <p class="text-xs text-muted-foreground tabular-nums">
                Titik ditandai:
                <strong class="text-foreground">{{ points.length }}</strong>
                <template v-if="mappedAreaHa">
                    · Perkiraan luas gambar:
                    <strong class="text-foreground"
                        >{{ formatAreaHa(mappedAreaHa) }} ha</strong
                    >
                </template>
                <template v-else-if="points.length === 0">
                    · Belum ditandai
                </template>
            </p>
            <div class="flex flex-wrap items-center gap-1.5">
                <Button
                    v-if="mappedAreaHa"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-primary/40 bg-primary/10 text-primary hover:border-primary hover:bg-primary hover:text-primary-foreground dark:bg-primary/15 dark:hover:bg-primary dark:hover:text-primary-foreground"
                    :disabled="disabled"
                    @click="applyMappedArea"
                >
                    <Check class="size-3.5" />Pakai luas
                    {{ formatAreaHa(mappedAreaHa) }} ha
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :class="
                        points.length > 0
                            ? 'border-warning/40 bg-warning/10 text-warning hover:border-warning hover:bg-warning hover:text-warning-foreground dark:bg-warning/15 dark:hover:bg-warning dark:hover:text-warning-foreground'
                            : ''
                    "
                    :disabled="disabled || points.length === 0"
                    @click="undoPoint"
                >
                    <Undo2 class="size-3.5" />Urungkan titik
                </Button>
                <Button
                    v-if="points.length > 0"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-error/35 bg-error/10 text-error hover:border-error hover:bg-error hover:text-error-foreground dark:bg-error/15 dark:hover:bg-error dark:hover:text-error-foreground"
                    :disabled="disabled"
                    @click="clearPoints"
                >
                    <Trash2 class="size-3.5" />Hapus batas
                </Button>
            </div>
        </div>

        <div
            class="farm-map farm-map--drawing relative isolate h-64 overflow-hidden rounded-xl border bg-muted sm:h-72"
            :class="
                error || selfIntersecting
                    ? 'border-error'
                    : 'border-border'
            "
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
            v-if="mapError"
            role="status"
            class="flex flex-wrap items-center gap-2 rounded-xl bg-muted p-3 text-xs text-muted-foreground"
        >
            <p class="flex-1">{{ mapError }}</p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="disabled || loading"
                @click="retry"
                ><RotateCcw />Coba lagi</Button
            >
        </div>

        <p
            v-if="error || (selfIntersecting && localError)"
            role="alert"
            class="text-xs font-medium text-error"
        >
            {{ error || localError }}
        </p>
        <p
            v-else-if="points.length > 0 && points.length < 3"
            class="text-xs text-muted-foreground"
        >
            Tambahkan {{ 3 - points.length }} titik lagi agar batas blok
            terbentuk.
        </p>
    </div>
</template>

<style scoped>
:deep(.farm-location-pin) {
    color: var(--primary);
    background: transparent;
}
</style>
