<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import BlockDialog from './BlockDialog.vue';
import BlockBatches from './BlockBatches.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {
    BlockFarm,
    BlockFormCapacity,
    ManagedBlock,
} from '@/types/blocks';
import { hectares } from '@/types/farms';
const props = withDefaults(
    defineProps<{
        farm: BlockFarm;
        blocks: ManagedBlock[];
        capacity: BlockFormCapacity | null;
        canCreate: boolean;
        theme?: string;
        returnTo?: 'farm' | 'blocks';
        batchPermissions: Record<string, boolean>;
        harvestPermissions: Record<string, boolean>;
        clientPagination?: boolean;
        searchable?: boolean;
        total?: number;
    }>(),
    { clientPagination: true, searchable: true },
);
const editing = ref(false);
const selected = ref<ManagedBlock | null>(null);
const deleting = ref<ManagedBlock | null>(null);
const search = ref('');
const currentPage = ref(1);
const filtered = computed(() =>
    props.blocks.filter((block) =>
        `${block.code} ${block.name}`
            .toLocaleLowerCase('id')
            .includes(search.value.trim().toLocaleLowerCase('id')),
    ),
);
const lastPage = computed(() =>
    props.clientPagination
        ? Math.max(1, Math.ceil(filtered.value.length / 10))
        : 1,
);
const rows = computed(() =>
    props.clientPagination
        ? filtered.value.slice(
              (currentPage.value - 1) * 10,
              currentPage.value * 10,
          )
        : filtered.value,
);
watch(search, () => (currentPage.value = 1));
watch(
    lastPage,
    (value) => (currentPage.value = Math.min(currentPage.value, value)),
);
const form = useForm({ return_to: props.returnTo ?? '' });
const error = computed(() => (form.errors as Record<string, string>).delete);
function open(block: ManagedBlock | null): void {
    selected.value = block;
    editing.value = true;
}
function remove(): void {
    if (!deleting.value) return;
    form.delete(`/kebun/${props.farm.id}/blocks/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (deleting.value = null),
    });
}
</script>
<template>
    <section class="overflow-hidden rounded-xl bg-card">
        <header
            class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6"
        >
            <div>
                <h2 class="admin-section-title text-primary">
                    Blok kebun
                    <span
                        class="ml-1 text-sm font-normal text-muted-foreground"
                        >{{ total ?? blocks.length }}</span
                    >
                </h2>
            </div>
            <Button v-if="canCreate" class="block-action" @click="open(null)"
                ><Plus />Tambah blok</Button
            >
        </header>
        <div v-if="searchable && blocks.length > 5" class="px-5 pb-4 sm:px-6">
            <label class="relative block"
                ><span class="sr-only">Cari blok</span
                ><Search
                    class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" /><input
                    v-model="search"
                    class="admin-field block-field pl-10"
                    type="search"
                    placeholder="Cari nama atau kode blok"
            /></label>
        </div>
        <div v-if="blocks.length" class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    Daftar blok
                    {{
                        farm.name
                    }}
                </caption>
                <thead
                    class="border-y border-border bg-muted/50 text-xs text-muted-foreground"
                >
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium sm:px-6">
                            Blok
                        </th>
                        <th scope="col" class="px-3 py-3 font-medium">Luas</th>
                        <th
                            scope="col"
                            class="hidden px-3 py-3 font-medium sm:table-cell"
                        >
                            Status
                        </th>
                        <th
                            scope="col"
                            class="px-5 py-3 text-right font-medium sm:px-6"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody
                    v-for="block in rows"
                    :key="block.id"
                    class="border-b-[12px] border-background last:border-b-0"
                >
                    <tr class="bg-primary/8">
                        <td class="px-5 py-4 sm:px-6">
                            <h3
                                class="text-base font-bold text-primary sm:text-lg"
                            >
                                {{ block.name }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ block.code
                                }}<span class="sm:hidden">
                                    ·
                                    {{
                                        block.is_active ? 'Aktif' : 'Nonaktif'
                                    }}</span
                                >
                            </p>
                        </td>
                        <td
                            class="px-3 py-4 font-bold whitespace-nowrap tabular-nums"
                        >
                            {{ hectares(block.area_ha) }} ha
                        </td>
                        <td class="hidden px-3 py-4 sm:table-cell">
                            <span
                                :class="
                                    block.is_active
                                        ? 'text-success'
                                        : 'text-muted-foreground'
                                "
                                >{{
                                    block.is_active ? 'Aktif' : 'Nonaktif'
                                }}</span
                            >
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex justify-end gap-1">
                                <Button
                                    v-if="block.can.update"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Ubah ${block.name}`"
                                    @click="open(block)"
                                    ><Pencil class="size-4" /></Button
                                ><Button
                                    v-if="block.can.delete"
                                    variant="destructive-ghost"
                                    size="icon"
                                    :aria-label="`Hapus ${block.name}`"
                                    @click="
                                        deleting = block;
                                        form.clearErrors();
                                    "
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="px-5 py-6 sm:px-6 sm:py-7">
                            <BlockBatches
                                :farm="farm"
                                :block="block"
                                :batch-permissions="batchPermissions"
                                :harvest-permissions="harvestPermissions"
                                :theme="theme ?? 'admin-dashboard-theme'"
                                :return-to="returnTo ?? 'blocks'"
                            />
                        </td>
                    </tr>
                </tbody>
                <tbody v-if="!rows.length">
                    <tr>
                        <td
                            colspan="4"
                            class="p-6 text-center text-muted-foreground"
                        >
                            Tidak ada blok yang cocok.
                            <button
                                class="text-primary underline"
                                @click="search = ''"
                            >
                                Hapus pencarian
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="px-5 py-10 text-center">
            <p class="font-medium">Belum ada blok</p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    canCreate
                        ? 'Tambahkan blok untuk membagi lahan kebun.'
                        : 'Blok akan tampil setelah ditambahkan.'
                }}
            </p>
        </div>
        <footer
            v-if="lastPage > 1"
            class="flex items-center justify-between gap-3 border-t border-border p-4 text-sm"
        >
            <p class="text-muted-foreground">
                {{ currentPage }} / {{ lastPage }} · {{ filtered.length }} blok
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    :disabled="currentPage === 1"
                    @click="currentPage--"
                    >Sebelumnya</Button
                ><Button
                    variant="outline"
                    :disabled="currentPage === lastPage"
                    @click="currentPage++"
                    >Berikutnya</Button
                >
            </div>
        </footer>
    </section>
    <BlockDialog
        v-if="selected?.capacity || capacity"
        v-model:open="editing"
        :farm="farm"
        :block="selected"
        :capacity="selected?.capacity ?? capacity!"
        :theme="theme ?? 'admin-dashboard-theme'"
        :return-to="returnTo"
    />
    <Dialog
        :open="!!deleting"
        @update:open="
            (value) => {
                if (!value && !form.processing) deleting = null;
            }
        "
    >
        <DialogContent
            :class="theme ?? 'admin-dashboard-theme'"
            :show-close-button="!form.processing"
            @interact-outside.prevent
            @escape-key-down="
                (event) => {
                    if (form.processing) event.preventDefault();
                }
            "
        >
            <DialogHeader
                ><DialogTitle>Hapus {{ deleting?.name }}?</DialogTitle
                ><DialogDescription>{{
                    deleting?.deletionBlocked
                        ? 'Blok memiliki histori. Gunakan Ubah blok untuk menonaktifkannya setelah seluruh sub blok selesai.'
                        : 'Blok akan dihapus dari kebun. Pastikan blok ini sudah tidak diperlukan.'
                }}</DialogDescription></DialogHeader
            >
            <p v-if="error" role="alert" class="text-sm text-error">
                {{ error }}
            </p>
            <DialogFooter
                ><Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="deleting = null"
                    >Batal</Button
                ><Button
                    v-if="!deleting?.deletionBlocked"
                    variant="destructive"
                    :disabled="form.processing"
                    @click="remove"
                    ><LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    />{{
                        form.processing ? 'Menghapus…' : 'Hapus blok'
                    }}</Button
                ></DialogFooter
            >
        </DialogContent>
    </Dialog>
</template>
