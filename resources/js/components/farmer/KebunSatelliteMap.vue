<script setup lang="ts">
import { LoaderCircle, RotateCcw } from '@lucide/vue';
import type { Map as LeafletMap, TileLayer } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../../../css/farm-map.css';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ position: [number, number]; name: string }>();
const container = ref<HTMLDivElement>();
const loading = ref(true);
const error = ref('');
let map: LeafletMap | undefined;
let tiles: TileLayer | undefined;
let observer: ResizeObserver | undefined;
let timeout: number | undefined;
let disposed = false;

function stopLoading(): void {
    loading.value = false;
    window.clearTimeout(timeout);
}

function startLoading(): void {
    loading.value = true;
    error.value = '';
    window.clearTimeout(timeout);
    timeout = window.setTimeout(() => {
        error.value = 'Citra satelit belum dapat dimuat. Periksa koneksi internet.';
        stopLoading();
    }, 12000);
}

async function initialize(): Promise<void> {
    startLoading();
    try {
        const leaflet = await import('leaflet');
        if (disposed || !container.value) return;
        map = leaflet.map(container.value, { scrollWheelZoom: false, zoomControl: false }).setView(props.position, 16);
        leaflet.control.zoom({ position: 'bottomright', zoomInTitle: 'Perbesar citra satelit', zoomOutTitle: 'Perkecil citra satelit' }).addTo(map);
        tiles = leaflet.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener noreferrer">Esri</a> &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
        });
        tiles.on('tileload', () => {
            error.value = '';
            stopLoading();
        });
        tiles.on('tileerror', () => {
            error.value = 'Citra satelit belum dapat dimuat. Periksa koneksi internet.';
            stopLoading();
        });
        tiles.addTo(map);
        leaflet.circleMarker(props.position, {
            radius: 8,
            color: getComputedStyle(container.value).getPropertyValue('--card').trim(),
            fillColor: getComputedStyle(container.value).getPropertyValue('--primary').trim(),
            fillOpacity: 1,
            weight: 3,
            interactive: false,
        }).addTo(map);
        observer = new ResizeObserver(() => map?.invalidateSize({ pan: false }));
        observer.observe(container.value);
    } catch {
        error.value = 'Peta satelit belum dapat dibuka. Silakan coba lagi.';
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
        void initialize();
    }
}

onMounted(() => void initialize());
onBeforeUnmount(() => {
    disposed = true;
    window.clearTimeout(timeout);
    observer?.disconnect();
    map?.remove();
});
</script>

<template>
    <div class="farm-map relative isolate h-[220px] shrink-0 overflow-hidden bg-muted">
        <div ref="container" class="absolute inset-0" :aria-label="`Peta satelit ${name}`" :aria-busy="loading" />
        <div v-if="loading" class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center gap-2 bg-card/80 text-sm text-muted-foreground" role="status">
            <LoaderCircle :size="18" class="animate-spin" aria-hidden="true" />Memuat citra satelit…
        </div>
        <div v-if="error" class="absolute top-3 right-3 left-3 z-[1000] flex items-center gap-2 rounded-lg bg-card p-3 text-xs text-card-foreground shadow-sm" role="status">
            <span class="flex-1">{{ error }}</span>
            <button type="button" class="rounded p-1 text-primary focus-visible:outline-2 focus-visible:outline-ring" aria-label="Muat ulang citra satelit" @click="retry"><RotateCcw :size="18" aria-hidden="true" /></button>
        </div>
    </div>
</template>
