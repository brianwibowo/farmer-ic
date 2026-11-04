<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, MapPin, UserRound } from '@lucide/vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import { ownerReadRole, type OwnerUserDetail } from '@/types/owner-secondary';

defineProps<{ managedUser: OwnerUserDetail }>();
</script>

<template>
    <Head :title="managedUser.name" />
    <div class="space-y-5">
        <OwnerPageHeader
            title="Rincian pengguna"
            back-href="/lainnya/pengguna"
            back-label="Data pengguna"
        />
        <section class="rounded-xl border border-border bg-card p-4">
            <span
                class="mb-4 flex size-14 items-center justify-center rounded-full bg-primary/10 text-secondary dark:text-primary"
                ><UserRound class="size-7" aria-hidden="true"
            /></span>
            <h2 class="text-lg leading-7 font-semibold">
                {{ managedUser.name }}
            </h2>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                <span
                    class="rounded-lg bg-muted px-2 py-1 text-muted-foreground"
                    >{{ ownerReadRole(managedUser.role) }}</span
                ><span
                    class="rounded-lg px-2 py-1"
                    :class="
                        managedUser.is_active
                            ? 'bg-success/10 text-success'
                            : 'bg-muted text-muted-foreground'
                    "
                    >{{ managedUser.is_active ? 'Aktif' : 'Nonaktif' }}</span
                >
            </div>
            <dl class="mt-5 space-y-4 border-t border-border pt-4">
                <div>
                    <dt class="text-xs text-muted-foreground">Email</dt>
                    <dd class="mt-1 text-sm leading-6 break-all">
                        {{ managedUser.email }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Nomor WhatsApp
                    </dt>
                    <dd class="mt-1 text-sm leading-6">
                        {{ managedUser.phone_number || 'Belum tercatat' }}
                    </dd>
                </div>
            </dl>
        </section>
        <section class="rounded-xl border border-border bg-card p-4">
            <h2 class="mb-3 text-base font-semibold">Keanggotaan tenant</h2>
            <ul v-if="managedUser.teams.length" class="space-y-2">
                <li
                    v-for="team in managedUser.teams"
                    :key="team.id"
                    class="rounded-lg bg-muted px-3 py-3 text-sm"
                >
                    {{ team.name }}
                </li>
            </ul>
            <p v-else class="text-sm leading-6 text-muted-foreground">
                Tidak ada keanggotaan tenant yang dapat ditampilkan.
            </p>
        </section>
        <section class="space-y-3">
            <h2 class="text-base font-semibold">Penugasan kebun</h2>
            <Link
                v-if="managedUser.assigned_farm"
                :href="`/kebun/${managedUser.assigned_farm.id}`"
                class="flex min-h-16 items-center gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                ><MapPin
                    class="size-5 shrink-0 text-primary"
                    aria-hidden="true" /><span
                    class="min-w-0 flex-1 text-sm font-medium"
                    >{{ managedUser.assigned_farm.name }}</span
                ><ArrowRight
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
            /></Link>
            <p
                v-else
                class="rounded-xl border border-dashed border-border p-4 text-sm leading-6 text-muted-foreground"
            >
                Tidak ada penugasan kebun yang dapat ditampilkan.
            </p>
        </section>
    </div>
</template>
