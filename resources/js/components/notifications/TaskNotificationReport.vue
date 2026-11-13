<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, ClipboardList, TriangleAlert } from '@lucide/vue';
import type { TaskContent } from '@/types/notifications';
import { date } from './format';
const props = defineProps<{ content: TaskContent }>();
const deviation = computed(() => props.content.kind === 'sop_deviation');
function href(group: TaskContent['groups'][number]): string {
    const farm = group.farm_id ?? group.key.split(':')[0];
    const block = group.block_id ?? group.key.split(':')[1];
    const query = new URLSearchParams();
    if (block && block !== 'unassigned') query.set('block_id', block);
    if (deviation.value) {
        query.set('view', 'overdue');
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
                class="flex min-h-11 items-start gap-3 rounded-xl border border-border p-3 hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-ring"
            >
                <TriangleAlert
                    class="mt-1 size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-semibold">{{ group.farm_name }}</h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ group.block_name }} ·
                        {{ group.items.reduce((n, item) => n + item.count, 0) }}
                        tugas melewati toleransi
                    </p>
                    <p
                        v-for="(item, index) in group.items"
                        :key="index"
                        class="mt-2 text-xs leading-5"
                    >
                        {{ item.name }} · batas {{ date(item.due_date) }}
                    </p>
                    <p class="mt-2 text-xs font-medium text-primary">
                        Lihat tugas terlambat
                    </p>
                </div>
                <ChevronRight
                    class="mt-1 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
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
                        <p class="mt-2 text-xs leading-5">
                            <span class="font-medium text-primary"
                                >H-{{ item.days_before }}</span
                            >
                            · {{ date(item.scheduled_date) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Batas toleransi {{ date(item.due_date)
                            }}<span v-if="item.count > 1">
                                · {{ item.count }} tugas</span
                            >
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
