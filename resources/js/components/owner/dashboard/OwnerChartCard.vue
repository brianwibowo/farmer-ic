<script setup lang="ts">
defineProps<{
    id: string;
    title: string;
    eyebrow?: string;
    description?: string;
}>();
</script>

<template>
    <section
        class="min-w-0 overflow-hidden rounded-xl border border-border bg-card p-4 min-[390px]:p-5"
        :aria-labelledby="`${id}-title`"
    >
        <header class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p
                    v-if="eyebrow"
                    class="mb-1.5 text-[10px] leading-4 font-semibold tracking-[0.13em] text-secondary uppercase dark:text-primary"
                >
                    {{ eyebrow }}
                </p>
                <h2
                    :id="`${id}-title`"
                    class="text-lg leading-6 font-semibold tracking-tight"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="mt-2 text-xs leading-5 text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
            <slot name="action" />
        </header>
        <div class="mt-5 min-w-0"><slot /></div>
        <footer
            v-if="$slots.footer"
            class="mt-4 border-t border-border pt-3 text-xs leading-5 text-muted-foreground"
        >
            <slot name="footer" />
        </footer>
    </section>
</template>
