<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { financeLabels } from '@/lib/finance';
import type {
    FinanceOptions,
    FinanceRecord,
    FinanceResource,
} from '@/types/finance';

const props = defineProps<{
    open: boolean;
    record: FinanceRecord | null;
    resource: FinanceResource;
    options: FinanceOptions;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
type Fields = {
    name: string;
    description: string;
    category_id: string;
    planting_cycle_id: string;
    transaction_date: string;
    transaction_type: string;
    amount: string;
    period_start: string;
    period_end: string;
    budget_amount: string;
    projected_income: string;
    projected_expense: string;
};
const form = useForm<Fields>({
    name: '',
    description: '',
    category_id: '',
    planting_cycle_id: '',
    transaction_date: '',
    transaction_type: 'expense',
    amount: '',
    period_start: '',
    period_end: '',
    budget_amount: '',
    projected_income: '',
    projected_expense: '',
});
const farmId = ref('');
const localError = ref('');
const fieldNames: Record<FinanceResource, Array<keyof Fields>> = {
    transactions: [
        'description',
        'category_id',
        'planting_cycle_id',
        'transaction_date',
        'transaction_type',
        'amount',
    ],
    categories: ['name', 'transaction_type'],
    budgets: ['category_id', 'period_start', 'period_end', 'budget_amount'],
    projections: [
        'planting_cycle_id',
        'period_start',
        'period_end',
        'projected_income',
        'projected_expense',
    ],
};
const periods = ['period_start', 'period_end'] as const;
const moneyFields = computed<
    Array<{ key: keyof Fields; label: string; optional: boolean }>
>(() => {
    if (props.resource === 'transactions')
        return [{ key: 'amount', label: 'Nominal (Rp)', optional: false }];
    if (props.resource === 'budgets')
        return [
            { key: 'budget_amount', label: 'Anggaran (Rp)', optional: false },
        ];
    if (props.resource === 'projections')
        return [
            {
                key: 'projected_income',
                label: 'Proyeksi pemasukan (Rp)',
                optional: true,
            },
            {
                key: 'projected_expense',
                label: 'Proyeksi pengeluaran (Rp)',
                optional: true,
            },
        ];
    return [];
});
const categories = computed(() =>
    props.options.categories.filter(
        (category) =>
            props.resource !== 'transactions' ||
            category.transaction_type === form.transaction_type,
    ),
);
const cycles = computed(() =>
    props.options.cycles.filter((cycle) => cycle.farm_id === farmId.value),
);
watch(
    () => [props.open, props.record] as const,
    () => {
        if (!props.open) return;
        form.reset();
        form.clearErrors();
        localError.value = '';
        farmId.value =
            props.record?.farm_id ?? props.options.farms[0]?.id ?? '';
        for (const key of fieldNames[props.resource]) {
            const value = props.record?.[key];
            form[key] =
                typeof value === 'string'
                    ? value
                    : key === 'transaction_type'
                      ? 'expense'
                      : '';
        }
    },
    { immediate: true },
);
watch(farmId, (next, previous) => {
    if (
        next !== previous &&
        !cycles.value.some((cycle) => cycle.id === form.planting_cycle_id)
    )
        form.planting_cycle_id = '';
});
watch(
    () => form.transaction_type,
    () => {
        if (
            props.resource === 'transactions' &&
            !categories.value.some(
                (category) => category.id === form.category_id,
            )
        )
            form.category_id = '';
    },
);

function submit(): void {
    form.clearErrors();
    localError.value = '';
    if (!props.record && props.resource !== 'categories' && !farmId.value) {
        localError.value = 'Pilih kebun terlebih dahulu.';
        return;
    }
    for (const field of moneyFields.value) {
        const value = form[field.key];
        if (
            (!value && !field.optional) ||
            (value && !/^\d{1,16}(?:\.\d{1,2})?$/.test(value)) ||
            (props.resource === 'transactions' && /^0+(?:\.0+)?$/.test(value))
        ) {
            form.setError(
                field.key,
                'Isi nominal yang valid, maksimal dua angka desimal.',
            );
            return;
        }
    }
    if (
        props.resource === 'projections' &&
        !form.projected_income &&
        !form.projected_expense
    ) {
        form.setError(
            'projected_income',
            'Isi sedikitnya satu nilai proyeksi.',
        );
        return;
    }
    if (
        form.period_start &&
        form.period_end &&
        form.period_end < form.period_start
    ) {
        form.setError(
            'period_end',
            'Akhir periode harus setelah atau sama dengan awal periode.',
        );
        return;
    }
    form.transform((data) =>
        Object.fromEntries(
            fieldNames[props.resource].map((key) => [
                key,
                data[key] === '' ? null : data[key],
            ]),
        ),
    );
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };
    if (props.record)
        form.patch(`/finance/${props.resource}/${props.record.id}`, options);
    else
        form.post(
            props.resource === 'categories'
                ? '/finance/categories'
                : `/finance/farms/${farmId.value}/${props.resource}`,
            options,
        );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90dvh] overflow-y-auto">
            <DialogHeader
                ><DialogTitle
                    >{{ record ? 'Ubah' : 'Tambah' }}
                    {{ financeLabels[resource] }}</DialogTitle
                ><DialogDescription
                    >Lengkapi data keuangan. Nominal menggunakan titik sebagai
                    pemisah desimal.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submit">
                <p v-if="localError" role="alert" class="text-sm text-error">
                    {{ localError }}
                </p>
                <div
                    v-if="resource !== 'categories' && !record"
                    class="space-y-1.5"
                >
                    <Label for="finance-farm">Kebun</Label>
                    <select
                        id="finance-farm"
                        v-model="farmId"
                        required
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm text-foreground"
                    >
                        <option value="" disabled>Pilih kebun</option>
                        <option
                            v-for="farm in options.farms"
                            :key="farm.id"
                            :value="farm.id"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                </div>
                <div v-if="resource === 'categories'" class="space-y-1.5">
                    <Label for="finance-name">Nama kategori</Label
                    ><Input
                        id="finance-name"
                        v-model="form.name"
                        required
                        maxlength="100"
                    /><InputError :message="form.errors.name" />
                </div>
                <div
                    v-if="
                        resource === 'transactions' || resource === 'categories'
                    "
                    class="space-y-1.5"
                >
                    <Label for="finance-type">Jenis transaksi</Label
                    ><select
                        id="finance-type"
                        v-model="form.transaction_type"
                        required
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm text-foreground"
                    >
                        <option value="income">Pemasukan</option>
                        <option value="expense">Pengeluaran</option></select
                    ><InputError :message="form.errors.transaction_type" />
                </div>
                <div
                    v-if="resource === 'transactions' || resource === 'budgets'"
                    class="space-y-1.5"
                >
                    <Label for="finance-category"
                        >Kategori{{
                            resource === 'budgets' ? ' (opsional)' : ''
                        }}</Label
                    ><select
                        id="finance-category"
                        v-model="form.category_id"
                        :required="resource === 'transactions'"
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm text-foreground"
                    >
                        <option value="">
                            {{
                                resource === 'budgets'
                                    ? 'Tanpa kategori'
                                    : 'Pilih kategori'
                            }}
                        </option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option></select
                    ><InputError :message="form.errors.category_id" />
                </div>
                <div
                    v-if="
                        resource === 'transactions' ||
                        resource === 'projections'
                    "
                    class="space-y-1.5"
                >
                    <Label for="finance-cycle">Siklus tanam (opsional)</Label
                    ><select
                        id="finance-cycle"
                        v-model="form.planting_cycle_id"
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm text-foreground"
                    >
                        <option value="">Tanpa siklus tanam</option>
                        <option
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.label }}
                        </option></select
                    ><InputError :message="form.errors.planting_cycle_id" />
                </div>
                <div v-if="resource === 'transactions'" class="space-y-1.5">
                    <Label for="finance-date">Tanggal transaksi</Label
                    ><Input
                        id="finance-date"
                        v-model="form.transaction_date"
                        type="date"
                        required
                    /><InputError :message="form.errors.transaction_date" />
                </div>
                <div
                    v-if="resource === 'budgets' || resource === 'projections'"
                    class="grid grid-cols-2 gap-3"
                >
                    <div
                        v-for="field in periods"
                        :key="field"
                        class="space-y-1.5"
                    >
                        <Label :for="`finance-${field}`">{{
                            field === 'period_start'
                                ? 'Awal periode'
                                : 'Akhir periode'
                        }}</Label
                        ><Input
                            :id="`finance-${field}`"
                            v-model="form[field]"
                            type="date"
                            required
                        /><InputError :message="form.errors[field]" />
                    </div>
                </div>
                <div
                    v-for="field in moneyFields"
                    :key="field.key"
                    class="space-y-1.5"
                >
                    <Label :for="`finance-${field.key}`"
                        >{{ field.label
                        }}{{ field.optional ? ' (opsional)' : '' }}</Label
                    ><Input
                        :id="`finance-${field.key}`"
                        v-model="form[field.key]"
                        inputmode="decimal"
                        :required="!field.optional"
                        pattern="[0-9]{1,16}([.][0-9]{1,2})?"
                        placeholder="0.00"
                    /><InputError :message="form.errors[field.key]" />
                </div>
                <div v-if="resource === 'transactions'" class="space-y-1.5">
                    <Label for="finance-description"
                        >Keterangan (opsional)</Label
                    ><textarea
                        id="finance-description"
                        v-model="form.description"
                        rows="3"
                        maxlength="5000"
                        class="w-full rounded-xl border border-border bg-card p-3 text-sm text-foreground"
                    /><InputError :message="form.errors.description" />
                </div>
                <p
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="text-sm text-error"
                >
                    Data belum tersimpan. Periksa isian yang ditandai.
                </p>
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="emit('update:open', false)"
                        >Batal</Button
                    ><Button type="submit" :disabled="form.processing">{{
                        form.processing ? 'Menyimpan…' : 'Simpan'
                    }}</Button></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>
</template>
