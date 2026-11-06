<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';

const page = usePage();
const teams = computed(() =>
    (page.props.teams ?? []).filter((team) => !team.isPersonal),
);
const selected = computed(
    () =>
        teams.value.find((team) => team.id === page.props.currentTeam?.id)
            ?.slug ?? '',
);
const switching = ref(false);
const error = ref('');
function switchTeam(value: string | number | boolean | null | undefined): void {
    if (typeof value !== 'string' || !value || value === selected.value) return;
    switching.value = true;
    error.value = '';
    router.post(
        `/owner/tenant/${encodeURIComponent(value)}`,
        {},
        {
            onError: () => {
                error.value =
                    'Tenant belum berhasil diganti. Silakan coba lagi.';
            },
            onHttpException: () => {
                error.value = 'Tenant tidak dapat dibuka. Muat ulang halaman.';
                return false;
            },
            onNetworkError: () => {
                error.value = 'Koneksi terputus. Silakan coba lagi.';
                return false;
            },
            onFinish: () => {
                switching.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="space-y-2 rounded-xl border border-border bg-card p-4"
        :aria-busy="switching"
    >
        <div class="flex items-end gap-2">
            <div class="min-w-0 flex-1">
                <label
                    for="owner-tenant"
                    class="mb-1 block text-xs font-medium text-muted-foreground"
                    >Tenant aktif</label
                >
                <AppSelect
                    id="owner-tenant"
                    :model-value="selected"
                    :disabled="switching || teams.length === 0"
                    :loading="switching"
                    placeholder="Pilih tenant"
                    @change="switchTeam"
                >
                    <AppSelectOption v-if="!selected" value="" disabled>{{
                        teams.length
                            ? 'Pilih tenant operasional'
                            : 'Belum ada tenant operasional'
                    }}</AppSelectOption>
                    <AppSelectOption
                        v-for="team in teams"
                        :key="team.id"
                        :value="team.slug"
                        >{{ team.name }}</AppSelectOption
                    >
                </AppSelect>
            </div>
        </div>
        <p v-if="error" role="alert" class="text-xs text-error">{{ error }}</p>
        <p v-if="switching" role="status" class="text-xs text-muted-foreground">
            Mengganti tenant…
        </p>
    </div>
</template>
