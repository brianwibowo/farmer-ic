<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { ChevronDown, ClipboardList, TriangleAlert } from '@lucide/vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import type { TaskContent } from '@/types/notifications';
import { date } from './format';

const props = defineProps<{ content: TaskContent }>();
const schedule = ref('all');
const selectId = useId();
const deviation = computed(() => props.content.kind === 'sop_deviation');
const groups = computed(() =>
    props.content.groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) =>
                    schedule.value === 'all' ||
                    String(item.days_before) === schedule.value,
            ),
        }))
        .filter((group) => group.items.length > 0),
);
const total = computed(() =>
    props.content.groups.reduce(
        (sum, group) =>
            sum + group.items.reduce((count, item) => count + item.count, 0),
        0,
    ),
);
const visibleTotal = computed(() =>
    groups.value.reduce(
        (sum, group) =>
            sum + group.items.reduce((count, item) => count + item.count, 0),
        0,
    ),
);
function milestone(days: number): string {
    return deviation.value ? 'Melewati toleransi' : 'H-' + days;
}
</script>

<template>
    <div class="space-y-4" :data-notification-content="content.kind">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold">
                    {{ visibleTotal }} tugas{{
                        visibleTotal !== total ? ' dari ' + total : ''
                    }}
                </p>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    Jadwal dan status pada saat notifikasi dikirim.
                </p>
            </div>
            <div
                v-if="!deviation"
                class="w-full min-w-0 @md:w-auto @md:min-w-40"
            >
                <label :for="selectId" class="sr-only"
                    >Filter jadwal tugas dalam notifikasi</label
                >
                <AppSelect
                    :id="selectId"
                    v-model="schedule"
                    class="w-full rounded-lg border border-border bg-background text-sm"
                >
                    <AppSelectOption value="all">Semua jadwal</AppSelectOption>
                    <AppSelectOption value="0">H-0</AppSelectOption>
                    <AppSelectOption value="1">H-1</AppSelectOption>
                    <AppSelectOption value="3">H-3</AppSelectOption>
                </AppSelect>
            </div>
        </div>
        <p
            v-if="groups.length === 0"
            class="rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground"
        >
            Tidak ada tugas pada jadwal ini. Pilih jadwal lain untuk melihat
            tugas.
        </p>
        <details
            v-for="group in groups"
            :key="group.key"
            :open="groups.length === 1"
            class="group/tasks rounded-xl border border-border"
        >
            <summary
                class="flex min-h-12 cursor-pointer list-none items-center gap-3 rounded-xl p-3 focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
            >
                <component
                    :is="deviation ? TriangleAlert : ClipboardList"
                    class="size-4 shrink-0"
                    :class="deviation ? 'text-warning' : 'text-primary'"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{
                        group.farm_name
                    }}</span>
                    <span class="mt-1 block text-xs text-muted-foreground"
                        >{{ group.block_name }} ·
                        {{
                            group.items.reduce(
                                (sum, item) => sum + item.count,
                                0,
                            )
                        }}
                        tugas</span
                    >
                </span>
                <ChevronDown
                    class="size-4 shrink-0 text-muted-foreground transition-transform group-open/tasks:rotate-180 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </summary>
            <div class="border-t border-border p-3 @md:p-4">
                <table class="hidden w-full text-left text-sm @xl:table">
                    <caption class="sr-only">
                        Tugas
                        {{
                            group.block_name
                        }},
                        {{
                            group.farm_name
                        }}
                    </caption>
                    <thead
                        class="border-b border-border text-xs text-muted-foreground"
                    >
                        <tr>
                            <th scope="col" class="py-2 pr-3 font-medium">
                                Tugas
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Jadwal
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Batas toleransi
                            </th>
                            <th
                                scope="col"
                                class="py-2 pl-2 text-right font-medium"
                            >
                                Jumlah
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(item, index) in group.items" :key="index">
                            <th
                                scope="row"
                                class="py-3 pr-3 align-top font-medium"
                            >
                                {{ item.name }}
                                <span
                                    class="mt-1 block text-xs font-medium"
                                    :class="
                                        deviation
                                            ? 'text-warning'
                                            : 'text-primary'
                                    "
                                    >{{ milestone(item.days_before) }}</span
                                >
                            </th>
                            <td class="px-2 py-3 align-top">
                                {{ date(item.scheduled_date) }}
                            </td>
                            <td class="px-2 py-3 align-top">
                                {{ date(item.due_date) }}
                            </td>
                            <td
                                class="py-3 pl-2 text-right align-top tabular-nums"
                            >
                                {{ item.count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <ul class="divide-y divide-border @xl:hidden">
                    <li
                        v-for="(item, index) in group.items"
                        :key="index"
                        class="space-y-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <h4 class="min-w-0 flex-1 text-sm font-medium">
                                {{ item.name }}
                            </h4>
                            <span
                                class="rounded-md px-2 py-1 text-xs font-medium"
                                :class="
                                    deviation
                                        ? 'bg-warning/10 text-warning'
                                        : 'bg-primary/10 text-primary'
                                "
                                >{{ milestone(item.days_before) }}</span
                            >
                        </div>
                        <dl class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <dt class="text-muted-foreground">Jadwal</dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{ date(item.scheduled_date) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Batas toleransi
                                </dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{ date(item.due_date) }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-xs text-muted-foreground">
                            Jumlah tugas:
                            <span
                                class="font-medium text-foreground tabular-nums"
                                >{{ item.count }}</span
                            >
                        </p>
                    </li>
                </ul>
            </div>
        </details>
    </div>
</template>
