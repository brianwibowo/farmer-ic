<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import RegionCombobox from '@/components/farms/RegionCombobox.vue';
import type { RegionOption, RegionReferenceOption } from '@/types/farms';

const props = defineProps<{
    initialRegion: RegionOption | null;
    legacy?: boolean;
    error?: string;
    disabled?: boolean;
}>();
const districtCode = defineModel<string>('districtCode', { required: true });
const district = defineModel<string>('district', { required: true });
const city = defineModel<string>('city', { required: true });
const province = defineModel<string>('province', { required: true });
type Level = 'province' | 'city' | 'district';
const fields = [
    { key: 'province', id: 'farm-province', label: 'Provinsi', level: 1 },
    { key: 'city', id: 'farm-city', label: 'Kota / kabupaten', level: 2 },
    { key: 'district', id: 'farm-district', label: 'Kecamatan', level: 3 },
] as const;
const names = { province, city, district };
const initial = props.initialRegion;
const codes = reactive({
    province: initial?.province_code ?? '',
    city: initial?.city_code ?? '',
    district: initial?.code ?? '',
});
const state = reactive<
    Record<
        Level,
        { options: RegionReferenceOption[]; loading: boolean; failure: string }
    >
>({
    province: {
        options: initial
            ? [
                  {
                      code: initial.province_code,
                      name: initial.province,
                      parent_code: null,
                  },
              ]
            : [],
        loading: false,
        failure: '',
    },
    city: {
        options: initial
            ? [
                  {
                      code: initial.city_code,
                      name: initial.city,
                      parent_code: initial.province_code,
                  },
              ]
            : [],
        loading: false,
        failure: '',
    },
    district: {
        options: initial
            ? [
                  {
                      code: initial.code,
                      name: initial.district,
                      parent_code: initial.city_code,
                  },
              ]
            : [],
        loading: false,
        failure: '',
    },
});
const drafts = reactive({ province: false, city: false, district: false });
const touched = ref(false);
const resetVersion = ref(0);
const requests: Partial<Record<Level, AbortController>> = {};
const generations = { province: 0, city: 0, district: 0 };
const hasAddress = computed(() =>
    fields.some(
        (field) =>
            names[field.key].value || codes[field.key] || drafts[field.key],
    ),
);
const unchangedLegacy = computed(
    () => props.legacy && !touched.value && !initial && hasAddress.value,
);
const incomplete = computed(
    () =>
        fields.find((field) => drafts[field.key] || !codes[field.key])?.key ??
        'district',
);
defineExpose({
    valid: () =>
        unchangedLegacy.value ||
        (!Object.values(drafts).some(Boolean) &&
            (!hasAddress.value ||
                fields.every(
                    (field) => codes[field.key] && names[field.key].value,
                ))),
    firstIncompleteId: () => 'farm-' + incomplete.value,
});
function parentCode(level: Level): string {
    return level === 'city'
        ? codes.province
        : level === 'district'
          ? codes.city
          : '';
}
function disabled(level: Level): boolean {
    return !!props.disabled || (level !== 'province' && !parentCode(level));
}
function hint(level: Level): string {
    if (level === 'province')
        return 'Pilih provinsi terlebih dahulu. Wilayah boleh dikosongkan seluruhnya.';
    if (!parentCode(level))
        return level === 'city'
            ? 'Pilih provinsi terlebih dahulu.'
            : 'Pilih kota / kabupaten terlebih dahulu.';
    return level === 'city'
        ? 'Hanya kota/kabupaten dalam provinsi terpilih.'
        : 'Hanya kecamatan dalam kota/kabupaten terpilih.';
}
function invalidate(level: Level): void {
    requests[level]?.abort();
    generations[level]++;
    state[level].options = [];
    state[level].loading = false;
    state[level].failure = '';
}
async function load(level: Level): Promise<void> {
    requests[level]?.abort();
    const current = ++generations[level];
    const controller = new AbortController();
    requests[level] = controller;
    state[level].loading = true;
    state[level].failure = '';
    const params = new URLSearchParams({
        level: String(fields.find((field) => field.key === level)!.level),
    });
    if (level !== 'province') params.set('parent_code', parentCode(level));
    try {
        const response = await fetch('/admin/farm-regions?' + params, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('Region lookup failed');
        const data = await response.json();
        if (current === generations[level]) state[level].options = data.data;
    } catch {
        if (current === generations[level] && !controller.signal.aborted)
            state[level].failure =
                'Daftar wilayah belum dapat dimuat. Periksa koneksi lalu coba lagi.';
    } finally {
        if (current === generations[level]) state[level].loading = false;
    }
}
function choose(level: Level, code: string): void {
    touched.value = true;
    codes[level] = code;
    names[level].value =
        state[level].options.find((option) => option.code === code)?.name ?? '';
    const index = fields.findIndex((field) => field.key === level);
    for (const child of fields.slice(index + 1)) {
        codes[child.key] = '';
        names[child.key].value = '';
        drafts[child.key] = false;
        invalidate(child.key);
    }
    districtCode.value = codes.district;
    const next = fields[index + 1];
    if (code && next) void load(next.key);
}
function clear(): void {
    touched.value = true;
    resetVersion.value++;
    for (const field of fields) {
        codes[field.key] = '';
        names[field.key].value = '';
        drafts[field.key] = false;
        if (field.key !== 'province') invalidate(field.key);
    }
    districtCode.value = '';
}
onMounted(() => {
    void load('province');
    if (codes.province) void load('city');
    if (codes.city) void load('district');
});
onBeforeUnmount(() => {
    for (const field of fields) {
        requests[field.key]?.abort();
        generations[field.key]++;
    }
});
</script>

<template>
    <div class="space-y-4 sm:col-span-2">
        <p
            v-if="unchangedLegacy"
            role="status"
            class="rounded-xl bg-muted p-3 text-sm leading-6 text-muted-foreground"
        >
            Alamat lama dipertahankan:
            {{ [province, city, district].filter(Boolean).join(' · ') }}. Pilih
            provinsi untuk menggantinya.
        </p>
        <div class="grid items-start gap-5 sm:grid-cols-3">
            <RegionCombobox
                v-for="field in fields"
                :key="
                    field.key + ':' + parentCode(field.key) + ':' + resetVersion
                "
                :id="field.id"
                :label="field.label"
                :model-value="codes[field.key]"
                :options="state[field.key].options"
                :loading="state[field.key].loading"
                :failure="state[field.key].failure"
                :disabled="disabled(field.key)"
                :hint="hint(field.key)"
                :error="incomplete === field.key ? error : undefined"
                @update:model-value="choose(field.key, $event)"
                @draft="drafts[field.key] = $event"
                @retry="load(field.key)"
            />
        </div>
        <button
            type="button"
            class="min-h-11 rounded-lg text-sm font-medium text-muted-foreground underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="disabled('province') || !hasAddress"
            @click="clear"
        >
            Kosongkan wilayah
        </button>
    </div>
</template>
