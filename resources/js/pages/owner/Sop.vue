<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen, ChevronDown } from '@lucide/vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerReadEmpty from '@/components/owner/OwnerReadEmpty.vue';
import {
    ownerReadDate,
    ownerReadStatus,
    type OwnerSopDetail,
    type OwnerSopTask,
} from '@/types/owner-secondary';
import {
    hstLabel,
    sopActivityCategoryLabels,
    sopActivitySubcategoryLabels,
} from '@/types/sop';

defineProps<{ template: OwnerSopDetail }>();
const categoryLabels: Record<string, string> = sopActivityCategoryLabels;
const subcategoryLabels: Record<string, string> = sopActivitySubcategoryLabels;
function category(task: OwnerSopTask): string {
    return task.activity_subcategory
        ? (subcategoryLabels[task.activity_subcategory] ??
              categoryLabels[task.activity_category] ??
              task.activity_category)
        : (categoryLabels[task.activity_category] ?? task.activity_category);
}
</script>

<template>
    <Head :title="template.name" />
    <div class="space-y-5">
        <OwnerPageHeader
            :title="template.name"
            :description="template.commodity?.name ?? 'Panduan kerja kebun'"
            back-href="/lainnya/sop"
            back-label="Daftar SOP"
        />
        <section class="rounded-xl border border-border bg-card p-4">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span
                    class="rounded-lg px-2 py-1"
                    :class="
                        template.is_active
                            ? 'bg-success/10 text-success'
                            : 'bg-muted text-muted-foreground'
                    "
                    >{{ template.is_active ? 'Aktif' : 'Nonaktif' }}</span
                >
                <span class="text-muted-foreground"
                    >{{ template.versions_count }} versi</span
                >
                <span
                    v-if="template.published_version !== null"
                    class="text-muted-foreground"
                    >· v{{ template.published_version }} diterbitkan</span
                >
            </div>
            <p
                class="mt-3 text-sm leading-6 whitespace-pre-line text-muted-foreground"
            >
                {{ template.description || 'Belum ada deskripsi SOP.' }}
            </p>
        </section>

        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <BookOpen class="size-4 text-primary" aria-hidden="true" />
                <h2 class="text-base font-semibold">Versi dan langkah kerja</h2>
            </div>
            <p class="text-xs leading-5 text-muted-foreground">
                Setiap batch mengikuti versi SOP yang ditetapkan saat batch
                dibuat.
            </p>
            <template v-if="template.versions.length">
                <details
                    v-for="version in template.versions"
                    :key="version.id"
                    :open="version.status === 'published'"
                    class="group overflow-hidden rounded-xl border border-border bg-card"
                >
                    <summary
                        class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 p-4 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold">
                                Versi {{ version.version }}
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ ownerReadStatus(version.status) }} ·
                                {{ version.tasks.length }} langkah
                            </p>
                        </div>
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </summary>
                    <div class="space-y-4 border-t border-border p-4">
                        <p
                            v-if="version.published_at"
                            class="text-xs text-muted-foreground"
                        >
                            Diterbitkan
                            {{ ownerReadDate(version.published_at) }}
                        </p>
                        <p
                            v-if="version.notes"
                            class="text-sm leading-6 whitespace-pre-line text-muted-foreground"
                        >
                            {{ version.notes }}
                        </p>
                        <ol v-if="version.tasks.length" class="space-y-4">
                            <li
                                v-for="(task, index) in version.tasks"
                                :key="task.id"
                                class="relative border-l-2 border-primary/30 pl-4"
                            >
                                <span
                                    class="absolute top-1 -left-[5px] size-2 rounded-full bg-primary"
                                    aria-hidden="true"
                                />
                                <p
                                    class="text-xs font-medium text-secondary dark:text-primary"
                                >
                                    {{ hstLabel(task)
                                    }}<span v-if="task.tolerance_days">
                                        · toleransi ±{{
                                            task.tolerance_days
                                        }}
                                        hari</span
                                    >
                                </p>
                                <h4
                                    class="mt-1 text-sm leading-6 font-semibold"
                                >
                                    {{ index + 1 }}. {{ task.name }}
                                </h4>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ category(task) }} ·
                                    {{
                                        task.is_required ? 'Wajib' : 'Opsional'
                                    }}
                                </p>
                                <p
                                    v-if="task.instructions"
                                    class="mt-2 text-sm leading-6 whitespace-pre-line text-muted-foreground"
                                >
                                    {{ task.instructions }}
                                </p>
                            </li>
                        </ol>
                        <p
                            v-else
                            class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
                        >
                            Belum ada langkah kerja pada versi ini.
                        </p>
                    </div>
                </details>
            </template>
            <OwnerReadEmpty
                v-else
                title="Belum ada versi SOP"
                description="Versi dan langkah kerja akan tampil setelah dicatat oleh Admin."
            />
        </div>
    </div>
</template>
