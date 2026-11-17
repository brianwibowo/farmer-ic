<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ClipboardList, TriangleAlert } from '@lucide/vue';
import type { TaskContent } from '@/types/notifications';
import { date } from './format';
const props = defineProps<{ content: TaskContent }>();
const page = usePage();
const deviation = computed(() => props.content.kind === 'sop_deviation');
function href(group: TaskContent['groups'][number]): string {
    const farm = group.farm_id ?? group.key.split(':')[0];
    const block = group.block_id ?? group.key.split(':')[1];
    const query = new URLSearchParams();
    if (block && block !== 'unassigned') query.set('block_id', block);
    if (deviation.value || page.props.navigation?.admin) {
        query.set(
            'view',
            deviation.value &&
                !group.items.some((i) => i.reason?.startsWith('Jadwal ulang'))
                ? 'overdue'
                : 'all',
        );
        query.set('farm_id', farm);
        if (!deviation.value) query.set('category', 'harvest');
        return '/admin/tasks?' + query.toString();
    }
    query.set('status', 'all');
    query.set('category', 'harvest');
    return '/petugas/aktivitas?' + query.toString();
}
type ReminderRow = { id: string; name: string; date: string; days: number };
const reminders = computed(() =>
    props.content.groups.flatMap((group) => {
        const sections = new Map<
            string,
            {
                key: string;
                title: string;
                group: typeof group;
                rows: ReminderRow[];
            }
        >();
        for (const item of group.items) {
            const key = item.commodity_id ?? item.commodity_name ?? item.name;
            let section = sections.get(key);
            if (!section) {
                section = {
                    key: group.key + ':' + key,
                    title: item.commodity_name
                        ? 'Panen ' + item.commodity_name
                        : item.name,
                    group,
                    rows: [],
                };
                sections.set(key, section);
            }
            for (const subblock of item.subblocks ?? []) {
                if (
                    !section.rows.some(
                        (row) =>
                            row.id === subblock.id &&
                            row.date === item.scheduled_date,
                    )
                ) {
                    section.rows.push({
                        ...subblock,
                        date: item.scheduled_date,
                        days: item.days_before,
                    });
                }
            }
        }
        return [...sections.values()].map((section) => ({
            ...section,
            rows: section.rows.sort(
                (a, b) =>
                    a.date.localeCompare(b.date) ||
                    a.name.localeCompare(b.name),
            ),
        }));
    }),
);
function timing(days: number): string {
    return days === 0
        ? 'Hari ini'
        : days === 1
          ? 'Besok'
          : days > 1
            ? days + ' hari lagi'
            : 'Jadwal terlewat';
}
function subblockLabel(name: string): string {
    return /^subblok\b/i.test(name) ? name : 'Subblok ' + name;
}
</script>
<template>
    <div class="space-y-2" :data-notification-content="content.kind">
        <template v-for="group in content.groups" :key="group.key">
            <Link
                v-if="deviation"
                :href="href(group)"
                class="flex min-h-11 flex-wrap items-start gap-3 rounded-xl border border-border p-3 hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-ring"
            >
                <TriangleAlert
                    class="mt-1 size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <div class="min-w-40 flex-1">
                    <h3 class="text-sm font-semibold">{{ group.farm_name }}</h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ group.block_name }} ·
                        {{ group.items.reduce((n, item) => n + item.count, 0) }}
                        temuan ketidaksesuaian
                    </p>
                    <p
                        v-for="(item, index) in group.items"
                        :key="index"
                        class="mt-2 text-xs leading-5"
                    >
                        {{ item.name }} · batas {{ date(item.due_date) }}
                        <span v-if="item.reason" class="block text-warning">{{
                            item.reason
                        }}</span>
                    </p>
                </div>
                <span
                    class="ml-auto inline-flex min-h-11 shrink-0 items-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                    >Lihat tugas</span
                >
            </Link>
        </template>
        <template v-if="!deviation">
            <section
                v-for="section in reminders"
                :key="section.key"
                class="rounded-xl border border-border p-3"
            >
                <div class="flex items-start gap-3">
                    <ClipboardList
                        class="mt-0.5 size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold">
                            {{ section.title }}
                        </h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ section.group.farm_name }} ·
                            {{ section.group.block_name }}
                        </p>
                    </div>
                </div>
                <ul
                    v-if="section.rows.length"
                    class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 marker:text-primary"
                >
                    <li
                        v-for="row in section.rows"
                        :key="row.id + ':' + row.date"
                        :data-subblock-id="row.id"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="min-w-0 font-medium"
                                >{{ subblockLabel(row.name) }} —
                                {{ timing(row.days) }}</span
                            >
                            <span
                                class="shrink-0 text-right text-sm whitespace-nowrap text-muted-foreground"
                                >{{ date(row.date) }}</span
                            >
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-3 text-xs text-muted-foreground">
                    Rincian subblok tidak tersedia pada notifikasi ini.
                </p>
                <div class="mt-3 flex justify-end">
                    <Link
                        :href="href(section.group)"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        >Lihat tugas panen</Link
                    >
                </div>
            </section>
        </template>
    </div>
</template>
