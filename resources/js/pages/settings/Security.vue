<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import MobileSecurityForm from '@/components/settings/MobileSecurityForm.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/security';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    securityPresentation?: {
        surface?: string;
        returnUrl?: string;
    };
} ;

const props = defineProps<Props>();
const page = usePage();
const presentation = computed(() => ({
    isMobile: props.securityPresentation?.surface === 'mobile',
    returnUrl: props.securityPresentation?.returnUrl ?? '/',
}));
const breadcrumbs = [{ title: 'Keamanan', href: edit() }];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Keamanan',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Keamanan" />

    <FarmerMobileShell v-if="presentation.isMobile" :show-navigation="false">
        <MobileSecurityForm
            :password-rules="props.passwordRules"
            :return-url="presentation.returnUrl"
        />
    </FarmerMobileShell>

    <AppLayout v-else :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <h1 class="sr-only">Keamanan</h1>

            <div class="rounded-xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-5 py-4 sm:px-6">
            <h2 class="admin-section-title">Ubah kata sandi</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Pastikan akun Anda menggunakan kata sandi yang kuat dan unik.
            </p>
        </div>

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-5 px-5 py-5 sm:px-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1.5">
                <Label for="current_password">Kata sandi saat ini</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="admin-field"
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
                    class="admin-field"
                    autocomplete="new-password"
                    placeholder="Masukkan kata sandi baru"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password_confirmation"
                    >Konfirmasi kata sandi baru</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="admin-field"
                    autocomplete="new-password"
                    placeholder="Ulangi kata sandi baru"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-3 pt-1">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="size-4 animate-spin"
                    />
                    <Save v-else class="size-4" />
                    Simpan
                </Button>
            </div>
        </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
