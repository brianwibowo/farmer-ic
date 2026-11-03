<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-xl border border-border bg-card px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-6"
    >
        <div class="min-w-0">
            <h2 class="admin-section-title">Hapus akun</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Hapus akun Anda beserta seluruh data yang terkait.
            </p>
        </div>

        <Dialog>
            <DialogTrigger as-child>
                <Button
                    variant="destructive"
                    class="shrink-0"
                    data-test="delete-user-button"
                >
                    Hapus akun
                </Button>
            </DialogTrigger>
            <DialogContent class="admin-dashboard-theme">
                <Form
                    v-bind="ProfileController.destroy.form()"
                    reset-on-success
                    @error="() => passwordInput?.focus()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="space-y-6"
                    v-slot="{ errors, processing, reset, clearErrors }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            Yakin ingin menghapus akun?
                        </DialogTitle>
                        <DialogDescription>
                            Setelah akun dihapus, seluruh data akan dihapus
                            secara permanen. Masukkan kata sandi untuk
                            mengonfirmasi penghapusan akun.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-1.5">
                        <Label for="password" class="sr-only">Kata sandi</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            ref="passwordInput"
                            class="admin-field"
                            placeholder="Masukkan kata sandi"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                "
                            >
                                Batal
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-user-button"
                        >
                            Hapus akun
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
