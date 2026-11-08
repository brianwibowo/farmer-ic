<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { LockKeyhole, Mail } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
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
        title: 'Log in to your account',
        description: 'Welcome back! Please enter your details.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();
</script>

<template>
    <Head title="Log in" />

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

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="space-y-6"
    >
        <div class="space-y-5" :aria-busy="processing">
            <div class="space-y-2">
                <Label for="email" class="text-sm font-medium">Email</Label>
                <div class="relative">
                    <Mail
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        v-focus
                        autocomplete="email"
                        autocapitalize="none"
                        :spellcheck="false"
                        placeholder="Enter your email"
                        class="h-12 rounded-xl bg-background pl-10 text-base shadow-none md:text-sm"
                        :aria-invalid="Boolean(errors.email)"
                        :aria-describedby="
                            errors.email ? 'email-error' : undefined
                        "
                    />
                </div>
                <InputError
                    id="email-error"
                    :message="errors.email"
                    role="alert"
                    class="[&_p]:text-error"
                />
            </div>

            <div class="space-y-2">
                <Label for="password" class="text-sm font-medium"
                    >Password</Label
                >
                <div class="relative">
                    <LockKeyhole
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        :toggle-tabindex="0"
                        placeholder="Enter your password"
                        class="h-12 rounded-xl bg-background pl-10 text-base shadow-none md:text-sm"
                        :aria-invalid="Boolean(errors.password)"
                        :aria-describedby="
                            errors.password ? 'password-error' : undefined
                        "
                    />
                </div>
                <InputError
                    id="password-error"
                    :message="errors.password"
                    role="alert"
                    class="[&_p]:text-error"
                />
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 pt-1"
            >
                <Label
                    for="remember"
                    class="flex cursor-pointer items-center gap-2.5"
                >
                    <Checkbox id="remember" name="remember" />
                    <span class="text-sm font-normal text-muted-foreground"
                        >Remember me</span
                    >
                </Label>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="rounded-sm text-sm font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring dark:text-primary"
                >
                    Forgot password?
                </Link>
            </div>
        </div>
        <Button
            type="submit"
            class="h-12 w-full rounded-xl bg-secondary text-base font-semibold text-secondary-foreground hover:bg-secondary/90 active:bg-secondary/80"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" aria-hidden="true" />
            {{ processing ? 'Logging in…' : 'Log in' }}
        </Button>
    </Form>
</template>
