<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import FinanceNavigation from '@/components/farmer/FinanceNavigation.vue';
import FinanceRecordForm from '@/components/farmer/FinanceRecordForm.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { financeRecordTitle, formatFinanceMoney } from '@/lib/finance';
import type {
    FinanceOptions,
    FinancePaginator,
    FinanceRecord,
    FinanceResource,
} from '@/types/finance';

const props = defineProps<{
    resource: FinanceResource;
    title: string;
    records: FinancePaginator<FinanceRecord>;
    filters: { search?: string; transaction_type?: string };
    options: FinanceOptions;
    can: { create: boolean };
}>();
const search = ref(props.filters.search ?? '');
const loading = ref(false);
const error = ref('');
const formOpen = ref(false);
const editing = ref<FinanceRecord | null>(null);
const deleting = ref<FinanceRecord | null>(null);
const deleteBusy = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

function openForm(record: FinanceRecord | null): void {
    editing.value = record;
    formOpen.value = true;
}
function load(page = 1): void {
    error.value = '';
    router.get(
        `/finance/${props.resource}`,
        { ...props.filters, search: search.value.trim() || undefined, page },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                loading.value = true;
            },
            onError: () => {
                error.value =
                    'Data gagal dimuat. Periksa pencarian lalu coba lagi.';
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => load(), 300);
});
onBeforeUnmount(() => clearTimeout(timer));
function remove(): void {
    if (!deleting.value) return;
    error.value = '';
    router.delete(`/finance/${props.resource}/${deleting.value.id}`, {
        preserveScroll: true,
        onStart: () => {
            deleteBusy.value = true;
        },
        onSuccess: () => {
            deleting.value = null;
        },
        onError: (errors) => {
            error.value =
                Object.values(errors).join(' ') || 'Data gagal dihapus.';
        },
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
}
</script>

<template>
    <Head :title="title" />
    <FarmerMobileShell active-key="finance">
        <div class="space-y-5 px-4 py-4">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-primary">Keuangan</p>
                    <h1 class="mt-1 text-2xl font-semibold text-foreground">
                        {{ title }}
                    </h1>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Data tersimpan dalam tim aktif Anda.
                    </p>
                </div>
                <Button v-if="can.create" size="sm" @click="openForm(null)"
                    >Tambah</Button
                >
            </header>
            <FinanceNavigation :active="resource" />
            <p
                v-if="error"
                role="alert"
                class="rounded-xl border border-error bg-error/10 p-3 text-sm text-error"
            >
                {{ error }}
            </p>
            <label
                v-if="resource === 'categories' || resource === 'transactions'"
                class="block space-y-1.5"
                ><span class="text-sm text-muted-foreground"
                    >Cari {{ title.toLowerCase() }}</span
                ><Input
                    v-model="search"
                    type="search"
                    maxlength="150"
                    placeholder="Ketik kata kunci…"
            /></label>
            <div
                v-if="loading"
                role="status"
                aria-label="Memuat data"
                class="space-y-3"
            >
                <div
                    v-for="n in 3"
                    :key="n"
                    class="h-28 animate-pulse rounded-xl bg-muted"
                />
            </div>
            <template v-else>
                <p class="text-xs text-muted-foreground">
                    {{ records.total }} {{ title.toLowerCase() }}
                </p>
                <ul v-if="records.data.length" class="space-y-3">
                    <li
                        v-for="record in records.data"
                        :key="record.id"
                        class="space-y-3 rounded-xl border border-border bg-card p-4"
                    >
                        <Link
                            :href="`/finance/${resource}/${record.id}`"
                            class="block font-semibold text-foreground underline-offset-4 hover:underline"
                            >{{ financeRecordTitle(record, resource) }}</Link
                        >
                        <p
                            v-if="resource === 'transactions'"
                            class="text-xs text-muted-foreground"
                        >
                            {{ record.transaction_date }} ·
                            {{
                                record.transaction_type === 'income'
                                    ? 'Pemasukan'
                                    : 'Pengeluaran'
                            }}
                        </p>
                        <p
                            v-if="resource === 'categories'"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                record.transaction_type === 'income'
                                    ? 'Pemasukan'
                                    : 'Pengeluaran'
                            }}
                        </p>
                        <p
                            v-if="
                                record.amount !== undefined ||
                                record.budget_amount !== undefined
                            "
                            class="text-lg font-semibold"
                            :class="
                                record.transaction_type === 'expense'
                                    ? 'text-error'
                                    : 'text-success'
                            "
                        >
                            {{
                                formatFinanceMoney(
                                    record.amount ?? record.budget_amount,
                                )
                            }}
                        </p>
                        <div
                            v-if="resource === 'projections'"
                            class="space-y-1 text-sm"
                        >
                            <p class="text-success">
                                Pemasukan:
                                {{
                                    formatFinanceMoney(record.projected_income)
                                }}
                            </p>
                            <p class="text-error">
                                Pengeluaran:
                                {{
                                    formatFinanceMoney(record.projected_expense)
                                }}
                            </p>
                        </div>
                        <div
                            v-if="record.can?.update || record.can?.delete"
                            class="flex gap-2"
                        >
                            <Button
                                v-if="record.can?.update"
                                size="sm"
                                variant="outline"
                                @click="openForm(record)"
                                >Ubah</Button
                            ><Button
                                v-if="record.can?.delete"
                                size="sm"
                                variant="outline"
                                class="text-error"
                                @click="deleting = record"
                                >Hapus</Button
                            >
                        </div>
                    </li>
                </ul>
                <div
                    v-else
                    class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
                >
                    Belum ada {{ title.toLowerCase() }} yang sesuai dengan
                    pencarian dan cakupan akses Anda.
                </div>
                <nav
                    v-if="records.last_page > 1"
                    aria-label="Halaman data keuangan"
                    class="flex items-center justify-between gap-2 text-xs"
                >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="records.current_page === 1"
                        @click="load(records.current_page - 1)"
                        >Sebelumnya</Button
                    ><span aria-current="page"
                        >{{ records.current_page }} /
                        {{ records.last_page }}</span
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="records.current_page === records.last_page"
                        @click="load(records.current_page + 1)"
                        >Berikutnya</Button
                    >
                </nav>
            </template>
            <FinanceRecordForm
                v-model:open="formOpen"
                :record="editing"
                :resource="resource"
                :options="options"
            />
            <Dialog
                :open="deleting !== null"
                @update:open="
                    (open) => {
                        if (!open) deleting = null;
                    }
                "
                ><DialogContent
                    ><DialogHeader
                        ><DialogTitle
                            >Hapus {{ title.toLowerCase() }}?</DialogTitle
                        ><DialogDescription
                            >{{
                                deleting
                                    ? financeRecordTitle(deleting, resource)
                                    : ''
                            }}
                            akan dihapus setelah Anda
                            mengonfirmasi.</DialogDescription
                        ></DialogHeader
                    >
                    <p v-if="error" role="alert" class="text-sm text-error">
                        {{ error }}
                    </p>
                    <DialogFooter
                        ><Button
                            variant="outline"
                            :disabled="deleteBusy"
                            @click="deleting = null"
                            >Batal</Button
                        ><Button
                            variant="destructive"
                            :disabled="deleteBusy"
                            @click="remove"
                            >{{ deleteBusy ? 'Menghapus…' : 'Hapus' }}</Button
                        ></DialogFooter
                    ></DialogContent
                ></Dialog
            >
        </div>
    </FarmerMobileShell>
</template>
