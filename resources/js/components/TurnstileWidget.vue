<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ siteKey: string; action: string }>();
const token = defineModel<string>({ required: true });
const container = ref<HTMLElement>();
const failed = ref(false);
let widgetId: string | undefined;
let disposed = false;

type Turnstile = {
    render: (element: HTMLElement, options: Record<string, unknown>) => string;
    reset: (id: string) => void;
    remove: (id: string) => void;
};

declare global {
    interface Window { turnstile?: Turnstile }
}

function loadScript(): Promise<void> {
    if (window.turnstile) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => { script.remove(); reject(); };
        document.head.appendChild(script);
    });
}

async function mountWidget() {
    failed.value = false;
    try {
        await loadScript();
        if (disposed || !container.value) return;
        if (!window.turnstile) throw new Error('Turnstile failed to load');
        widgetId = window.turnstile.render(container.value, {
            sitekey: props.siteKey,
            action: props.action,
            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
            'response-field': false,
            callback: (value: string) => { failed.value = false; token.value = value; },
            'expired-callback': () => { token.value = ''; failed.value = true; },
            'timeout-callback': () => { token.value = ''; failed.value = true; },
            'error-callback': () => { token.value = ''; failed.value = true; },
        });
    } catch {
        failed.value = true;
    }
}

function reset() {
    token.value = '';
    if (widgetId && window.turnstile) window.turnstile.reset(widgetId);
}

function retry() {
    if (widgetId && window.turnstile) {
        failed.value = false;
        reset();
    } else {
        void mountWidget();
    }
}

defineExpose({ reset });
onMounted(() => void mountWidget());
onBeforeUnmount(() => {
    disposed = true;
    if (widgetId && window.turnstile) window.turnstile.remove(widgetId);
});
</script>

<template>
    <div>
        <div ref="container" class="max-w-full overflow-x-auto" />
        <p v-if="failed" role="alert" class="mt-2 text-sm text-error">
            Verifikasi keamanan tidak tersedia.
            <button type="button" class="underline" @click="retry">Coba lagi</button>
        </p>
        <p v-else-if="!token" role="status" class="mt-2 text-sm text-muted-foreground">
            Selesaikan verifikasi keamanan untuk melanjutkan.
        </p>
    </div>
</template>
