<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronRight,
    LoaderCircle,
    LockKeyhole,
    Pencil,
    Save,
} from '@lucide/vue';
import { ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { edit as editSecurity } from '@/routes/security';

const props = defineProps<{
    returnUrl: string;
    profile: {
        name: string;
        email: string;
        phone_number: string | null;
        avatar: string | null;
    };
}>();

const isEditing = ref(false);
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-5 px-4 py-5">
        <Link
            :href="props.returnUrl"
            class="inline-flex min-h-11 w-fit items-center gap-2 rounded-xl px-1 text-sm font-medium text-muted-foreground transition-colors hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Kembali ke beranda
        </Link>

        <header class="flex flex-col items-center px-4 pt-2 pb-1 text-center">
            <Avatar class="size-20 rounded-full border-2 border-primary/20 shadow-sm">
                <AvatarImage
                    v-if="props.profile.avatar"
                    :src="props.profile.avatar"
                    :alt="props.profile.name"
                />
                <AvatarFallback class="bg-primary/15 text-xl font-bold text-secondary">
                    {{ getInitials(props.profile.name) }}
                </AvatarFallback>
            </Avatar>
            <h1 class="mt-3 text-[22px] leading-7 font-bold text-foreground">
                {{ props.profile.name }}
            </h1>
            <p class="mt-1 max-w-full truncate text-sm leading-5 text-muted-foreground/70">
                {{ props.profile.email }}
            </p>
            <button
                v-if="!isEditing"
                type="button"
                class="mt-4 inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="isEditing = true"
            >
                <Pencil class="size-4" aria-hidden="true" />
                Edit profil
            </button>
        </header>

        <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                <p class="text-sm leading-5 text-foreground">
                    Informasi akun
                </p>
                <button
                    v-if="isEditing"
                    type="button"
                    class="inline-flex min-h-10 shrink-0 items-center rounded-xl px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="isEditing = false"
                >
                    Batal
                </button>
            </div>

            <dl v-if="!isEditing" class="border-t border-border/50 text-sm">
                <div class="flex items-start justify-between gap-4 px-4 py-3.5">
                    <dt class="text-muted-foreground/70">Nama lengkap</dt>
                    <dd class="max-w-[62%] text-right font-medium text-foreground">
                        {{ props.profile.name }}
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-4 border-t border-border/50 px-4 py-3.5">
                    <dt class="text-muted-foreground/70">Email</dt>
                    <dd class="max-w-[62%] break-all text-right font-medium text-foreground">
                        {{ props.profile.email }}
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-4 border-t border-border/50 px-4 py-3.5">
                    <dt class="text-muted-foreground/70">WhatsApp</dt>
                    <dd
                        class="max-w-[62%] text-right font-medium"
                        :class="props.profile.phone_number ? 'text-foreground' : 'text-muted-foreground/70'"
                    >
                        {{ props.profile.phone_number || 'Belum ditambahkan' }}
                    </dd>
                </div>
            </dl>

            <Form
                v-else
                v-bind="ProfileController.update.form()"
                v-slot="{ errors, processing }"
                class="space-y-4 border-t border-border px-4 py-4"
                @success="isEditing = false"
            >
                <div class="grid gap-1.5">
                    <Label for="name">Nama lengkap</Label>
                    <Input
                        id="name"
                        name="name"
                        class="mobile-profile-field"
                        :default-value="props.profile.name"
                        required
                        autocomplete="name"
                        placeholder="Masukkan nama lengkap"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="email">Alamat email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        class="mobile-profile-field"
                        :default-value="props.profile.email"
                        required
                        autocomplete="username"
                        placeholder="contoh@email.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="phone_number">Nomor WhatsApp</Label>
                    <Input
                        id="phone_number"
                        name="phone_number"
                        type="tel"
                        class="mobile-profile-field"
                        autocomplete="tel"
                        inputmode="tel"
                        :default-value="props.profile.phone_number ?? ''"
                        placeholder="081234567890"
                        pattern="\+?[0-9][0-9 \(\)\-]{7,23}"
                        maxlength="24"
                        aria-describedby="phone-help"
                    />
                    <p id="phone-help" class="text-xs leading-4 text-muted-foreground/60">
                        Opsional. Nomor 08... atau +628... disimpan sebagai 628....
                    </p>
                    <InputError :message="errors.phone_number" />
                </div>

                <button
                    type="submit"
                    :disabled="processing"
                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-secondary disabled:cursor-not-allowed disabled:bg-disable disabled:text-disable-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    data-test="update-profile-button"
                >
                    <LoaderCircle v-if="processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" aria-hidden="true" />
                    {{ processing ? 'Menyimpan...' : 'Simpan perubahan' }}
                </button>
            </Form>
        </section>

        <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <Link
                :href="editSecurity()"
                class="flex min-h-12 items-center gap-2.5 px-4 py-2.5 text-left transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <LockKeyhole class="size-4 shrink-0 text-muted-foreground/70" aria-hidden="true" />
                <span class="min-w-0 flex-1 text-sm font-medium text-foreground">
                    Ubah kata sandi
                </span>
                <ChevronRight class="size-4 shrink-0 text-muted-foreground/60" aria-hidden="true" />
            </Link>
        </section>

        <DeleteUser mobile />
    </div>
</template>

<style scoped>
.mobile-profile-field {
    min-height: 3rem;
    border-color: var(--border);
    border-radius: 0.75rem;
    background: var(--background);
    font-size: 1rem;
}

.mobile-profile-field:hover:not(:disabled) {
    border-color: var(--primary);
    background: var(--card);
}

.mobile-profile-field:focus-visible {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 30%, transparent);
}
</style>
