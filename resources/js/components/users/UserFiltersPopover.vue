<script setup lang="ts">
import { computed, watch } from 'vue';
import { SlidersHorizontal, X, LoaderCircle } from '@lucide/vue';
import {
    PopoverRoot,
    PopoverTrigger,
    PopoverPortal,
    PopoverContent,
    PopoverClose,
} from 'reka-ui';
import { Button } from '@/components/ui/button';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import UserField from '@/components/users/UserField.vue';
import type {
    UserOptions,
    UserManagementScope,
    UserFilters,
} from '@/types/user-management';

const props = defineProps<{
    options: UserOptions;
    scope: UserManagementScope;
    processing: boolean;
    count: number;
    errors: Partial<Record<keyof UserFilters, string>>;
}>();
const open = defineModel<boolean>('open', { required: true });
const role = defineModel<string>('role', { required: true });
const teamId = defineModel<number | string>('teamId', { required: true });
const farmId = defineModel<string>('farmId', { required: true });
defineEmits<{ apply: []; reset: [] }>();

const farms = computed(() =>
    props.options.farms.filter(
        (farm) =>
            !props.scope.multiTenant ||
            !teamId.value ||
            String(farm.team_id) === String(teamId.value),
    ),
);
watch(teamId, () => {
    if (farmId.value && !farms.value.some((farm) => farm.id === farmId.value))
        farmId.value = '';
});
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :variant="count ? 'secondary' : 'outline'"
                type="button"
                class="h-10 px-2! sm:px-4! pointer-coarse:min-h-11"
                :disabled="processing"
                aria-label="Filter pengguna"
                title="Filter pengguna"
            >
                <SlidersHorizontal aria-hidden="true" />Filter
                <span
                    v-if="count"
                    class="rounded-full bg-accent px-1.5 text-xs text-accent-foreground"
                    :aria-label="count + ' filter aktif'"
                    >{{ count }}</span
                >
            </Button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                side="bottom"
                align="end"
                :side-offset="10"
                :collision-padding="16"
                :side-flip="false"
                :align-flip="false"
                aria-label="Filter pengguna"
                class="admin-dashboard-theme z-50 flex max-h-[min(620px,var(--reka-popover-content-available-height))] w-[340px] max-w-[calc(100vw-32px)] flex-col overflow-hidden rounded-xl border border-border bg-card text-foreground shadow-lg outline-none"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-5 py-4"
                >
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <SlidersHorizontal class="size-4 text-primary" />Filter
                        pengguna
                    </h2>
                    <PopoverClose as-child
                        ><Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Tutup filter"
                            ><X /></Button
                    ></PopoverClose>
                </header>
                <form
                    class="flex min-h-0 flex-col"
                    @submit.prevent="$emit('apply')"
                >
                    <div class="min-h-0 space-y-4 overflow-y-auto p-5">
                        <UserField
                            id="user-filter-role"
                            label="Role"
                            :error="errors.role"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="role"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Semua role</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="option in options.roles"
                                    :key="option.value"
                                    :value="option.value"
                                    >{{ option.label }}</AppSelectOption
                                >
                            </AppSelect>
                        </UserField>
                        <UserField
                            v-if="scope.multiTenant"
                            id="user-filter-team"
                            label="Tim / tenant"
                            :error="errors.team_id"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="teamId"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Semua tim</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="team in options.teams"
                                    :key="team.id"
                                    :value="team.id"
                                    >{{ team.name }}</AppSelectOption
                                >
                            </AppSelect>
                        </UserField>
                        <UserField
                            id="user-filter-farm"
                            label="Kebun tugas"
                            :error="errors.farm_id"
                            hint="Menampilkan pengguna yang ditugaskan langsung ke kebun."
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="farmId"
                                class="admin-field"
                                :disabled="processing"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value=""
                                    >Semua kebun</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="farm in farms"
                                    :key="farm.id"
                                    :value="farm.id"
                                    >{{ farm.name
                                    }}{{
                                        scope.multiTenant && !teamId
                                            ? ' — ' +
                                              (options.teams.find(
                                                  (team) =>
                                                      team.id === farm.team_id,
                                              )?.name ?? '')
                                            : ''
                                    }}</AppSelectOption
                                >
                            </AppSelect>
                        </UserField>
                    </div>
                    <footer
                        class="grid shrink-0 grid-cols-2 gap-3 border-t border-border px-5 py-4"
                    >
                        <Button
                            variant="outline"
                            type="button"
                            :disabled="processing"
                            @click="$emit('reset')"
                            >Reset</Button
                        >
                        <Button type="submit" :disabled="processing"
                            ><LoaderCircle
                                v-if="processing"
                                class="animate-spin motion-reduce:animate-none"
                            />{{
                                processing ? 'Menerapkan…' : 'Terapkan filter'
                            }}</Button
                        >
                    </footer>
                </form>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
