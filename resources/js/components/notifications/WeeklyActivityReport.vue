<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { ChevronDown, CircleAlert, MapPin } from '@lucide/vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import type { WeeklyAdminContent } from '@/types/notifications';

const props = defineProps<{ content: WeeklyAdminContent }>();
const selected = ref('all');
const selectId = useId();
const farms = computed(() =>
    props.content.farms.filter(
        (farm) => selected.value === 'all' || farm.id === selected.value,
    ),
);
const incidentCount = (farm: WeeklyAdminContent['farms'][number]) =>
    farm.blocks.reduce(
        (sum, block) =>
            sum +
            block.incidents.reduce(
                (count, incident) => count + incident.count,
                0,
            ),
        0,
    );
</script>

<template>
    <div class="space-y-3" data-notification-content="weekly_admin">
        <div
            v-if="content.farms.length > 1"
            class="min-w-0 @md:ml-auto @md:max-w-64"
        >
            <label
                :for="selectId"
                class="mb-1.5 block text-xs font-medium text-muted-foreground"
                >Lihat kebun</label
            >
            <AppSelect
                :id="selectId"
                v-model="selected"
                class="w-full rounded-lg border border-border bg-background text-sm"
            >
                <AppSelectOption value="all">Semua kebun</AppSelectOption>
                <AppSelectOption
                    v-for="farm in content.farms"
                    :key="farm.id"
                    :value="farm.id"
                    >{{ farm.name }}</AppSelectOption
                >
            </AppSelect>
        </div>
        <details
            v-for="farm in farms"
            :key="farm.id"
            :open="farms.length === 1"
            class="group/farm rounded-xl border border-border"
        >
            <summary
                class="flex min-h-12 cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-3 focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
            >
                <MapPin
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{
                        farm.name
                    }}</span>
                    <span
                        class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span>{{ farm.activity_count }} aktivitas</span>
                        <span :class="incidentCount(farm) ? 'text-warning' : ''"
                            >{{ incidentCount(farm) }} insiden</span
                        >
                    </span>
                </span>
                <ChevronDown
                    class="size-4 shrink-0 text-muted-foreground transition-transform group-open/farm:rotate-180 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </summary>
            <div class="space-y-3 border-t border-border p-3 @md:p-4">
                <p
                    v-if="farm.activity_count === 0"
                    class="rounded-lg bg-muted/50 px-3 py-3 text-sm text-muted-foreground"
                >
                    Belum ada aktivitas yang dilaporkan Petugas pada periode
                    ini.
                </p>
                <details
                    v-for="(block, index) in farm.blocks.filter(
                        (item) => item.activities.length > 0,
                    )"
                    :key="block.id ?? index"
                    :open="farm.blocks.length === 1"
                    class="group/block rounded-lg bg-muted/25"
                >
                    <summary
                        class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-3 py-3 focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                    >
                        <span class="min-w-0 flex-1 text-sm font-medium">{{
                            block.name
                        }}</span>
                        <span class="text-xs text-muted-foreground tabular-nums"
                            >{{
                                block.activities.reduce(
                                    (sum, item) => sum + item.count,
                                    0,
                                )
                            }}
                            aktivitas</span
                        >
                        <CircleAlert
                            v-if="block.incidents.length"
                            class="size-4 shrink-0 text-warning"
                            aria-label="Ada laporan insiden"
                        />
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground transition-transform group-open/block:rotate-180 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </summary>
                    <div class="space-y-3 px-3 pb-3">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">
                                Ringkasan aktivitas
                                {{
                                    block.name
                                }}
                            </caption>
                            <thead
                                class="border-b border-border text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th scope="col" class="py-2 font-medium">
                                        Aktivitas
                                    </th>
                                    <th
                                        scope="col"
                                        class="py-2 text-right font-medium"
                                    >
                                        Laporan
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="activity in block.activities"
                                    :key="activity.label"
                                >
                                    <th
                                        scope="row"
                                        class="py-2 pr-3 font-normal"
                                    >
                                        {{ activity.label }}
                                    </th>
                                    <td
                                        class="py-2 text-right font-medium tabular-nums"
                                    >
                                        {{ activity.count }} kali
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <section
                            v-if="block.incidents.length"
                            class="rounded-lg border border-warning/30 bg-warning/5 p-3"
                            :aria-label="'Insiden ' + block.name"
                        >
                            <h4
                                class="mb-2 flex items-center gap-2 text-xs font-semibold text-warning"
                            >
                                <CircleAlert
                                    class="size-4"
                                    aria-hidden="true"
                                />Laporan insiden
                            </h4>
                            <dl class="space-y-2 text-sm">
                                <div
                                    v-for="incident in block.incidents"
                                    :key="incident.label"
                                    class="flex items-start justify-between gap-3"
                                >
                                    <dt class="min-w-0 break-words">
                                        {{ incident.label }}
                                    </dt>
                                    <dd
                                        class="shrink-0 font-medium tabular-nums"
                                    >
                                        {{ incident.count }} kejadian
                                    </dd>
                                </div>
                            </dl>
                        </section>
                        <section
                            v-if="block.obstacle_count > 0"
                            class="rounded-lg border border-border bg-card p-3"
                            :aria-label="'Hambatan ' + block.name"
                        >
                            <h4
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Hambatan · {{ block.obstacle_count }} laporan
                            </h4>
                            <p class="mt-1.5 text-sm leading-6 break-words">
                                {{
                                    block.obstacle_summary ||
                                    'Lihat catatan aktivitas untuk rincian hambatan.'
                                }}
                            </p>
                        </section>
                    </div>
                </details>
            </div>
        </details>
    </div>
</template>
