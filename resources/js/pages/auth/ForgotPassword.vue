<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, Mail } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        variant: 'login',
        title: 'Forgot your password?',
        description:
            "Enter your email and we'll send you a link to reset your password.",
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-3 rounded-xl border border-success/30 bg-success/10 px-4 py-3"
    >
        <CircleCheck
            aria-hidden="true"
            class="mt-0.5 size-5 shrink-0 text-success"
        />
        <div class="space-y-1">
            <p class="text-sm font-semibold text-foreground">
                Check your inbox
            </p>
            <p class="text-sm leading-5 text-muted-foreground">{{ status }}</p>
        </div>
    </div>

    <Form
        v-bind="email.form()"
        v-slot="{ errors, processing }"
        class="space-y-6"
    >
        <div class="space-y-2" :aria-busy="processing">
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
                    autocomplete="email"
                    autocapitalize="none"
                    :spellcheck="false"
                    v-focus
                    placeholder="Enter your email"
                    class="h-12 rounded-xl bg-background pl-10 text-base shadow-none md:text-sm"
                    :aria-invalid="Boolean(errors.email)"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                />
            </div>
            <InputError
                id="email-error"
                :message="errors.email"
                role="alert"
                class="[&_p]:text-error"
            />
        </div>

        <Button
            type="submit"
            class="h-12 w-full rounded-xl bg-secondary text-base font-semibold text-secondary-foreground hover:bg-secondary/90 active:bg-secondary/80"
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <Spinner v-if="processing" aria-hidden="true" />
            {{ processing ? 'Sending link…' : 'Send reset link' }}
        </Button>
    </Form>

    <div class="mt-6 flex justify-center">
        <Link
            :href="login()"
            class="inline-flex items-center gap-2 rounded-sm text-sm font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring dark:text-primary"
        >
            <ArrowLeft aria-hidden="true" class="size-4" />
            Back to log in
        </Link>
    </div>
</template>
