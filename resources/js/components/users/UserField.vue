<script setup lang="ts">
defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="space-y-2">
        <label :for="id" class="block text-sm font-medium"
            >{{ label }}
            <span v-if="required" class="text-error" aria-hidden="true"
                >*</span
            ></label
        >
        <slot
            :id="id"
            :invalid="!!error"
            :description="error || hint ? id + '-message' : undefined"
        />
        <p
            v-if="error || hint"
            :id="id + '-message'"
            class="text-xs leading-5"
            :class="error ? 'text-error' : 'text-muted-foreground'"
            :role="error ? 'alert' : undefined"
        >
            {{ error || hint }}
        </p>
    </div>
</template>
