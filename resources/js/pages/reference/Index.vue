<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/pages/farmer/Index.vue -->
<script setup lang="ts">
defineOptions({ layout: [] });
import { Head } from '@/reference/inertia';
import { ref } from 'vue';
import FarmerMobileShell from '@/reference/components/farmer/FarmerMobileShell.vue';
import AiChatHeader from '@/reference/components/farmer/AiChatHeader.vue';
import AiChatComposer from '@/reference/components/farmer/AiChatComposer.vue';
import AiMessageList from '@/reference/components/farmer/AiMessageList.vue';
const inputText = ref('');
const quickPrompts = [
    '📊 Gambar: Panduan Pemupukan Jagung sesuai Fase',
    '💧 Gambar: Jadwal & Kebutuhan Air Kebun',
    '🐛 Gambar: Deteksi & Pengendalian Hama Daun',
    'Bagaimana kondisi kebun saya hari ini?',
];
const voice = {
    isPreparing: ref(false), isRecording: ref(false), isTranscribing: ref(false),
    error: ref<string | null>(null), recordingMode: ref('browser'), elapsedSeconds: ref(0),
    cancelPreparing() {}, stopRecording() {},
};
</script>
<template>
    <Head title="AI · Referensi historis" />
    <FarmerMobileShell active-key="ai">
        <section class="relative flex h-full min-h-0 flex-col overflow-hidden bg-background">
            <p id="reference-ai-notice" role="note" class="shrink-0 border-b border-border bg-muted px-4 py-3 text-xs leading-5 text-muted-foreground">Tampilan AI historis saja. Kirim pesan, suara, unggah gambar, dan perubahan riwayat dinonaktifkan. Tidak ada panggilan AI maupun jawaban buatan.</p>
            <fieldset disabled aria-describedby="reference-ai-notice" class="flex min-h-0 flex-1 flex-col">
                <AiChatHeader :is-sending="false" />
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-5">
                    <AiMessageList :messages="[]" :quick-prompts="quickPrompts" :copied-message-id="null" :speaking-message-id="null" />
                </div>
                <AiChatComposer v-model="inputText" :pending-attachments="[]" :max-attachment-count="4" :voice="voice" :is-sending="false" :can-send="false" :textarea-ref="() => {}" :file-input-ref="() => {}" />
            </fieldset>
        </section>
    </FarmerMobileShell>
</template>
