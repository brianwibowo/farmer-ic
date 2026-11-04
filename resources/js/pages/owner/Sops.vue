<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Search } from '@lucide/vue';
import { watch } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerPagination from '@/components/owner/OwnerPagination.vue';
import OwnerReadEmpty from '@/components/owner/OwnerReadEmpty.vue';
import OwnerReadFeedback from '@/components/owner/OwnerReadFeedback.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import type { OwnerReadPage, OwnerSopRow } from '@/types/owner-secondary';

const props = defineProps<{
    sops: OwnerReadPage<OwnerSopRow>;
    filters: { search?: string };
}>();
const { filters, loading, error, feedback, submit, reset, sync } =
    useOwnerReadFilters('/lainnya/sop', { search: props.filters.search ?? '' });
watch(
    () => props.filters,
    () => sync({ search: props.filters.search ?? '' }),
);
</script>

<template>
    <Head title="SOP kebun" />
    <div class="space-y-5" :aria-busy="loading">
        <OwnerPageHeader
            title="SOP"
            description="Lihat panduan kerja dan versi SOP yang digunakan tim Anda."
            back-href="/lainnya"
        />
        <form
            class="space-y-3 rounded-xl border border-border bg-card p-4"
            @submit.prevent="submit()"
        >
            <label for="owner-sop-search" class="block text-xs font-medium"
                >Cari SOP</label
            >
            <Input
                id="owner-sop-search"
                v-model="filters.search"
                placeholder="Nama atau deskripsi SOP"
                maxlength="100"
                class="h-11 rounded-xl bg-background text-base"
                :disabled="loading"
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    class="min-h-11 flex-1 rounded-xl"
                    :disabled="loading"
                    ><Search aria-hidden="true" />Cari</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 rounded-xl"
                    :disabled="loading"
                    @click="reset({ search: '' })"
                    >Reset filter</Button
                >
            </div>
        </form>
        <OwnerReadFeedback
            :loading="loading"
            :error="error"
            :feedback="feedback"
        />
        <p class="text-xs text-muted-foreground" aria-live="polite">
            {{ sops.total }} SOP{{
                props.filters.search ? ' sesuai pencarian' : ' tersedia'
            }}
        </p>
        <div v-if="sops.data.length" class="space-y-3">
            <Link
                v-for="sop in sops.data"
                :key="sop.id"
                :href="`/lainnya/sop/${sop.id}`"
                class="block rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-secondary dark:text-primary"
                            ><BookOpen class="size-5" aria-hidden="true"
                        /></span>
                        <div class="min-w-0">
                            <h2 class="text-sm leading-6 font-semibold">
                                {{ sop.name }}
                            </h2>
                            <p
                                class="mt-0.5 text-xs leading-5 text-muted-foreground"
                            >
                                {{
                                    sop.commodity?.name ??
                                    'Komoditas belum tersedia'
                                }}
                            </p>
                        </div>
                    </div>
                    <ArrowRight
                        class="mt-2 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <p
                    v-if="sop.description"
                    class="mt-3 text-sm leading-6 text-muted-foreground"
                >
                    {{ sop.description }}
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    <span
                        class="rounded-lg px-2 py-1"
                        :class="
                            sop.is_active
                                ? 'bg-success/10 text-success'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ sop.is_active ? 'Aktif' : 'Nonaktif' }}</span
                    >
                    <span class="text-muted-foreground"
                        >{{ sop.versions_count }} versi</span
                    >
                    <span
                        v-if="sop.published_version !== null"
                        class="text-muted-foreground"
                        >· v{{ sop.published_version }} diterbitkan</span
                    >
                </div>
            </Link>
        </div>
        <OwnerReadEmpty
            v-else
            :title="
                props.filters.search ? 'SOP tidak ditemukan' : 'Belum ada SOP'
            "
            :description="
                props.filters.search
                    ? 'Coba cari dengan nama atau kata kunci lain.'
                    : 'SOP akan muncul setelah template dibuat oleh Admin pada tenant ini.'
            "
        />
        <OwnerPagination :paginator="sops" />
    </div>
</template>
