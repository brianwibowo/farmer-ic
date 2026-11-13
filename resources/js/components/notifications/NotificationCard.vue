<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import {
    Bell,
    Building2,
    Check,
    CheckCheck,
    ChevronDown,
    ClipboardList,
    LoaderCircle,
    Sprout,
    TriangleAlert,
    Wheat,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { InboxNotice } from '@/types/notifications';
import { date } from './format';
import WeeklyHarvestReport from './WeeklyHarvestReport.vue';
import WeeklyActivityReport from './WeeklyActivityReport.vue';
import TaskNotificationReport from './TaskNotificationReport.vue';

const props = defineProps<{
    notice: InboxNotice;
    loading: boolean;
    pending: string | null;
    showTenant: boolean;
}>();
defineEmits<{ read: [notice: InboxNotice] }>();
const report = computed(() =>
    props.notice.content?.version === 1 ? props.notice.content : null,
);
const expanded = ref(false);
const contentId = useId();
const category = computed(() => {
    switch (report.value?.kind) {
        case 'weekly_owner':
            return { label: 'Panen mingguan', icon: Wheat, warning: false };
        case 'weekly_admin':
            return {
                label: 'Aktivitas mingguan',
                icon: Sprout,
                warning: false,
            };
        case 'task_reminder':
            return {
                label: 'Pengingat tugas',
                icon: ClipboardList,
                warning: false,
            };
        case 'sop_deviation':
            return {
                label: 'Perlu perhatian',
                icon: TriangleAlert,
                warning: true,
            };
        default:
            return { label: 'Notifikasi', icon: Bell, warning: false };
    }
});
const preview = computed(() => {
    const content = report.value;
    if (content?.kind === 'weekly_owner') return '';
    if (content?.kind === 'weekly_admin') {
        const activities = content.farms.reduce(
            (sum, farm) => sum + farm.activity_count,
            0,
        );
        const incidents = content.farms.reduce(
            (sum, farm) =>
                sum +
                farm.blocks.reduce(
                    (count, block) =>
                        count +
                        block.incidents.reduce(
                            (total, item) => total + item.count,
                            0,
                        ),
                    0,
                ),
            0,
        );
        return (
            content.farms.length +
            ' lahan · ' +
            activities +
            ' aktivitas' +
            (incidents ? ' · ' + incidents + ' insiden' : '')
        );
    }
    if (
        content?.kind === 'task_reminder' ||
        content?.kind === 'sop_deviation'
    ) {
        const count = content.groups.reduce(
            (sum, group) =>
                sum +
                group.items.reduce((total, item) => total + item.count, 0),
            0,
        );
        return count + ' tugas · ' + content.groups.length + ' blok';
    }
    return props.notice.message.replace(/\s+/g, ' ').slice(0, 160);
});
const period = computed(() => {
    const content = report.value;
    return content?.kind === 'weekly_owner' || content?.kind === 'weekly_admin'
        ? date(content.period.from) + ' – ' + date(content.period.to)
        : null;
});
</script>

<template>
    <article
        class="@container min-w-0 rounded-xl border border-border bg-card"
        :data-rich-inbox="report?.kind ?? 'text'"
    >
        <header
            class="flex flex-wrap items-center gap-2 p-3 @md:flex-nowrap @md:p-4"
        >
            <button
                type="button"
                class="flex min-h-11 min-w-0 flex-1 basis-full items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-ring @md:basis-auto"
                :aria-expanded="expanded"
                :aria-controls="contentId"
                @click="expanded = !expanded"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                    :class="
                        category.warning
                            ? 'bg-warning/10 text-warning'
                            : 'bg-primary/10 text-primary'
                    "
                    aria-hidden="true"
                    ><component :is="category.icon" class="size-5"
                /></span>
                <span class="block min-w-0 flex-1 text-left">
                    <span
                        v-if="
                            report?.kind !== 'weekly_owner' ||
                            (showTenant && notice.tenant_name)
                        "
                        class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1"
                    >
                        <span
                            v-if="report?.kind !== 'weekly_owner'"
                            class="text-xs font-medium"
                            :class="
                                category.warning
                                    ? 'text-warning'
                                    : 'text-primary'
                            "
                            >{{ category.label }}</span
                        >
                        <span
                            v-if="showTenant && notice.tenant_name"
                            class="inline-flex max-w-full items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Building2
                                class="size-3 shrink-0"
                                aria-hidden="true"
                            /><span class="truncate">{{
                                notice.tenant_name
                            }}</span>
                        </span>
                    </span>
                    <span class="block text-sm leading-5 font-semibold">{{
                        report?.kind === 'weekly_owner'
                            ? 'Laporan Mingguan Panen'
                            : notice.title
                    }}</span>
                    <span
                        v-if="preview"
                        class="mt-1 line-clamp-2 block text-xs leading-5 text-muted-foreground"
                        >{{ preview }}</span
                    >
                    <span
                        v-if="report?.kind === 'weekly_owner' && period"
                        class="mt-2 inline-flex rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >Periode {{ period }}</span
                    >
                    <time
                        v-else
                        class="mt-1.5 block text-xs text-muted-foreground"
                        >{{ notice.created_at }}</time
                    >
                </span>
            </button>
            <div class="ml-auto flex items-center gap-1">
                <Button
                    v-if="notice.can_read && !notice.is_read"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-11 shrink-0 gap-1.5 px-2 text-sm"
                    aria-label="Tandai dibaca"
                    title="Tandai dibaca"
                    :disabled="pending !== null || loading"
                    @click="$emit('read', notice)"
                >
                    <LoaderCircle
                        v-if="pending === notice.id"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    <Check v-else class="size-4" aria-hidden="true" />
                    Tandai dibaca
                </Button>
                <button
                    type="button"
                    class="flex size-11 items-center justify-center rounded-lg text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    :aria-expanded="expanded"
                    :aria-controls="contentId"
                    :aria-label="
                        expanded
                            ? 'Tutup detail notifikasi'
                            : 'Buka detail notifikasi'
                    "
                    @click="expanded = !expanded"
                >
                    <CheckCheck
                        v-if="notice.is_read"
                        class="mr-1 size-4"
                        aria-label="Sudah dibaca"
                    />
                    <ChevronDown
                        class="size-4 transition-transform motion-reduce:transition-none"
                        :class="expanded ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </header>
        <div
            v-show="expanded"
            :id="contentId"
            class="@container min-w-0 border-t border-border"
        >
            <div class="space-y-4 px-3 py-4 @md:px-5">
                <p
                    v-if="period && report?.kind !== 'weekly_owner'"
                    class="inline-flex rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                >
                    Periode {{ period }}
                </p>
                <WeeklyHarvestReport
                    v-if="report?.kind === 'weekly_owner'"
                    :content="report"
                />
                <WeeklyActivityReport
                    v-else-if="report?.kind === 'weekly_admin'"
                    :content="report"
                />
                <TaskNotificationReport
                    v-else-if="
                        report?.kind === 'task_reminder' ||
                        report?.kind === 'sop_deviation'
                    "
                    :content="report"
                />
                <p
                    v-else
                    class="text-sm leading-6 break-words whitespace-pre-wrap"
                >
                    {{ notice.message }}
                </p>
            </div>
        </div>
    </article>
</template>
