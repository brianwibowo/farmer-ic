<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import NumberPagination from '@/components/NumberPagination.vue';
import { paginationUrl } from '@/lib/pagination';
import { toast } from '@/lib/toast';

const props = withDefaults(
    defineProps<{
        paginator: { current_page: number; last_page: number };
        disabled?: boolean;
        label?: string;
        pageName?: string;
        preserveState?: boolean;
    }>(),
    {
        disabled: false,
        label: 'Pagination',
        pageName: 'page',
        preserveState: false,
    },
);
const page = usePage();
const loading = ref(false);

function visit(target: number): void {
    if (loading.value || props.disabled) return;
    const fail = (): false => {
        void toast.error('Halaman belum dapat dimuat. Silakan coba lagi.');
        return false;
    };
    router.visit(paginationUrl(page.url, target, props.pageName), {
        preserveScroll: true,
        preserveState: props.preserveState,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
        onError: fail,
        onNetworkError: fail,
        onHttpException: fail,
    });
}
</script>

<template>
    <NumberPagination
        :current-page="paginator.current_page"
        :total-pages="paginator.last_page"
        :disabled="disabled || loading"
        :aria-busy="loading"
        :label="label"
        @change="visit"
    />
</template>
