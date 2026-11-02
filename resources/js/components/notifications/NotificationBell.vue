<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const visible = computed(() => page.props.notificationAccess === true);
const count = ref(0);
const failed = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;
let request: AbortController | undefined;

async function refresh(): Promise<void> {
    if (!visible.value || document.hidden || request) return;
    request = new AbortController();
    const timeout = setTimeout(() => request?.abort(), 10000);
    try {
        const response = await fetch('/notifications/unread', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            signal: request.signal,
        });
        if (!response.ok) throw new Error('Notification count unavailable');
        const payload: { count: number } = await response.json();
        count.value = Number.isInteger(payload.count) ? payload.count : 0;
        failed.value = false;
    } catch {
        failed.value = true;
    } finally {
        clearTimeout(timeout);
        request = undefined;
    }
}

onMounted(() => {
    void refresh();
    timer = setInterval(() => void refresh(), 60000);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    request?.abort();
});
</script>

<template>
    <Link
        v-if="visible"
        href="/notifications"
        :aria-label="`Buka notifikasi${count ? `, ${count} belum dibaca` : ''}`"
        :title="
            failed ? 'Buka inbox untuk memuat ulang notifikasi' : 'Notifikasi'
        "
        class="relative inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-muted/40 text-primary transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
    >
        <Bell class="size-5" aria-hidden="true" />
        <span
            v-if="count"
            class="absolute -top-1 -right-1 rounded-full bg-primary px-1.5 py-0.5 text-xs font-semibold text-primary-foreground"
            aria-hidden="true"
            >{{ count > 99 ? '99+' : count }}</span
        >
    </Link>
</template>
