<script setup lang="ts">
import { LocateFixed, LoaderCircle, MapPin, RotateCcw } from '@lucide/vue';
import type { FeatureGroup, Map as LeafletMap, Path, TileLayer } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { listMappedKebun, type MappedKebun } from '@/services/kebunService';

const mapElement = ref<HTMLDivElement>();
const isLoading = ref(true);
const errorMessage = ref('');
const tilesUnavailable = ref(false);
const kebunCount = ref(0);
const { resolvedAppearance } = useAppearance();
let map: LeafletMap | undefined;
let areas: FeatureGroup | undefined;
let tiles: TileLayer | undefined;
let resizeObserver: ResizeObserver | undefined;
let disposed = false;
let coloredLayers: Array<{ layer: Path; status: MappedKebun['healthStatus'] }> =
    [];

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
        map.fitBounds(areas.getBounds(), { padding: [24, 24], maxZoom: 12 });
    }
}

function destroyMap(): void {
    resizeObserver?.disconnect();
    map?.remove();
    map = undefined;
    areas = undefined;
    tiles = undefined;
    coloredLayers = [];
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
        });
        leaflet.control
            .zoom({
                position: 'bottomright',
                zoomInTitle: 'Perbesar peta',
                zoomOutTitle: 'Perkecil peta',
            })
            .addTo(map);
        tiles = leaflet.tileLayer(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
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
            const polygon = leaflet
                .polygon(item.boundary, { color, weight: 2, fillOpacity: 0.22 })
                .addTo(areas);
            const marker = leaflet
                .circleMarker(polygon.getBounds().getCenter(), {
                    radius: 7,
                    color,
                    fillColor: color,
                    fillOpacity: 1,
                    weight: 3,
                })
                .addTo(areas);

            for (const layer of [polygon, marker]) {
                coloredLayers.push({ layer, status: item.healthStatus });
                layer.bindPopup(
                    () => {
                        const content = document.createElement('div');
                        const title = document.createElement('strong');
                        title.textContent = item.name;
                        const description = document.createElement('p');
                        description.textContent = `${item.city} · ${item.seedType} · ${item.healthStatus}`;
                        const area = document.createElement('p');
                        const format = new Intl.NumberFormat('id-ID');
                        area.textContent = `${format.format(item.usedHectare)} / ${format.format(item.availableHectare)} ha terpakai`;
                        const focusButton = document.createElement('button');
                        focusButton.type = 'button';
                        focusButton.className = 'kebun-map-focus';
                        focusButton.textContent = 'Lihat area kebun';
                        focusButton.addEventListener('click', () => {
                            map?.closePopup();
                            map?.fitBounds(polygon.getBounds(), {
                                padding: [32, 32],
                                maxZoom: 14,
                            });
                        });
                        content.append(title, description, area, focusButton);
                        return content;
                    },
                    { maxWidth: 220 },
                );

                const element = layer.getElement();
                element?.setAttribute('tabindex', '0');
                element?.setAttribute('role', 'button');
                element?.setAttribute(
                    'aria-label',
                    `${item.name}, ${item.city}, ${item.healthStatus}`,
                );
                element?.addEventListener('keydown', (event) => {
                    if (
                        event instanceof KeyboardEvent &&
                        (event.key === 'Enter' || event.key === ' ')
                    ) {
                        event.preventDefault();
                        layer.openPopup(marker.getLatLng());
                    }
                });
            }
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
        for (const { layer, status } of coloredLayers) {
            const color = healthColor(status);
            layer.setStyle({ color, fillColor: color });
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
            class="kebun-map relative isolate min-h-[240px] flex-1 overflow-hidden rounded-[20px] border border-border bg-muted"
        >
            <div
                ref="mapElement"
                class="absolute inset-0"
                aria-label="Peta lokasi dan batas area kebun di Jawa Tengah"
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
    </section>
</template>

<style scoped>
.kebun-map :deep(.leaflet-container) {
    background: var(--muted);
    font-family: var(--font-sans);
}

.kebun-map :deep(.leaflet-bar),
.kebun-map :deep(.leaflet-popup-content-wrapper),
.kebun-map :deep(.leaflet-popup-tip) {
    border-color: var(--border);
    background: var(--card);
    color: var(--foreground);
}

.kebun-map :deep(.leaflet-bar a) {
    border-color: var(--border);
    background: var(--card);
    color: var(--foreground);
}

.kebun-map :deep(.leaflet-bar a:hover) {
    background: var(--muted);
}

.kebun-map :deep(.leaflet-control-attribution) {
    background: var(--card);
    color: var(--muted-foreground);
    font-size: 9px;
}

.kebun-map :deep(.leaflet-control-attribution a),
.kebun-map :deep(.leaflet-popup-close-button) {
    color: var(--secondary);
}

.kebun-map :deep(.leaflet-popup-content p) {
    margin: 6px 0;
}

.kebun-map :deep(.kebun-map-focus) {
    margin-top: 4px;
    border-radius: 6px;
    background: var(--primary);
    padding: 7px 10px;
    color: var(--primary-foreground);
    font-weight: 600;
    cursor: pointer;
}

.kebun-map :deep(.leaflet-interactive:focus-visible),
.kebun-map :deep(.kebun-map-focus:focus-visible) {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
</style>
