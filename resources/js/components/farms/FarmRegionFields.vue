<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { LoaderCircle, Search, X } from '@lucide/vue';
import FarmField from '@/components/farms/FarmField.vue';
import type { RegionOption } from '@/types/farms';

const props = defineProps<{
    initialRegion: RegionOption | null;
    legacy?: boolean;
    error?: string;
    disabled?: boolean;
}>();
defineExpose({
    valid: () => query.value.trim() === '' || selected.value !== null,
});
const districtCode = defineModel<string>('districtCode', { required: true });
const district = defineModel<string>('district', { required: true });
const city = defineModel<string>('city', { required: true });
const province = defineModel<string>('province', { required: true });
const selected = ref<RegionOption | null>(props.initialRegion);
const query = ref(props.initialRegion?.district ?? '');
const results = ref<RegionOption[]>([]);
const open = ref(false);
const loading = ref(false);
const failure = ref('');
const active = ref(-1);
let timer: ReturnType<typeof setTimeout> | undefined;
let request: AbortController | undefined;
let generation = 0;
const hint = computed(() =>
    props.legacy &&
    !selected.value &&
    [district.value, city.value, province.value].some(Boolean)
        ? `Alamat lama dipertahankan: ${[district.value, city.value, province.value].filter(Boolean).join(' · ')}. Pilih kecamatan untuk memperbaruinya.`
        : 'Cari minimal 2 huruf. Hasil menampilkan kota/kabupaten dan provinsinya.',
);

async function search(): Promise<void> {
    request?.abort();
    const current = ++generation;
    if (query.value.trim().length < 2) {
        results.value = [];
        loading.value = false;
        return;
    }
    request = new AbortController();
    loading.value = true;
    failure.value = '';
    try {
        const response = await fetch(
            `/admin/farm-regions?search=${encodeURIComponent(query.value.trim())}`,
            { signal: request.signal, headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error('Wilayah gagal dimuat');
        const data = await response.json();
        if (current === generation) results.value = data.data;
    } catch (error) {
        if (
            current === generation &&
            !(error instanceof DOMException && error.name === 'AbortError')
        )
            failure.value =
                'Daftar wilayah belum dapat dimuat. Periksa koneksi lalu coba lagi.';
    } finally {
        if (current === generation) loading.value = false;
    }
}
function typing(): void {
    clearTimeout(timer);
    request?.abort();
    generation++;
    selected.value = null;
    districtCode.value = '';
    district.value = '';
    city.value = '';
    province.value = '';
    results.value = [];
    active.value = -1;
    failure.value = '';
    loading.value = false;
    open.value = true;
    timer = setTimeout(() => void search(), 300);
}
function choose(option: RegionOption): void {
    request?.abort();
    generation++;
    clearTimeout(timer);
    loading.value = false;
    selected.value = option;
    query.value = option.district;
    districtCode.value = option.code;
    district.value = option.district;
    city.value = '';
    province.value = '';
    open.value = false;
    active.value = -1;
}
function keydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
        return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = true;
        active.value =
            event.key === 'ArrowDown'
                ? Math.min(active.value + 1, results.value.length - 1)
                : Math.max(0, active.value - 1);
    } else if (
        event.key === 'Enter' &&
        open.value &&
        active.value >= 0 &&
        results.value[active.value]
    ) {
        event.preventDefault();
        choose(results.value[active.value]);
    }
}
function focus(): void {
    open.value = true;
    if (
        query.value.trim().length >= 2 &&
        !results.value.length &&
        !loading.value
    )
        void search();
}
function clear(): void {
    query.value = '';
    typing();
}
watch(city, (value) => {
    if (!value) province.value = '';
});
watch(active, async (index) => {
    if (index < 0) return;
    await nextTick();
    document
        .getElementById(`farm-region-${index}`)
        ?.scrollIntoView({ block: 'nearest' });
});
onBeforeUnmount(() => {
    clearTimeout(timer);
    request?.abort();
    generation++;
});
</script>

<template>
    <div class="space-y-5 sm:col-span-2">
        <FarmField
            id="farm-district"
            label="Kecamatan"
            :hint="hint"
            :error="error"
            v-slot="field"
        >
            <div
                class="relative"
                @focusout="
                    (event) => {
                        if (
                            !(event.currentTarget as HTMLElement).contains(
                                event.relatedTarget as Node,
                            )
                        )
                            open = false;
                    }
                "
            >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        :id="field.id"
                        v-model="query"
                        class="admin-field pr-11! pl-9!"
                        autocomplete="off"
                        maxlength="100"
                        placeholder="Cari kecamatan atau kota/kabupaten"
                        role="combobox"
                        aria-autocomplete="list"
                        :aria-expanded="open"
                        aria-controls="farm-district-results"
                        :aria-activedescendant="
                            active >= 0 && open
                                ? `farm-region-${active}`
                                : undefined
                        "
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                        :disabled="disabled"
                        @input="typing"
                        @focus="focus"
                        @keydown="keydown"
                    />
                    <button
                        v-if="query || district"
                        type="button"
                        class="absolute top-0 right-0 flex h-full w-11 items-center justify-center rounded-xl text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        aria-label="Kosongkan wilayah"
                        :disabled="disabled"
                        @click="clear"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <ul
                    v-if="open"
                    id="farm-district-results"
                    role="listbox"
                    aria-label="Hasil pencarian kecamatan"
                    class="mt-2 max-h-72 overflow-y-auto rounded-xl border border-border bg-popover p-1 text-popover-foreground"
                >
                    <li
                        v-for="(option, index) in results"
                        :id="`farm-region-${index}`"
                        :key="option.code"
                        role="option"
                        :aria-selected="active === index"
                        @pointerdown.prevent="choose(option)"
                        @mousemove="active = index"
                        class="cursor-pointer rounded-lg px-3 py-3"
                        :class="
                            active === index
                                ? 'bg-accent text-accent-foreground'
                                : 'hover:bg-accent'
                        "
                    >
                        <p class="text-sm font-semibold">
                            {{ option.district }}
                        </p>
                        <p class="mt-1 text-xs">
                            {{ option.city }} · {{ option.province }}
                        </p>
                    </li>
                    <li
                        v-if="!results.length"
                        role="presentation"
                        class="px-3 py-3 text-sm text-muted-foreground"
                    >
                        <span
                            v-if="loading"
                            role="status"
                            class="flex items-center gap-2"
                            ><LoaderCircle class="size-4 animate-spin" />Memuat
                            wilayah…</span
                        >
                        <span v-else-if="failure" role="alert"
                            >{{ failure }}
                            <button
                                type="button"
                                class="underline"
                                @click="search"
                            >
                                Coba lagi
                            </button></span
                        >
                        <span v-else>{{
                            query.trim().length < 2
                                ? 'Ketik minimal 2 huruf untuk mencari kecamatan.'
                                : 'Tidak ada hasil. Coba nama kecamatan atau kota/kabupaten lain.'
                        }}</span>
                    </li>
                </ul>
            </div>
        </FarmField>
        <div class="grid gap-5 sm:grid-cols-2">
            <FarmField
                id="farm-city"
                label="Kota / kabupaten"
                :hint="
                    selected
                        ? 'Pilih wilayah induk kecamatan tersebut.'
                        : 'Pilih kecamatan terlebih dahulu.'
                "
                v-slot="field"
                ><select
                    :id="field.id"
                    v-model="city"
                    class="admin-field"
                    :disabled="disabled || !selected"
                    :aria-describedby="field.description"
                >
                    <option value="">
                        {{
                            selected
                                ? 'Pilih kota / kabupaten'
                                : 'Menunggu kecamatan'
                        }}
                    </option>
                    <option v-if="legacy && !selected && city" :value="city">
                        {{ city }} · alamat lama
                    </option>
                    <option v-if="selected" :value="selected.city">
                        {{ selected.city }}
                    </option>
                </select></FarmField
            >
            <FarmField
                id="farm-province"
                label="Provinsi"
                :hint="
                    city
                        ? 'Pilih provinsi dari kota/kabupaten tersebut.'
                        : 'Pilih kota / kabupaten terlebih dahulu.'
                "
                v-slot="field"
                ><select
                    :id="field.id"
                    v-model="province"
                    class="admin-field"
                    :disabled="disabled || !selected || !city"
                    :aria-describedby="field.description"
                >
                    <option value="">
                        {{
                            city
                                ? 'Pilih provinsi'
                                : 'Menunggu kota / kabupaten'
                        }}
                    </option>
                    <option
                        v-if="legacy && !selected && province"
                        :value="province"
                    >
                        {{ province }} · alamat lama
                    </option>
                    <option v-if="selected && city" :value="selected.province">
                        {{ selected.province }}
                    </option>
                </select></FarmField
            >
        </div>
    </div>
</template>
