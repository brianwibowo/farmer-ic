<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    MapPin,
    Search,
    SlidersHorizontal,
    UserRound,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerPagination from '@/components/owner/OwnerPagination.vue';
import OwnerReadEmpty from '@/components/owner/OwnerReadEmpty.vue';
import OwnerReadFeedback from '@/components/owner/OwnerReadFeedback.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import {
    ownerReadDate,
    ownerReadStatus,
    type OwnerActivityRow,
    type OwnerOption,
    type OwnerReadPage,
} from '@/types/owner-secondary';

type ActivityFilters = {
    search?: string;
    farm_id?: string;
    activity_type_id?: string;
    performed_by?: string;
    from_date?: string;
    to_date?: string;
};
const props = defineProps<{
    activities: OwnerReadPage<OwnerActivityRow>;
    filters: ActivityFilters;
    farms: OwnerOption[];
    activityTypes: Array<OwnerOption & { code: string }>;
    officers: OwnerOption[];
}>();
const defaults = {
    search: '',
    farm_id: '',
    activity_type_id: '',
    performed_by: '',
    from_date: '',
    to_date: '',
};
const values = () => ({
    search: props.filters.search ?? '',
    farm_id: props.filters.farm_id ?? '',
    activity_type_id: props.filters.activity_type_id ?? '',
    performed_by: props.filters.performed_by ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
});
const { filters, loading, error, feedback, submit, reset, sync } =
    useOwnerReadFilters('/aktivitas', values());
watch(
    () => props.filters,
    () => sync(values()),
);
const hasFilters = computed(() => Object.values(values()).some(Boolean));

function apply(): void {
    submit(() => {
        if (
            filters.value.from_date &&
            filters.value.to_date &&
            filters.value.from_date > filters.value.to_date
        ) {
            return 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.';
        }
        return null;
    });
}
</script>

<template>
    <Head title="Aktivitas kebun" />
    <div class="space-y-5" :aria-busy="loading">
        <OwnerPageHeader
            title="Aktivitas"
            description="Ikuti pekerjaan lapangan yang sudah dicatat pada kebun Anda."
            back-href="/lainnya"
        />

        <form
            class="space-y-4 rounded-xl border border-border bg-card p-4"
            @submit.prevent="apply"
        >
            <div class="space-y-1.5">
                <label for="owner-activity-search" class="text-xs font-medium"
                    >Cari aktivitas</label
                >
                <Input
                    id="owner-activity-search"
                    v-model="filters.search"
                    class="h-11 rounded-xl bg-background text-base"
                    placeholder="Cari catatan pekerjaan"
                    maxlength="100"
                    :disabled="loading"
                />
            </div>
            <details class="group">
                <summary
                    class="flex min-h-11 cursor-pointer list-none items-center gap-2 text-sm font-medium text-foreground focus-visible:rounded-xl focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <SlidersHorizontal
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />
                    Filter kebun, petugas, dan periode
                </summary>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="min-w-0 space-y-1.5">
                        <label
                            for="owner-activity-farm"
                            class="text-xs font-medium"
                            >Kebun</label
                        >
                        <AppSelect
                            id="owner-activity-farm"
                            v-model="filters.farm_id"
                            :disabled="loading"
                        >
                            <AppSelectOption value=""
                                >Semua kebun</AppSelectOption
                            >
                            <AppSelectOption
                                v-for="farm in farms"
                                :key="farm.id"
                                :value="farm.id"
                                >{{ farm.name }}</AppSelectOption
                            >
                        </AppSelect>
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <label
                            for="owner-activity-type"
                            class="text-xs font-medium"
                            >Jenis aktivitas</label
                        >
                        <AppSelect
                            id="owner-activity-type"
                            v-model="filters.activity_type_id"
                            :disabled="loading"
                        >
                            <AppSelectOption value=""
                                >Semua jenis</AppSelectOption
                            >
                            <AppSelectOption
                                v-for="type in activityTypes"
                                :key="type.id"
                                :value="type.id"
                                >{{ type.name }}</AppSelectOption
                            >
                        </AppSelect>
                    </div>
                    <div class="min-w-0 space-y-1.5 sm:col-span-2">
                        <label
                            for="owner-activity-officer"
                            class="text-xs font-medium"
                            >Petugas pencatat</label
                        >
                        <AppSelect
                            id="owner-activity-officer"
                            v-model="filters.performed_by"
                            :disabled="loading"
                        >
                            <AppSelectOption value=""
                                >Semua petugas</AppSelectOption
                            >
                            <AppSelectOption
                                v-for="officer in officers"
                                :key="officer.id"
                                :value="officer.id"
                                >{{ officer.name }}</AppSelectOption
                            >
                        </AppSelect>
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <label
                            for="owner-activity-from"
                            class="text-xs font-medium"
                            >Dari tanggal</label
                        >
                        <Input
                            id="owner-activity-from"
                            v-model="filters.from_date"
                            type="date"
                            class="h-11 rounded-xl bg-background text-base"
                            :max="filters.to_date || undefined"
                            :disabled="loading"
                        />
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <label
                            for="owner-activity-to"
                            class="text-xs font-medium"
                            >Sampai tanggal</label
                        >
                        <Input
                            id="owner-activity-to"
                            v-model="filters.to_date"
                            type="date"
                            class="h-11 rounded-xl bg-background text-base"
                            :min="filters.from_date || undefined"
                            :disabled="loading"
                        />
                    </div>
                </div>
            </details>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    class="min-h-11 flex-1 rounded-xl"
                    :disabled="loading"
                    ><Search aria-hidden="true" />Tampilkan</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 rounded-xl"
                    :disabled="loading"
                    @click="reset(defaults)"
                    >Reset filter</Button
                >
            </div>
        </form>

        <OwnerReadFeedback
            :loading="loading"
            :error="error"
            :feedback="feedback"
        />
        <p class="text-xs text-muted-foreground" aria-live="polite">
            {{ activities.total }} aktivitas{{
                hasFilters ? ' sesuai filter' : ' tercatat'
            }}
        </p>

        <div v-if="activities.data.length" class="space-y-3">
            <Link
                v-for="activity in activities.data"
                :key="activity.id"
                :href="`/aktivitas/${activity.id}`"
                class="block rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-secondary dark:text-primary"
                        >{{ activity.type?.name ?? 'Aktivitas lapangan' }}</span
                    >
                    <ArrowRight
                        class="mt-1 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <h2
                    class="mt-3 text-sm leading-6 font-semibold text-foreground"
                >
                    {{
                        activity.description ||
                        activity.type?.name ||
                        'Catatan aktivitas'
                    }}
                </h2>
                <div
                    class="mt-3 space-y-1.5 text-xs leading-5 text-muted-foreground"
                >
                    <p class="flex items-start gap-2">
                        <MapPin
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />{{ activity.farm?.name ?? 'Kebun tidak tersedia'
                        }}<span v-if="activity.cycle">
                            · {{ activity.cycle.label }}</span
                        >
                    </p>
                    <p class="flex items-center gap-2">
                        <UserRound
                            class="size-3.5 shrink-0"
                            aria-hidden="true"
                        />{{
                            activity.performer?.name ?? 'Petugas tidak tersedia'
                        }}
                    </p>
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <CalendarDays
                            class="size-3.5 shrink-0"
                            aria-hidden="true"
                        />{{ ownerReadDate(activity.activity_date)
                        }}<span aria-hidden="true">·</span
                        >{{ ownerReadStatus(activity.status) }}
                    </p>
                </div>
            </Link>
        </div>
        <OwnerReadEmpty
            v-else
            :title="
                hasFilters ? 'Aktivitas tidak ditemukan' : 'Belum ada aktivitas'
            "
            :description="
                hasFilters
                    ? 'Coba kurangi filter atau pilih periode lain.'
                    : 'Catatan pekerjaan akan muncul setelah aktivitas tersimpan pada kebun dalam cakupan Anda.'
            "
        />
        <OwnerPagination :paginator="activities" />
    </div>
</template>
