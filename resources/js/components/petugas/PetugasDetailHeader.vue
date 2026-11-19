<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{ title: string; backHref: string; backLabel: string }>();
</script>

<template>
    <header class="flex items-center gap-3">
        <Button
            as-child
            variant="outline"
            size="icon"
            class="size-11 rounded-xl bg-card"
        >
            <Link :href="backHref" :aria-label="backLabel">
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
        </Button>
        <h1 class="min-w-0 break-words">{{ title }}</h1>
    </header>
</template>
