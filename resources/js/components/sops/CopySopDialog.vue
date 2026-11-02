<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Copy, LoaderCircle } from '@lucide/vue';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SopFarm, SopVersion } from '@/types/sop';

const props = defineProps<{
    open: boolean;
    version: SopVersion;
    farms: SopFarm[];
}>();
const emit = defineEmits<{ 'update:open': [boolean] }>();

const form = useForm({ target_farm_ids: [] as string[] });

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.clearErrors();
            form.target_farm_ids = [];
        }
    },
);

function toggleFarm(id: string): void {
    form.target_farm_ids = form.target_farm_ids.includes(id)
        ? form.target_farm_ids.filter((farmId) => farmId !== id)
        : [...form.target_farm_ids, id];
}

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    form.post(
        `/admin/sops/${props.version.sop_template_id}/versions/${props.version.id}/copy`,
        {
            preserveScroll: true,
            onSuccess: () => close(),
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="admin-dashboard-theme sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Salin SOP ke kebun lain</DialogTitle>
                <DialogDescription>
                    Salinan dibuat sebagai versi draft baru dan tidak langsung diterapkan ke batch yang sedang berjalan.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="rounded-xl border border-border bg-muted/40 p-4 text-sm">
                    <p class="font-medium">Sumber</p>
                    <p class="mt-1 text-muted-foreground">
                        Versi {{ version.version }} · {{ version.tasks.length }} langkah
                    </p>
                </div>

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">Kebun tujuan</legend>
                    <p v-if="!farms.length" class="text-sm text-muted-foreground">
                        Belum ada kebun lain di tenant ini.
                    </p>
                    <label
                        v-for="farm in farms"
                        :key="farm.id"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-border p-3 text-sm hover:bg-muted/40"
                    >
                        <input
                            type="checkbox"
                            class="size-4 accent-primary"
                            :value="farm.id"
                            :checked="form.target_farm_ids.includes(farm.id)"
                            @change="toggleFarm(farm.id)"
                        />
                        {{ farm.name }}
                    </label>
                    <p v-if="form.errors.target_farm_ids" role="alert" class="text-sm text-error">
                        {{ form.errors.target_farm_ids }}
                    </p>
                </fieldset>

                <p class="rounded-xl bg-accent p-3 text-xs leading-5 text-accent-foreground">
                    Salinan bersifat independen. Perubahan pada sumber tidak otomatis memperbarui kebun tujuan.
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="close">Batal</Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.target_farm_ids.length"
                    >
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        <Copy v-else />Salin ke {{ form.target_farm_ids.length || 0 }} kebun
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
