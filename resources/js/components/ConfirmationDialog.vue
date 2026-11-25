<script setup lang="ts">
import { FocusScope } from 'reka-ui';
import Swal from 'sweetalert2';
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import {
    confirmationState,
    resolveConfirmation,
} from '@/composables/useConfirmation';

const props = defineProps<{ mobile?: boolean }>();
const target = useTemplateRef<HTMLElement>('target');
const mounted = ref(false);
let currentRun = 0;

onMounted(() => {
    mounted.value = true;
});

function closeOwnDialog(): void {
    if (Swal.getPopup()?.classList.contains('app-confirmation-popup')) {
        Swal.close();
    }
}

watch(
    () => mounted.value && confirmationState.open,
    async (open) => {
        const run = ++currentRun;
        if (!open) {
            closeOwnDialog();
            return;
        }

        await nextTick();
        if (run !== currentRun) return;
        if (!target.value) {
            // Never leave a pending decision with an empty full-screen target.
            resolveConfirmation(false);
            return;
        }

        const result = await Swal.fire({
            target: target.value,
            titleText: confirmationState.title,
            text: confirmationState.description,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmationState.confirmLabel,
            cancelButtonText: confirmationState.cancelLabel,
            focusCancel: true,
            reverseButtons: true,
            allowOutsideClick: false,
            keydownListenerCapture: true,
            heightAuto: false,
            scrollbarPadding: false,
            buttonsStyling: false,
            // Close before Vue removes the target, so body/ARIA cleanup can finish.
            hideClass: { popup: '', backdrop: '' },
            customClass: {
                container: 'app-confirmation-container',
                popup: props.mobile
                    ? 'admin-dashboard-theme petugas-dashboard-theme app-confirmation-popup'
                    : 'admin-dashboard-theme app-confirmation-popup',
                confirmButton: 'app-confirmation-confirm',
                cancelButton: 'app-confirmation-cancel',
            },
        });

        if (run === currentRun) resolveConfirmation(result.isConfirmed);
    },
    { flush: 'pre' },
);

onBeforeUnmount(() => {
    ++currentRun;
    closeOwnDialog();
    resolveConfirmation(false);
});
</script>

<template>
    <!-- Body teleports must not participate in the initial SSR hydration. -->
    <Teleport v-if="mounted" to="body">
        <!-- Keep the target outside Reka's as-child Slot: it strips a cached
             child ref on reopening, leaving an empty overlay without a popup. -->
        <FocusScope
            v-if="confirmationState.open"
            trapped
            loop
            @mount-auto-focus.prevent
            @unmount-auto-focus.prevent
        >
            <div ref="target" class="app-confirmation-target" />
        </FocusScope>
    </Teleport>
</template>
