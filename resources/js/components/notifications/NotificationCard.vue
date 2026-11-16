<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import {
    Bell,
    Building2,
    Check,
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
import IncidentNotificationReport from './IncidentNotificationReport.vue';
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
            return { icon: Wheat, warning: false };
        case 'weekly_admin':
            return {
                icon: Sprout,
                warning: false,
            };
        case 'task_reminder':
            return {
                icon: ClipboardList,
                warning: false,
            };
        case 'incident_report':
            return {
                icon: TriangleAlert,
                warning: true,
            };
        case 'sop_deviation':
            return {
                icon: TriangleAlert,
                warning: true,
            };
        default:
            return { icon: Bell, warning: false };
    }
});
const preview = computed(() => {
    const content = report.value;
    if (content?.kind === 'incident_report')
        return content.incidents.length + ' laporan insiden baru';
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
        if (content.kind === 'task_reminder') {
            const subblocks = new Set(
                content.groups.flatMap((group) =>
                    group.items.flatMap(
                        (item) =>
                            item.subblocks?.map((block) => block.id) ?? [],
                    ),
                ),
            );
            return (
                content.groups.length +
                ' blok · ' +
                (subblocks.size || count) +
                ' subblok'
            );
        }
        return (
            count +
            (content.kind === 'sop_deviation' ? ' temuan · ' : ' tugas · ') +
            content.groups.length +
            ' blok'
        );
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
            class="cursor-pointer space-y-0 p-3 @md:space-y-2 @md:p-4"
            @click="expanded = !expanded"
        >
            <div class="mb-0 flex min-w-0 items-start gap-2 @md:items-center">
                <button
                    type="button"
                    class="flex min-h-11 min-w-0 flex-auto cursor-pointer items-center gap-2 rounded-lg focus-visible:outline-2 focus-visible:outline-ring @md:gap-3"
                    :aria-expanded="expanded"
                    :aria-controls="contentId"
                    @click.stop="expanded = !expanded"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-xl @md:size-10"
                        :class="
                            category.warning
                                ? 'bg-warning/10 text-warning'
                                : 'bg-primary/10 text-primary'
                        "
                        aria-hidden="true"
                        ><component
                            :is="category.icon"
                            class="size-4 @md:size-5"
                    /></span>
                    <span class="block min-w-0 flex-1 text-left">
                        <span class="block text-sm leading-5 font-semibold">{{
                            report?.kind === 'weekly_owner'
                                ? 'Laporan Mingguan Panen'
                                : notice.title
                        }}</span>
                        <span
                            v-if="showTenant && notice.tenant_name"
                            class="mt-1 inline-flex max-w-full items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Building2
                                class="size-3 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{
                                notice.tenant_name
                            }}</span>
                        </span>
                        <span
                            v-if="preview"
                            class="mt-1 hidden text-xs leading-5 text-muted-foreground @md:line-clamp-2"
                            >{{ preview }}</span
                        >
                        <span
                            v-if="period"
                            class="mt-2 hidden rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground @md:inline-flex"
                            >Periode {{ period }}</span
                        >
                        <time
                            v-else
                            class="mt-1.5 hidden text-xs text-muted-foreground @md:block"
                            >{{ notice.created_at }}</time
                        >
                    </span>
                </button>
                <div
                    class="ml-auto inline-flex items-center gap-0.5 @md:gap-1"
                    :class="
                        notice.can_read && !notice.is_read
                            ? 'min-w-[5.875rem] shrink-10'
                            : 'shrink-0'
                    "
                >
                    <Button
                        v-if="notice.can_read && !notice.is_read"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-auto min-h-9! min-w-16 shrink gap-1 px-1 py-1 text-xs leading-4 whitespace-normal has-[>svg]:px-1 @md:min-h-11! @md:gap-1.5 @md:px-2 @md:text-sm @md:has-[>svg]:px-2"
                        aria-label="Tandai dibaca"
                        title="Tandai dibaca"
                        :disabled="pending !== null || loading"
                        @click.stop="$emit('read', notice)"
                    >
                        <LoaderCircle
                            v-if="pending === notice.id"
                            class="size-3 animate-spin @md:size-4"
                            aria-hidden="true"
                        />
                        <Check
                            v-else
                            class="size-3 @md:size-4"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">Tandai dibaca</span>
                    </Button>
                    <button
                        type="button"
                        class="flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring @md:size-11"
                        :aria-expanded="expanded"
                        :aria-controls="contentId"
                        :aria-label="
                            expanded
                                ? 'Tutup detail notifikasi'
                                : 'Buka detail notifikasi'
                        "
                        @click.stop="expanded = !expanded"
                    >
                        <ChevronDown
                            class="size-4 transition-transform motion-reduce:transition-none"
                            :class="expanded ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </div>
            <div
                class="w-full space-y-1.5 pl-10 text-xs text-muted-foreground @md:hidden"
            >
                <p v-if="preview" class="leading-5">{{ preview }}</p>
                <span
                    v-if="period"
                    class="inline-flex rounded-md bg-muted px-2 py-1 font-medium"
                >
                    Periode {{ period }}
                </span>
                <time v-else class="block">{{ notice.created_at }}</time>
            </div>
        </header>
        <div
            v-show="expanded"
            :id="contentId"
            class="@container min-w-0 border-t border-border"
        >
            <div class="space-y-4 px-3 py-4 @md:px-5">
                <WeeklyHarvestReport
                    v-if="report?.kind === 'weekly_owner'"
                    :content="report"
                />
                <WeeklyActivityReport
                    v-else-if="report?.kind === 'weekly_admin'"
                    :content="report"
                />
                <IncidentNotificationReport
                    v-else-if="report?.kind === 'incident_report'"
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
