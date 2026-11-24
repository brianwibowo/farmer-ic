<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { CircleAlert, LockKeyhole, Mail } from '@lucide/vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        variant: 'login',
        title: 'Masuk ke akun Anda',
        description: 'Selamat datang kembali! Silakan masukkan data Anda.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();
const formElement = ref<HTMLFormElement>();
const form = useForm<{
    email: string;
    password: string;
    remember: boolean;
    login?: string;
}>({ email: '', password: '', remember: false });

async function focusError() {
    await nextTick();
    formElement.value
        ?.querySelector<HTMLElement>(
            '[role="alert"], input[aria-invalid="true"]',
        )
        ?.focus();
}

function clearError(field: 'email' | 'password') {
    form.clearErrors(field, 'login');
}

function submit() {
    if (form.processing) return;
    form.clearErrors();
    form.email = form.email.trim();
    const emailInput =
        formElement.value?.querySelector<HTMLInputElement>('#email');

    if (!form.email) {
        form.setError('email', 'Masukkan alamat email Anda.');
    } else if (emailInput?.validity.typeMismatch) {
        form.setError('email', 'Masukkan alamat email yang valid.');
    }
    if (!form.password.trim()) {
        form.setError('password', 'Masukkan kata sandi Anda.');
    }
    if (form.hasErrors) {
        void focusError();
        return;
    }
    form.submit(store(), {
        onError: () => void focusError(),
        onSuccess: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <div
        v-if="status"
        role="status"
        class="mb-6 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-medium text-success"
    >
        {{ status }}
    </div>

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Log in"
        class="mb-6"
    />

    <form
        ref="formElement"
        novalidate
        class="auth-login-form space-y-6"
        @submit.prevent="submit"
    >
        <div
            v-if="form.errors.login"
            role="alert"
            tabindex="-1"
            class="flex gap-3 rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm leading-5 text-error focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-error"
        >
            <CircleAlert aria-hidden="true" class="mt-0.5 size-4 shrink-0" />
            <p>{{ form.errors.login }}</p>
        </div>
        <div class="space-y-5" :aria-busy="form.processing">
            <div class="space-y-2">
                <Label for="email" class="text-sm font-medium">Email</Label>
                <div class="relative">
                    <Mail
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="email"
                        v-model="form.email"
                        @input="clearError('email')"
                        type="email"
                        name="email"
                        required
                        v-focus
                        autocomplete="email"
                        autocapitalize="none"
                        :spellcheck="false"
                        placeholder="Masukkan email Anda"
                        class="h-12 rounded-xl bg-background pl-10 text-base shadow-none md:text-sm"
                        :aria-invalid="Boolean(form.errors.email)"
                        :aria-describedby="
                            form.errors.email ? 'email-error' : undefined
                        "
                    />
                </div>
                <p
                    v-if="form.errors.email"
                    id="email-error"
                    class="text-sm text-error"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="password" class="text-sm font-medium"
                    >Kata sandi</Label
                >
                <div class="relative">
                    <LockKeyhole
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        @input="clearError('password')"
                        name="password"
                        required
                        autocomplete="current-password"
                        :toggle-tabindex="0"
                        placeholder="Masukkan kata sandi Anda"
                        class="h-12 rounded-xl bg-background pl-10 text-base shadow-none md:text-sm"
                        :aria-invalid="Boolean(form.errors.password)"
                        :aria-describedby="
                            form.errors.password ? 'password-error' : undefined
                        "
                    />
                </div>
                <p
                    v-if="form.errors.password"
                    id="password-error"
                    class="text-sm text-error"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 pt-1"
            >
                <Label
                    for="remember"
                    class="flex cursor-pointer items-center gap-2.5"
                >
                    <Checkbox
                        id="remember"
                        v-model="form.remember"
                        name="remember"
                    />
                    <span class="text-sm font-normal text-muted-foreground"
                        >Ingat login saya</span
                    >
                </Label>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="rounded-sm text-sm font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring dark:text-primary"
                >
                    Lupa kata sandi?
                </Link>
            </div>
        </div>
        <Button
            type="submit"
            class="h-12 w-full rounded-xl bg-secondary text-base font-semibold text-secondary-foreground hover:bg-secondary/90 active:bg-secondary/80"
            :disabled="form.processing"
            data-test="login-button"
        >
            <Spinner v-if="form.processing" aria-hidden="true" />
            {{ form.processing ? 'Sedang masuk…' : 'Masuk' }}
        </Button>
    </form>
</template>
