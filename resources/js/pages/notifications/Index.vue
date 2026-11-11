<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    Check,
    CheckCheck,
    ChevronDown,
    LoaderCircle,
    RotateCcw,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: [], inheritAttrs: false });
type Status = 'unread' | 'all';
type Filters = {
    status: Status;
    date_from: string;
    date_to: string;
    team_id: number | null;
};
type Notice = {
    id: string;
    title: string;
    message: string;
    tenant_name: string | null;
    is_read: boolean;
    created_at: string;
    date_key: string;
    date_label: string;
    can_read: boolean;
};
const props = defineProps<{
    notifications: {
        data: Notice[];
        current_page: number;
        last_page: number;
        total: number;
    };
    unreadCount: number;
    hasPhoneNumber: boolean;
    fonnteEnabled: boolean;
    filters: Filters;
    defaultFilters: Filters;
    tenantOptions: { id: number; name: string }[];
    canFilterTenant: boolean;
}>();
const page = usePage();
const layout = computed(() =>
    page.props.navigation?.admin ? AppLayout : FarmerMobileShell,
);
const filters = reactive<Filters>({ ...props.filters });
const loading = ref(false);
const pending = ref<string | null>(null);
const feedback = ref('');
const error = ref('');
const showPhoneWarning = ref(true);
const fieldErrors = ref<Record<string, string>>({});
let filterTimer: ReturnType<typeof setTimeout> | undefined;
const statuses = [
    { value: 'unread' as const, label: 'Belum dibaca' },
    { value: 'all' as const, label: 'Semua' },
];
const groups = computed(() => {
    const result = new Map<
        string,
        { key: string; label: string; notices: Notice[] }
    >();
    for (const notice of props.notifications.data) {
        const key = notice.date_key;
        if (!result.has(key)) {
            result.set(key, {
                key,
                label:
                    key === props.defaultFilters.date_from
                        ? 'Hari ini'
                        : notice.date_label,
                notices: [],
            });
        }
        result.get(key)!.notices.push(notice);
    }
    return [...result.values()];
});
const fieldClass = computed(() =>
    page.props.navigation?.admin
        ? 'admin-field w-full min-w-0 [color-scheme:light] dark:[color-scheme:dark]'
        : 'w-full min-w-0 rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring [color-scheme:light] dark:[color-scheme:dark]',
);
const appliedLabel = computed(() => {
    if (props.filters.status === 'unread') return 'Semua tanggal';
    const format = (value: string) =>
        new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Asia/Jakarta',
        }).format(new Date(`${value}T00:00:00+07:00`));
    const range =
        props.filters.date_from === props.filters.date_to
            ? format(props.filters.date_from)
            : `${format(props.filters.date_from)} – ${format(props.filters.date_to)}`;
    const status =
        statuses.find((item) => item.value === props.filters.status)?.label ??
        '';
    return `${status} · ${range}`;
});

watch(
    () => props.filters,
    (value) => Object.assign(filters, value),
);

watch(
    () => [filters.date_from, filters.date_to, filters.team_id] as const,
    () => {
        if (filterTimer) clearTimeout(filterTimer);
        if (filters.status !== 'all' || loading.value || pending.value) return;
        error.value = '';
        feedback.value = '';
        filterTimer = setTimeout(() => {
            if (!validFilters()) return;
            if (
                filters.date_from === props.filters.date_from &&
                filters.date_to === props.filters.date_to &&
                filters.team_id === props.filters.team_id
            )
                return;
            load({ ...filters });
        }, 300);
    },
);

function saveErrors(errors: Record<string, unknown>): void {
    fieldErrors.value = Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [key, String(value)]),
    );
    error.value =
        'Filter atau perubahan belum dapat diproses. Periksa isian dan coba lagi.';
}
function validFilters(): boolean {
    fieldErrors.value = {};
    if (!filters.date_from) fieldErrors.value.date_from = 'Pilih tanggal awal.';
    if (!filters.date_to) fieldErrors.value.date_to = 'Pilih tanggal akhir.';
    if (
        filters.date_from &&
        filters.date_to &&
        filters.date_from > filters.date_to
    ) {
        fieldErrors.value.date_to =
            'Tanggal akhir tidak boleh sebelum tanggal awal.';
    }
    return Object.keys(fieldErrors.value).length === 0;
}
function load(values: Filters, pageNumber = 1, reset = false): void {
    if (loading.value || pending.value) return;
    loading.value = true;
    error.value = '';
    fieldErrors.value = {};
    router.get('/notifications', reset ? {} : { ...values, page: pageNumber }, {
        preserveState: true,
        preserveScroll: true,
        preserveUrl: !reset,
        replace: true,
        onError: saveErrors,
        onFinish: () => {
            loading.value = false;
        },
    });
}
function selectStatus(status: Status): void {
    if (status === props.filters.status || loading.value || pending.value)
        return;
    if (filterTimer) clearTimeout(filterTimer);
    error.value = '';
    feedback.value = '';
    Object.assign(filters, { ...props.defaultFilters, status });
    load({ ...filters });
}
function reset(): void {
    if (filterTimer) clearTimeout(filterTimer);
    feedback.value = '';
    Object.assign(filters, { ...props.defaultFilters, status: filters.status });
    load({ ...filters });
}
function read(notice: Notice): void {
    pending.value = notice.id;
    error.value = '';
    router.patch(
        `/notifications/${notice.id}/read`,
        { ...props.filters, page: props.notifications.current_page },
        {
            preserveScroll: true,
            preserveUrl: true,
            onSuccess: () => {
                feedback.value = 'Notifikasi ditandai sudah dibaca.';
            },
            onError: saveErrors,
            onFinish: () => {
                pending.value = null;
                if (
                    props.notifications.current_page >
                    props.notifications.last_page
                ) {
                    load({ ...props.filters }, props.notifications.last_page);
                }
            },
        },
    );
}

onMounted(() => {
    // New entries and full reloads start with all unread notifications.
    if (
        props.filters.status !== 'unread' ||
        props.filters.date_from !== props.defaultFilters.date_from ||
        props.filters.date_to !== props.defaultFilters.date_to ||
        props.filters.team_id !== null ||
        props.notifications.current_page !== 1 ||
        window.location.search
    ) {
        Object.assign(filters, props.defaultFilters);
        load({ ...props.defaultFilters }, 1, true);
    }
});
onUnmounted(() => {
    if (filterTimer) clearTimeout(filterTimer);
});
</script>

<template>
    <Head title="Notifikasi" />
    <component :is="layout">
        <div
            class="space-y-[18px] text-foreground"
            :class="
                page.props.navigation?.admin
                    ? 'admin-dashboard-theme admin-page-content'
                    : 'mx-auto w-full max-w-4xl px-4 py-5 md:p-6'
            "
            :aria-busy="loading || pending !== null"
        >
            <header
                v-if="!page.props.navigation?.admin"
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <h1 class="text-2xl font-semibold">Notifikasi</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ props.unreadCount }} belum dibaca (total)
                    </p>
                </div>
            </header>

            <div
                class="grid grid-cols-2 border-b border-border"
                role="group"
                aria-label="Filter status notifikasi"
                :aria-busy="loading"
            >
                <button
                    v-for="item in statuses"
                    :key="item.value"
                    type="button"
                    :aria-pressed="props.filters.status === item.value"
                    :disabled="loading || pending !== null"
                    class="min-h-11 border-b-2 px-3 py-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50"
                    :class="
                        props.filters.status === item.value
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                    @click="selectStatus(item.value)"
                >
                    {{ item.label }}
                    <span
                        v-if="item.value === 'unread' && props.unreadCount"
                        class="ml-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs"
                        >{{ props.unreadCount }}</span
                    >
                </button>
            </div>

            <form
                v-if="props.filters.status === 'all'"
                class="@container rounded-xl border border-border bg-card p-4 md:p-5"
                aria-label="Filter notifikasi"
                @submit.prevent
            >
                <div
                    class="align-center mb-4 flex flex-row justify-between gap-3"
                >
                    <h2 class="flex items-center gap-2 font-semibold">
                        <SlidersHorizontal
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />Filter notifikasi
                    </h2>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="loading || pending !== null"
                        @click="reset"
                        ><RotateCcw
                            class="size-4"
                            aria-hidden="true"
                        />Reset</Button
                    >
                </div>
                <fieldset
                    :disabled="loading || pending !== null"
                    class="space-y-4"
                >
                    <div
                        class="grid min-w-0 gap-4 @md:grid-cols-2"
                        :class="{ '@xl:grid-cols-3': props.canFilterTenant }"
                    >
                        <div class="min-w-0 space-y-1.5">
                            <label
                                for="notification-date-from"
                                class="block text-sm font-medium"
                                >Dari tanggal</label
                            >
                            <input
                                id="notification-date-from"
                                v-model="filters.date_from"
                                type="date"
                                required
                                :class="fieldClass"
                                :aria-invalid="Boolean(fieldErrors.date_from)"
                                aria-describedby="notification-date-from-error"
                            />
                            <p
                                v-if="fieldErrors.date_from"
                                id="notification-date-from-error"
                                class="text-xs text-error"
                            >
                                {{ fieldErrors.date_from }}
                            </p>
                        </div>
                        <div class="min-w-0 space-y-1.5">
                            <label
                                for="notification-date-to"
                                class="block text-sm font-medium"
                                >Sampai tanggal</label
                            >
                            <input
                                id="notification-date-to"
                                v-model="filters.date_to"
                                type="date"
                                required
                                :min="filters.date_from || undefined"
                                :class="fieldClass"
                                :aria-invalid="Boolean(fieldErrors.date_to)"
                                aria-describedby="notification-date-to-error"
                            />
                            <p
                                v-if="fieldErrors.date_to"
                                id="notification-date-to-error"
                                class="text-xs text-error"
                            >
                                {{ fieldErrors.date_to }}
                            </p>
                        </div>
                        <div
                            v-if="props.canFilterTenant"
                            class="min-w-0 space-y-1.5 md:col-span-2 xl:col-span-1"
                        >
                            <label
                                for="notification-tenant"
                                class="block text-sm font-medium"
                                >Tenant</label
                            >
                            <AppSelect
                                id="notification-tenant"
                                v-model="filters.team_id"
                                :class="fieldClass"
                            >
                                <AppSelectOption :value="null"
                                    >Semua tenant</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="tenant in props.tenantOptions"
                                    :key="tenant.id"
                                    :value="tenant.id"
                                >
                                    {{ tenant.name }}
                                </AppSelectOption>
                            </AppSelect>
                        </div>
                    </div>
                </fieldset>
            </form>

            <div
                v-if="showPhoneWarning && props.fonnteEnabled && !props.hasPhoneNumber"
                class="flex items-start justify-between gap-3 rounded-xl border border-border bg-muted p-4 text-sm leading-6"
                role="status"
            >
                <p>
                    Agar notifikasi lewat WhatsApp dapat terkirim, mohon untuk
                    <Link
                        href="/settings/profile"
                        class="font-medium text-primary underline underline-offset-2"
                        >isi nomor di profil</Link
                    >.
                </p>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center self-stretch rounded-lg text-muted-foreground transition-colors hover:bg-background hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    aria-label="Tutup pemberitahuan nomor WhatsApp"
                    @click="showPhoneWarning = false"
                >
                    <X class="size-6" aria-hidden="true" />
                </button>
            </div>
            <p v-if="feedback" role="status" class="text-sm text-success">
                {{ feedback }}
            </p>
            <p v-if="error" role="alert" class="text-sm text-error">
                {{ error }}
            </p>
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                aria-live="polite"
            >
                <p v-if="props.filters.status === 'all'">{{ appliedLabel }}</p>
                <p>Menampilkan {{ props.notifications.total }} notifikasi</p>
            </div>
            <section
                v-if="!props.notifications.data.length"
                class="rounded-xl border border-dashed border-border bg-card px-5 py-10 text-center"
            >
                <Bell
                    class="mx-auto mb-3 size-8 text-muted-foreground"
                    aria-hidden="true"
                />
                <h2 class="font-semibold">
                    {{
                        props.filters.status === 'unread'
                            ? 'Semua notifikasi sudah dibaca'
                            : 'Tidak ada notifikasi sesuai filter'
                    }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground"
                >
                    {{
                        props.filters.status === 'unread'
                            ? 'Notifikasi baru akan muncul di sini. Buka tab Semua untuk melihat riwayat notifikasi.'
                            : 'Coba rentang tanggal atau tenant lain untuk melihat notifikasi.'
                    }}
                </p>
                <Button
                    v-if="
                        props.filters.status === 'all' &&
                        (props.filters.date_from !==
                            props.defaultFilters.date_from ||
                            props.filters.date_to !==
                                props.defaultFilters.date_to)
                    "
                    type="button"
                    variant="outline"
                    class="mt-4"
                    :disabled="loading"
                    @click="reset"
                    >Kembali ke hari ini</Button
                >
            </section>
            <div
                v-else
                class="space-y-8 md:space-y-10"
                aria-label="Daftar notifikasi berdasarkan tanggal"
                :class="{ 'opacity-60': loading }"
            >
                <section
                    v-for="group in groups"
                    :key="group.key"
                    :aria-labelledby="`date-${group.key}`"
                >
                    <h2
                        :id="`date-${group.key}`"
                        class="mb-3 text-sm font-semibold text-muted-foreground"
                    >
                        <time :datetime="group.key">{{ group.label }}</time>
                    </h2>
                    <ol class="space-y-2">
                        <li
                            v-for="notice in group.notices"
                            :key="notice.id"
                            :data-notification-id="notice.id"
                        >
                            <details
                                class="group rounded-xl border border-border bg-card"
                            >
                                <summary
                                    class="flex min-h-16 cursor-pointer list-none items-center gap-3 rounded-xl p-3 focus-visible:outline-2 focus-visible:outline-ring md:p-4 [&::-webkit-details-marker]:hidden"
                                >
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                        aria-hidden="true"
                                        ><Bell class="size-4"
                                    /></span>
                                    <span class="min-w-0 flex-1 space-y-1">
                                        <span
                                            class="block text-sm leading-5 font-semibold"
                                            >{{ notice.title }}</span
                                        >
                                        <span
                                            v-if="
                                                props.canFilterTenant &&
                                                notice.tenant_name
                                            "
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                            ><Building2
                                                class="size-3 shrink-0"
                                                aria-hidden="true"
                                            /><span class="truncate">{{
                                                notice.tenant_name
                                            }}</span></span
                                        >
                                        <time
                                            class="block text-xs text-muted-foreground"
                                            >{{ notice.created_at }}</time
                                        >
                                    </span>
                                    <span
                                        v-if="!notice.is_read"
                                        class="size-2 shrink-0 rounded-full bg-primary"
                                        role="img"
                                        aria-label="Belum dibaca"
                                    />
                                    <CheckCheck
                                        v-else
                                        class="size-4 shrink-0 text-muted-foreground"
                                        role="img"
                                        aria-label="Sudah dibaca"
                                    />
                                    <ChevronDown
                                        class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                                        aria-hidden="true"
                                    />
                                </summary>
                                <div
                                    class="border-t border-border px-4 py-4 md:px-5"
                                >
                                    <p
                                        class="text-sm leading-6 break-words whitespace-pre-wrap"
                                    >
                                        {{ notice.message }}
                                    </p>
                                    <Button
                                        v-if="
                                            notice.can_read && !notice.is_read
                                        "
                                        variant="outline"
                                        size="sm"
                                        class="mt-4 rounded-lg bg-primary text-white"
                                        :disabled="pending !== null || loading"
                                        @click="read(notice)"
                                    >
                                        <LoaderCircle
                                            v-if="pending === notice.id"
                                            class="size-4 animate-spin"
                                            aria-hidden="true"
                                        />
                                        <Check
                                            v-else
                                            class="size-4"
                                            aria-hidden="true"
                                        />Tandai dibaca
                                    </Button>
                                </div>
                            </details>
                        </li>
                    </ol>
                </section>
            </div>
            <nav
                v-if="props.notifications.last_page > 1"
                class="flex flex-wrap items-center justify-between gap-3 text-sm"
                aria-label="Halaman notifikasi"
            >
                <Button
                    variant="outline"
                    :disabled="
                        props.notifications.current_page === 1 ||
                        loading ||
                        pending !== null
                    "
                    @click="
                        load(
                            { ...props.filters },
                            props.notifications.current_page - 1,
                        )
                    "
                    >Sebelumnya</Button
                >
                <span aria-current="page"
                    >{{ props.notifications.current_page }} /
                    {{ props.notifications.last_page }}</span
                >
                <Button
                    variant="outline"
                    :disabled="
                        props.notifications.current_page >=
                            props.notifications.last_page ||
                        loading ||
                        pending !== null
                    "
                    @click="
                        load(
                            { ...props.filters },
                            props.notifications.current_page + 1,
                        )
                    "
                    >Berikutnya</Button
                >
            </nav>
        </div>
    </component>
</template>
