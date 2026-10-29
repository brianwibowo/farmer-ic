<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, LandPlot } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import UserField from '@/components/users/UserField.vue';
import type { UserOptions, UserManagementScope } from '@/types/user-management';

const props = defineProps<{
    role: string;
    options: UserOptions;
    teamError?: string;
    farmError?: string;
    scope: UserManagementScope;
    canConfigure: boolean;
}>();
const teamIds = defineModel<number[]>('teamIds', { required: true });
const farmId = defineModel<string>('farmId', { required: true });
const tenantSearch = ref('');
const multipleTeams = computed(
    () =>
        props.scope.global &&
        (props.role === 'owner' || props.role === 'superadmin'),
);
const visibleTeams = computed(() =>
    props.options.teams.filter((team) =>
        team.name
            .toLocaleLowerCase('id')
            .includes(tenantSearch.value.toLocaleLowerCase('id')),
    ),
);
const availableFarms = computed(() =>
    props.options.farms.filter((farm) => teamIds.value.includes(farm.team_id)),
);
const singleTeam = computed({
    get: () => teamIds.value[0] ?? '',
    set: (value: number | string) => {
        teamIds.value = value === '' ? [] : [Number(value)];
    },
});

watch(
    () => props.role,
    () => {
        if (!multipleTeams.value && teamIds.value.length > 1)
            teamIds.value = teamIds.value.slice(0, 1);
        if (props.role !== 'petugas') farmId.value = '';
    },
);
watch(
    teamIds,
    () => {
        if (!availableFarms.value.some((farm) => farm.id === farmId.value))
            farmId.value = '';
    },
    { deep: true },
);
</script>

<template>
    <section
        v-if="scope.global || role === 'petugas' || teamError"
        class="space-y-5 rounded-xl border border-border bg-card p-5 md:p-6"
        aria-labelledby="assignment-heading"
    >
        <div v-if="scope.global || role === 'petugas'">
            <h2
                id="assignment-heading"
                class="admin-section-title flex items-center gap-2"
            >
                <Building2 v-if="scope.global" class="size-5 text-primary" />
                <LandPlot v-else class="size-5 text-primary" />
                {{
                    scope.global ? 'Assignment tenant' : 'Farm yang ditugaskan'
                }}
            </h2>
            <p
                v-if="scope.global"
                class="mt-2 text-sm leading-6 text-muted-foreground"
            >
                {{
                    multipleTeams
                        ? 'Owner boleh terhubung ke beberapa tenant. Superadmin dapat mengakses semua tenant tanpa assignment.'
                        : 'Admin dan petugas hanya dapat terhubung ke satu tenant.'
                }}
            </p>
        </div>
        <template v-if="scope.global">
            <div
                v-if="options.teams.length === 0"
                class="rounded-xl bg-muted p-4 text-sm text-muted-foreground"
            >
                Belum ada tenant.
                <Link
                    href="/settings/teams"
                    class="font-medium text-primary underline"
                    >Kelola Teams</Link
                >
                untuk menambahkan tenant.
            </div>
            <fieldset
                v-else-if="multipleTeams"
                :aria-describedby="teamError ? 'tenant-error' : 'tenant-hint'"
                class="space-y-3"
            >
                <legend class="mb-2 text-sm font-medium">
                    Pilih tenant
                    <span class="text-muted-foreground"
                        >({{ teamIds.length }} dipilih)</span
                    >
                </legend>
                <div class="space-y-2">
                    <label for="tenant-search" class="block text-xs font-medium"
                        >Cari tenant</label
                    >
                    <input
                        id="tenant-search"
                        v-model="tenantSearch"
                        type="search"
                        placeholder="Cari tenant…"
                        class="admin-field"
                    />
                </div>
                <div
                    class="max-h-60 space-y-1 overflow-y-auto rounded-xl border border-border p-2"
                >
                    <label
                        v-for="team in visibleTeams"
                        :key="team.id"
                        class="flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-muted/60"
                    >
                        <input
                            v-model="teamIds"
                            type="checkbox"
                            :value="team.id"
                            class="mt-0.5 size-4 shrink-0 accent-primary focus-visible:outline-2 focus-visible:outline-ring"
                        />
                        <span class="min-w-0 text-sm break-words">{{
                            team.name
                        }}</span>
                    </label>
                    <p
                        v-if="visibleTeams.length === 0"
                        class="p-3 text-sm text-muted-foreground"
                    >
                        Tenant tidak ditemukan.
                    </p>
                </div>
                <p
                    id="tenant-hint"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    Kosongkan jika user belum diasosiasikan ke tenant. Role
                    aplikasi tetap menentukan hak akses.
                </p>
                <p
                    v-if="teamError"
                    id="tenant-error"
                    role="alert"
                    class="text-xs text-error"
                >
                    {{ teamError }}
                </p>
            </fieldset>
            <UserField
                v-else
                id="tenant"
                label="Tenant"
                :error="teamError"
                hint="Boleh dikosongkan jika user belum ditugaskan."
            >
                <template #default="{ id, invalid, description }">
                    <select
                        :id="id"
                        v-model="singleTeam"
                        :aria-invalid="invalid"
                        :aria-describedby="description"
                        class="admin-field"
                    >
                        <option value="">Belum ditugaskan</option>
                        <option
                            v-for="team in options.teams"
                            :key="team.id"
                            :value="team.id"
                        >
                            {{ team.name }}
                        </option>
                    </select>
                </template>
            </UserField>
        </template>
        <p
            v-if="!scope.global && teamError"
            role="alert"
            class="text-xs text-error"
        >
            {{ teamError }}
        </p>
        <div
            v-if="role === 'petugas'"
            class="space-y-3"
            :class="scope.global ? 'border-t border-border pt-5' : undefined"
        >
            <h3
                v-if="scope.global"
                class="flex items-center gap-2 text-sm font-semibold"
            >
                <LandPlot class="size-4 text-primary" />Farm yang ditugaskan
            </h3>
            <UserField
                id="assigned-farm"
                label="Farm / kebun"
                :error="farmError"
                :hint="
                    !canConfigure
                        ? 'Anda tidak memiliki izin untuk mengubah assignment.'
                        : teamIds.length === 0
                          ? 'Pilih tenant terlebih dahulu.'
                          : availableFarms.length === 0
                            ? 'Tenant ini belum memiliki farm yang bisa dipilih.'
                            : 'Satu petugas maksimal satu farm. Satu farm boleh memiliki beberapa petugas.'
                "
            >
                <template #default="{ id, invalid, description }">
                    <select
                        :id="id"
                        v-model="farmId"
                        :disabled="
                            !canConfigure ||
                            teamIds.length !== 1 ||
                            availableFarms.length === 0
                        "
                        :aria-invalid="invalid"
                        :aria-describedby="description"
                        class="admin-field"
                    >
                        <option value="">Belum ditugaskan</option>
                        <option
                            v-for="farm in availableFarms"
                            :key="farm.id"
                            :value="farm.id"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                </template>
            </UserField>
        </div>
    </section>
</template>
