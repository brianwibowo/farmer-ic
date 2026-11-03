<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Save } from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    passwordRules: string;
    returnUrl: string;
}>();
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-5 px-4 py-5">
        <Link
            :href="props.returnUrl"
            class="inline-flex min-h-11 w-fit items-center gap-2 rounded-xl px-1 text-sm font-medium text-muted-foreground transition-colors hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Kembali ke profil
        </Link>

        <header class="px-1 pt-1">
            <h1 class="text-xl leading-7 font-bold text-foreground">
                Keamanan akun
            </h1>
            <p class="mt-1 text-sm leading-5 text-muted-foreground/70">
                Perbarui kata sandi untuk menjaga akun Anda.
            </p>
        </header>

        <section class="rounded-xl border border-border bg-card p-4 shadow-sm">
            <Form
                v-bind="SecurityController.update.form()"
                v-slot="{ errors, processing }"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="['password', 'password_confirmation', 'current_password']"
                class="space-y-4"
            >
                <div class="grid gap-1.5">
                    <Label for="current_password">Kata sandi saat ini</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="mobile-security-field"
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi saat ini"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password">Kata sandi baru</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        class="mobile-security-field"
                        autocomplete="new-password"
                        placeholder="Masukkan kata sandi baru"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password_confirmation">Konfirmasi kata sandi baru</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        class="mobile-security-field"
                        autocomplete="new-password"
                        placeholder="Ulangi kata sandi baru"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <button
                    type="submit"
                    :disabled="processing"
                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-secondary disabled:cursor-not-allowed disabled:bg-disable disabled:text-disable-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    data-test="update-password-button"
                >
                    <LoaderCircle v-if="processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" aria-hidden="true" />
                    {{ processing ? 'Menyimpan...' : 'Simpan kata sandi' }}
                </button>
            </Form>
        </section>
    </div>
</template>

<style scoped>
.mobile-security-field {
    min-height: 3rem;
    border-color: var(--border);
    border-radius: 0.75rem;
    background: var(--background);
    font-size: 1rem;
}

.mobile-security-field:hover:not(:disabled) {
    border-color: var(--primary);
    background: var(--card);
}

.mobile-security-field:focus-visible {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 30%, transparent);
}
</style>
