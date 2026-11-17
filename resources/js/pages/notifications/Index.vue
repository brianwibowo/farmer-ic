<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, CheckCheck, X } from '@lucide/vue';
import NotificationCard from '@/components/notifications/NotificationCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import PetugasLayout from '@/layouts/PetugasLayout.vue';
import type { InboxNotice } from '@/types/notifications';
defineOptions({ layout: [], inheritAttrs: false });
const props = defineProps<{
    notifications: {
        data: InboxNotice[];
        current_page: number;
        last_page: number;
        total: number;
    };
    unreadCount: number;
    hasPhoneNumber: boolean;
    fonnteEnabled: boolean;
    canFilterTenant: boolean;
    status: 'all' | 'unread' | 'read';
    category: string;
}>();
const page = usePage();
const usesAdminShell = computed(
    () => page.props.navigation?.admin || page.props.navigation?.owner,
);
const layout = computed(() =>
    usesAdminShell.value ? AppLayout : PetugasLayout,
);
const loading = ref(false);
const pending = ref<string | null>(null);
const feedback = ref('');
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;
onBeforeUnmount(() => clearTimeout(feedbackTimer));
const error = ref('');
const showPhoneWarning = ref(true);
function load(
    pageNumber: number,
    status = props.status,
    category = props.category,
): void {
    loading.value = true;
    error.value = '';
    router.get(
        '/notifications',
        { page: pageNumber, status, category },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                error.value = 'Notifikasi belum dapat dimuat. Coba lagi.';
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
function readAll(): void {
    loading.value = true;
    error.value = '';
    router.patch(
        '/notifications/read-all',
        { status: props.status, category: props.category, page: 1 },
        {
            preserveScroll: true,
            onSuccess: () => {
                clearTimeout(feedbackTimer);
                feedback.value = 'Semua notifikasi ditandai sudah dibaca.';
                feedbackTimer = setTimeout(() => {
                    feedback.value = '';
                }, 5000);
            },
            onError: () => {
                error.value = 'Perubahan belum dapat disimpan. Coba lagi.';
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
function read(notice: InboxNotice): void {
    pending.value = notice.id;
    error.value = '';
    router.patch(
        '/notifications/' + notice.id + '/read',
        {
            page: props.notifications.current_page,
            status: props.status,
            category: props.category,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                clearTimeout(feedbackTimer);
                feedback.value = 'Notifikasi ditandai sudah dibaca.';
            },
            onError: () => {
                error.value = 'Perubahan belum dapat disimpan. Coba lagi.';
            },
            onFinish: () => {
                pending.value = null;
                if (
                    props.notifications.current_page >
                    props.notifications.last_page
                ) {
                    load(props.notifications.last_page);
                }
            },
        },
    );
}
</script>
<template>
    <Head title="Notifikasi" />
    <component :is="layout">
        <div
            class="space-y-4 text-foreground"
            :class="
                usesAdminShell
                    ? 'admin-dashboard-theme admin-page-content'
                    : 'mx-auto w-full max-w-4xl min-w-0'
            "
            :aria-busy="loading || pending !== null"
        >
            <header v-if="!usesAdminShell">
                <h1 class="text-2xl font-semibold">Notifikasi</h1>
            </header>
            <div
                class="grid grid-cols-2 border-b border-border"
                role="group"
                aria-label="Filter status notifikasi"
            >
                <button
                    v-for="item in [
                        { value: 'unread', label: 'Belum dibaca' },
                        { value: 'read', label: 'Sudah dibaca' },
                    ]"
                    :key="item.value"
                    type="button"
                    :aria-pressed="status === item.value"
                    :aria-label="
                        item.value === 'unread'
                            ? `Belum dibaca, ${unreadCount} notifikasi`
                            : item.label
                    "
                    :disabled="loading || pending !== null"
                    class="min-h-11 border-b-2 px-2 py-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50"
                    :class="
                        status === item.value
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground'
                    "
                    @click="load(1, item.value as 'all' | 'unread' | 'read')"
                >
                    {{ item.label }}
                    <span
                        v-if="item.value === 'unread'"
                        aria-hidden="true"
                        class="ml-1 inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground tabular-nums"
                        >{{ unreadCount }}</span
                    >
                </button>
            </div>
            <div
                class="flex flex-col items-end justify-end gap-2 sm:flex-row sm:items-center"
            >
                <Button
                    v-if="status !== 'read'"
                    variant="outline"
                    class="min-h-11 gap-2"
                    :disabled="loading || pending !== null || unreadCount === 0"
                    @click="readAll"
                >
                    <CheckCheck class="size-4" aria-hidden="true" /> Tandai
                    semua telah dibaca
                </Button>
                <div class="relative w-fit">
                    <select
                        aria-label="Jenis kategori notifikasi"
                        :value="category"
                        :disabled="loading || pending !== null"
                        class="min-h-11 w-full appearance-none rounded-lg border border-border bg-card py-2 pr-9 pl-3 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                        @change="
                            load(
                                1,
                                status,
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="all">Semua</option>
                        <option value="weekly_report">Laporan Mingguan</option>
                        <option value="task_reminder">Reminder Panen</option>
                        <option value="sop_deviation">
                            Ketidaksesuaian SOP
                        </option>
                        <option value="incident_report">Laporan Insiden</option>
                    </select>
                    <ChevronDown
                        class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
            </div>
            <div
                v-if="showPhoneWarning && fonnteEnabled && !hasPhoneNumber"
                class="flex items-center gap-3 rounded-xl border border-border bg-muted p-3 text-sm"
                role="status"
            >
                <p class="flex-1">
                    Lengkapi
                    <Link
                        href="/settings/profile"
                        class="text-primary underline"
                        >nomor WhatsApp di profil</Link
                    >
                    untuk menerima pesan WhatsApp.
                </p>
                <button
                    type="button"
                    class="flex size-11 shrink-0 items-center justify-center rounded-lg focus-visible:outline-2 focus-visible:outline-ring"
                    aria-label="Tutup pemberitahuan nomor WhatsApp"
                    @click="showPhoneWarning = false"
                >
                    <X class="size-5" />
                </button>
            </div>
            <p
                v-if="loading"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Memuat notifikasi…
            </p>
            <p v-if="feedback" role="status" class="text-sm text-success">
                {{ feedback }}
            </p>
            <p v-if="error" role="alert" class="text-sm text-error">
                {{ error }}
            </p>
            <section
                v-if="!notifications.data.length"
                class="rounded-xl border border-dashed border-border bg-card p-8 text-center"
            >
                <Bell
                    class="mx-auto mb-3 size-8 text-muted-foreground"
                    aria-hidden="true"
                />
                <h2 class="font-semibold">
                    {{
                        category !== 'all'
                            ? 'Tidak ada notifikasi pada kategori ini'
                            : status === 'unread'
                              ? 'Semua notifikasi sudah dibaca'
                              : status === 'read'
                                ? 'Belum ada notifikasi yang dibaca'
                                : 'Belum ada notifikasi'
                    }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Notifikasi dari sistem akan muncul di sini.
                </p>
            </section>
            <div v-else class="space-y-3" aria-label="Daftar notifikasi">
                <div
                    v-for="notice in notifications.data"
                    :key="notice.id"
                    :data-notification-id="notice.id"
                >
                    <NotificationCard
                        :notice="notice"
                        :loading="loading"
                        :pending="pending"
                        :show-tenant="canFilterTenant"
                        @read="read"
                    />
                </div>
            </div>
            <nav
                v-if="notifications.last_page > 1"
                class="flex items-center justify-between gap-3"
                aria-label="Halaman notifikasi"
            >
                <Button
                    variant="outline"
                    class="min-h-11"
                    :disabled="
                        loading ||
                        pending !== null ||
                        notifications.current_page === 1
                    "
                    @click="load(notifications.current_page - 1)"
                    >Sebelumnya</Button
                >
                <span class="text-xs text-muted-foreground" aria-current="page"
                    >{{ notifications.current_page }} /
                    {{ notifications.last_page }}</span
                >
                <Button
                    variant="outline"
                    class="min-h-11"
                    :disabled="
                        loading ||
                        pending !== null ||
                        notifications.current_page === notifications.last_page
                    "
                    @click="load(notifications.current_page + 1)"
                    >Berikutnya</Button
                >
            </nav>
        </div>
    </component>
</template>
