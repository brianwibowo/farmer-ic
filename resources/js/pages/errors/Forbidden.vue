<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, LogOut, ShieldAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useLogout } from '@/composables/useLogout';
import { home, login } from '@/routes';

defineProps<{ status: number; canLogout: boolean }>();
const { signOut, processing, error } = useLogout();
</script>

<template>
    <Head title="Akses tidak diizinkan" />
    <main class="flex min-h-dvh items-center justify-center bg-background px-4 py-10 text-foreground sm:px-6">
        <section class="w-full max-w-lg rounded-xl border border-border bg-card p-6 text-center shadow-sm sm:p-10" aria-labelledby="forbidden-heading">
            <img src="/images/logo-dashboard.svg" alt="Farm IC" class="mx-auto mb-8 h-9 w-auto dark:brightness-0 dark:invert" />
            <div class="mx-auto flex size-16 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <ShieldAlert class="size-8" aria-hidden="true" />
            </div>
            <p class="mt-6 text-xs font-semibold tracking-widest text-muted-foreground">{{ status }} · AKSES DITOLAK</p>
            <h1 id="forbidden-heading" class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Akses tidak diizinkan</h1>
            <p class="mt-4 text-sm leading-6 text-muted-foreground">Akun kamu belum memiliki izin untuk membuka halaman ini. Hubungi admin tim jika kamu memerlukan akses.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <Button as-child variant="secondary" class="min-h-11 rounded-xl"><Link :href="home()"><ArrowLeft class="size-4" />Ke beranda</Link></Button>
                <Button v-if="canLogout" variant="outline" class="min-h-11 rounded-xl" :disabled="processing" @click="signOut">
                    <LoaderCircle v-if="processing" class="size-4 animate-spin" aria-hidden="true" /><LogOut v-else class="size-4" aria-hidden="true" />
                    {{ processing ? 'Sedang keluar…' : 'Keluar' }}
                </Button>
                <Button v-else as-child variant="outline" class="min-h-11 rounded-xl"><Link :href="login()">Masuk ke akun</Link></Button>
            </div>
            <p v-if="error" role="alert" class="mt-4 text-sm leading-6 text-error">{{ error }}</p>
        </section>
    </main>
</template>
