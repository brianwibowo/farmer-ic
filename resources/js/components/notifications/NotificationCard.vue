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
const messageId = useId();
const fullText = ref(false);
const longText = computed(
    () =>
        props.notice.message.length > 220 ||
        props.notice.message.split('\n').length > 6,
);
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
    if (content?.kind === 'weekly_owner') {
        const harvests = content.totals.commodities.reduce(
            (sum, crop) => sum + crop.harvest_count,
            0,
        );
        return (
            content.regions.length +
            ' kabupaten / kota · ' +
            (harvests ? harvests + ' laporan panen' : 'Belum ada panen')
        );
    }
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
            ' kebun · ' +
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
    <details
        class="group/notice min-w-0 rounded-xl border border-border bg-card transition-colors open:border-primary/30"
        :data-rich-inbox="report?.kind ?? 'text'"
    >
        <summary
            class="flex min-h-20 cursor-pointer list-none items-start gap-3 rounded-xl p-3 focus-visible:outline-2 focus-visible:outline-ring @md:p-4 [&::-webkit-details-marker]:hidden"
        >
            <span
                class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl"
                :class="
                    category.warning
                        ? 'bg-warning/10 text-warning'
                        : 'bg-primary/10 text-primary'
                "
                aria-hidden="true"
            >
                <component :is="category.icon" class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span
                        class="text-xs font-medium"
                        :class="
                            category.warning ? 'text-warning' : 'text-primary'
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
                        /><span class="truncate">{{ notice.tenant_name }}</span>
                    </span>
                </span>
                <span class="block text-sm leading-5 font-semibold">{{
                    notice.title
                }}</span>
                <span
                    v-if="preview"
                    class="mt-1 line-clamp-2 block text-xs leading-5 text-muted-foreground"
                    >{{ preview }}</span
                >
                <time class="mt-1.5 block text-xs text-muted-foreground">{{
                    notice.created_at
                }}</time>
            </span>
            <span class="mt-1 flex shrink-0 items-center gap-2">
                <span
                    v-if="!notice.is_read"
                    class="size-2 rounded-full bg-primary"
                    role="img"
                    aria-label="Belum dibaca"
                />
                <CheckCheck
                    v-else
                    class="size-4 text-muted-foreground"
                    role="img"
                    aria-label="Sudah dibaca"
                />
                <ChevronDown
                    class="size-4 text-muted-foreground transition-transform group-open/notice:rotate-180 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </span>
        </summary>
        <div class="@container min-w-0 border-t border-border">
            <div class="space-y-4 px-3 py-4 @md:px-5">
                <p
                    v-if="period"
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
                <div v-else>
                    <p
                        :id="messageId"
                        class="text-sm leading-6 break-words whitespace-pre-wrap"
                        :class="longText && !fullText ? 'line-clamp-6' : ''"
                    >
                        {{ notice.message }}
                    </p>
                    <Button
                        v-if="longText"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="mt-2 min-h-11"
                        :aria-expanded="fullText"
                        :aria-controls="messageId"
                        @click="fullText = !fullText"
                    >
                        {{
                            fullText ? 'Ringkas teks' : 'Tampilkan seluruh isi'
                        }}
                        <ChevronDown
                            class="size-4 transition-transform motion-reduce:transition-none"
                            :class="fullText ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
            </div>
            <div
                v-if="notice.can_read && !notice.is_read"
                class="flex justify-end border-t border-border px-3 py-3 @md:px-5"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-11 w-full @md:w-auto"
                    :disabled="pending !== null || loading"
                    @click="$emit('read', notice)"
                >
                    <LoaderCircle
                        v-if="pending === notice.id"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    <Check v-else class="size-4" aria-hidden="true" />Tandai
                    dibaca
                </Button>
            </div>
        </div>
    </details>
</template>
