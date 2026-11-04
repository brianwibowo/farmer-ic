<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, MapPin, Search, UserRound } from '@lucide/vue';
import { computed, watch } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerPagination from '@/components/owner/OwnerPagination.vue';
import OwnerReadEmpty from '@/components/owner/OwnerReadEmpty.vue';
import OwnerReadFeedback from '@/components/owner/OwnerReadFeedback.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import {
    ownerReadRole,
    type OwnerReadPage,
    type OwnerUserRow,
} from '@/types/owner-secondary';

const props = defineProps<{
    users: OwnerReadPage<OwnerUserRow>;
    filters: { search?: string; role?: string };
}>();
const defaults = { search: '', role: '' };
const values = () => ({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
});
const { filters, loading, error, feedback, submit, reset, sync } =
    useOwnerReadFilters('/lainnya/pengguna', values());
watch(
    () => props.filters,
    () => sync(values()),
);
const hasFilters = computed(
    () => !!props.filters.search || !!props.filters.role,
);
</script>

<template>
    <Head title="Data pengguna" />
    <div class="space-y-5" :aria-busy="loading">
        <OwnerPageHeader
            title="Data pengguna"
            description="Lihat anggota tim dan penugasan kebun dalam tenant aktif."
            back-href="/lainnya"
        />
        <form
            class="space-y-4 rounded-xl border border-border bg-card p-4"
            @submit.prevent="submit()"
        >
            <div class="space-y-1.5">
                <label for="owner-user-search" class="text-xs font-medium"
                    >Cari pengguna</label
                ><Input
                    id="owner-user-search"
                    v-model="filters.search"
                    placeholder="Nama atau email"
                    maxlength="100"
                    class="h-11 rounded-xl bg-background text-base"
                    :disabled="loading"
                />
            </div>
            <div class="space-y-1.5">
                <label for="owner-user-role" class="text-xs font-medium"
                    >Role aplikasi</label
                >
                <AppSelect
                    id="owner-user-role"
                    v-model="filters.role"
                    :disabled="loading"
                >
                    <AppSelectOption value="">Semua role</AppSelectOption>
                    <AppSelectOption value="owner">Owner</AppSelectOption>
                    <AppSelectOption value="admin">Admin</AppSelectOption>
                    <AppSelectOption value="petugas">Petugas</AppSelectOption>
                </AppSelect>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    class="min-h-11 flex-1 rounded-xl"
                    :disabled="loading"
                    ><Search aria-hidden="true" />Tampilkan</Button
                ><Button
                    type="button"
                    variant="outline"
                    class="min-h-11 rounded-xl"
                    :disabled="loading"
                    @click="reset(defaults)"
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
            {{ users.total }} pengguna{{
                hasFilters ? ' sesuai filter' : ' dalam cakupan Anda'
            }}
        </p>
        <div v-if="users.data.length" class="space-y-3">
            <Link
                v-for="user in users.data"
                :key="user.id"
                :href="`/lainnya/pengguna/${user.id}`"
                class="block rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-secondary dark:text-primary"
                        ><UserRound class="size-5" aria-hidden="true"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm leading-6 font-semibold">
                            {{ user.name }}
                        </h2>
                        <p
                            class="mt-0.5 text-xs leading-5 break-all text-muted-foreground"
                        >
                            {{ user.email }}
                        </p>
                    </div>
                    <ArrowRight
                        class="mt-2 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    <span
                        class="rounded-lg bg-muted px-2 py-1 text-muted-foreground"
                        >{{ ownerReadRole(user.role) }}</span
                    ><span
                        class="rounded-lg px-2 py-1"
                        :class="
                            user.is_active
                                ? 'bg-success/10 text-success'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ user.is_active ? 'Aktif' : 'Nonaktif' }}</span
                    >
                </div>
                <p
                    v-if="user.assigned_farm"
                    class="mt-3 flex items-start gap-2 text-xs leading-5 text-muted-foreground"
                >
                    <MapPin
                        class="mt-0.5 size-3.5 shrink-0"
                        aria-hidden="true"
                    />{{ user.assigned_farm.name }}
                </p>
            </Link>
        </div>
        <OwnerReadEmpty
            v-else
            :title="
                hasFilters ? 'Pengguna tidak ditemukan' : 'Belum ada pengguna'
            "
            :description="
                hasFilters
                    ? 'Coba nama, email, atau role lain.'
                    : 'Pengguna akan tampil sesuai keanggotaan tenant dan izin baca Anda.'
            "
        />
        <OwnerPagination :paginator="users" />
    </div>
</template>
