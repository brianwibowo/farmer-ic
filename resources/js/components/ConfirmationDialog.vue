<script setup lang="ts">
import { FocusScope } from 'reka-ui';
import Swal from 'sweetalert2';
import { nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { confirmationState, resolveConfirmation } from '@/composables/useConfirmation';

const target = useTemplateRef<HTMLElement>('target');
const mounted = ref(false);
let currentRun = 0;

onMounted(() => { mounted.value = true; });

watch(() => confirmationState.open, async (open) => {
    const run = ++currentRun;
    if (!open) {
        Swal.close();
        return;
    }

    await nextTick();
    if (run !== currentRun || !target.value) return;

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
            popup: 'admin-dashboard-theme app-confirmation-popup',
            confirmButton: 'app-confirmation-confirm',
            cancelButton: 'app-confirmation-cancel',
        },
    });

    if (run === currentRun) resolveConfirmation(result.isConfirmed);
}, { flush: 'pre' });

onBeforeUnmount(() => {
    ++currentRun;
    Swal.close();
    resolveConfirmation(false);
});
</script>

<template>
    <!-- Body teleports must not participate in the initial SSR hydration. -->
    <Teleport v-if="mounted" to="body">
        <!-- Pause the editor's focus trap while SweetAlert handles the confirmation. -->
        <FocusScope
            v-if="confirmationState.open"
            as-child
            trapped
            loop
            @mount-auto-focus.prevent
            @unmount-auto-focus.prevent
        >
            <div ref="target" class="app-confirmation-target" />
        </FocusScope>
    </Teleport>
</template>
