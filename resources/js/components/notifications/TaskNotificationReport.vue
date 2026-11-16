<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, ClipboardList, TriangleAlert } from '@lucide/vue';
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
        return '/admin/tasks?' + query.toString();
    }
    query.set('status', 'all');
    return '/petugas/aktivitas?' + query.toString();
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
            <template v-else>
                <Link
                    v-for="(item, index) in group.items"
                    :key="index"
                    :href="href(group)"
                    class="flex min-h-11 items-start gap-3 rounded-xl border border-border p-3 hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <ClipboardList
                        class="mt-1 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold">{{ item.name }}</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ group.farm_name }} · {{ group.block_name }}
                        </p>
                        <ul
                            v-if="item.subblocks?.length"
                            class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 marker:text-primary"
                        >
                            <li
                                v-for="subblock in item.subblocks"
                                :key="subblock.id"
                                :data-subblock-id="subblock.id"
                            >
                                <span class="font-medium">{{
                                    subblock.name
                                }}</span>
                                <span class="text-muted-foreground">
                                    · H-{{ item.days_before }} ·
                                    {{ date(item.scheduled_date) }}</span
                                >
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-xs text-muted-foreground">
                            Rincian subblok tidak tersedia pada notifikasi lama
                            ini.
                        </p>
                    </div>
                    <ChevronRight
                        class="mt-1 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Link>
            </template>
        </template>
    </div>
</template>
