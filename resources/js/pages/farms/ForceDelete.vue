<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { AlertTriangle, ArrowLeft, LoaderCircle, RotateCcw, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import FarmField from '@/components/farms/FarmField.vue';
import type { FarmDeletionSummary } from '@/types/farms';

defineOptions({ inheritAttrs: false });
const props = defineProps<{ farm: { id: string; name: string }; summary: FarmDeletionSummary; backUrl: string }>();
const refreshing = ref(false);
const form = useForm({ confirmation_name: '', reason: '', summary_token: props.summary.token });
const generalError = computed(() => (form.errors as Record<string, string | undefined>).force_delete);
const affected = computed(() => props.summary.groups.filter((item) => item.count > 0));
const ready = computed(() => form.confirmation_name === props.farm.name && form.reason.trim().length >= 5 && form.reason.length <= 2000 && !props.summary.blockers.length && !refreshing.value);
watch(() => props.summary.token, (token) => {
    form.summary_token = token;
    form.confirmation_name = '';
    form.clearErrors();
});
function refresh(): void {
    refreshing.value = true;
    router.reload({ only: ['farm', 'summary', 'backUrl'], onFinish: () => { refreshing.value = false; } });
}
function submit(): void {
    if (!ready.value || form.processing) return;
    form.delete(`/admin/farm-management/${props.farm.id}/force-delete`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Force delete ${farm.name}`" />
    <div class="mx-auto min-w-0 max-w-3xl space-y-6 break-words">
        <Link :href="backUrl" class="inline-flex items-center gap-2 rounded text-sm font-medium text-muted-foreground hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"><ArrowLeft class="size-4" />Kembali</Link>
        <header class="space-y-2"><p class="text-xs font-semibold tracking-widest text-error uppercase">Khusus SuperAdmin</p><h1 class="admin-page-title">Force delete kebun</h1><p class="text-sm leading-6 text-muted-foreground">Periksa dampak penghapusan permanen untuk <strong class="text-foreground">{{ farm.name }}</strong>.</p></header>
        <div role="note" class="flex items-start gap-3 rounded-xl border border-error/30 bg-error/10 p-4 text-error"><AlertTriangle class="mt-0.5 size-5 shrink-0" /><p class="text-sm leading-6">Kebun dan data di bawah akan dihapus permanen. Data tidak dapat dipulihkan melalui aplikasi. Akun pengguna, tenant, master data, chat AI, dan audit log tetap disimpan. File asli pada layanan eksternal tidak ikut dihapus.</p></div>
        <section class="overflow-hidden rounded-xl border border-border bg-card" :aria-busy="refreshing">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5"><h2 class="admin-section-title">Ringkasan dampak</h2><Button type="button" variant="outline" size="sm" :disabled="refreshing || form.processing" @click="refresh"><LoaderCircle v-if="refreshing" class="animate-spin" /><RotateCcw v-else />Muat ulang ringkasan</Button></div>
            <p v-if="refreshing" role="status" class="p-5 text-sm text-muted-foreground">Memeriksa data terkait…</p>
            <dl v-else class="divide-y divide-border"><div v-for="item in affected" :key="item.key" class="flex items-start justify-between gap-4 px-5 py-3 text-sm"><dt class="min-w-0">{{ item.label }}<span class="mt-1 block text-xs text-muted-foreground">{{ item.effect === 'detach' ? 'Penugasan dilepas; akun tetap disimpan' : 'Dihapus permanen' }}</span></dt><dd class="shrink-0 font-semibold tabular-nums">{{ item.count }}</dd></div></dl>
            <p v-if="affected.length === 1" class="border-t border-border px-5 py-3 text-xs text-muted-foreground">Kebun ini belum memiliki data operasional atau penugasan.</p>
        </section>
        <div v-if="summary.blockers.length" role="alert" class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm leading-6 text-error"><p v-for="blocker in summary.blockers" :key="blocker">{{ blocker }}</p></div>
        <form class="space-y-5 rounded-xl border border-border bg-card p-5 md:p-6" @submit.prevent="submit">
            <fieldset class="space-y-5 disabled:opacity-70" :disabled="form.processing || refreshing || !!summary.blockers.length">
                <legend class="admin-section-title mb-5">Konfirmasi penghapusan</legend>
                <FarmField id="force-delete-name" label="Ketik nama kebun" required :error="form.errors.confirmation_name" :hint="`Ketik persis: ${farm.name}`" v-slot="field"><input :id="field.id" v-model="form.confirmation_name" class="admin-field" autocomplete="off" required :maxlength="150" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
                <FarmField id="force-delete-reason" label="Alasan penghapusan" required :error="form.errors.reason" hint="Minimal 5 karakter. Alasan dan ringkasan penghapusan dicatat di audit log." v-slot="field"><textarea :id="field.id" v-model="form.reason" class="admin-field resize-y" rows="3" required minlength="5" maxlength="2000" :aria-invalid="field.invalid" :aria-describedby="field.description" /></FarmField>
            </fieldset>
            <p v-if="generalError || form.errors.summary_token" role="alert" class="rounded-xl bg-error/10 p-3 text-sm text-error">{{ generalError || form.errors.summary_token }}</p>
            <div class="flex flex-wrap justify-end gap-3"><Button v-if="!form.processing" as-child variant="outline"><Link :href="backUrl">Batal</Link></Button><Button variant="destructive" type="submit" class="h-auto min-h-10 max-w-full whitespace-normal" :disabled="!ready || form.processing"><LoaderCircle v-if="form.processing" class="animate-spin" /><Trash2 v-else />{{ form.processing ? 'Menghapus permanen…' : 'Hapus kebun dan seluruh data terkait' }}</Button></div>
        </form>
    </div>
</template>
