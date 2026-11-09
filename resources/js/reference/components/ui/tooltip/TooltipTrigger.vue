<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/components/ui/tooltip/TooltipTrigger.vue -->
<script setup lang="ts">
import type { TooltipTriggerProps } from "reka-ui"
import { TooltipTrigger } from "reka-ui"

const props = defineProps<TooltipTriggerProps>()
</script>

<template>
  <TooltipTrigger
    data-slot="tooltip-trigger"
    v-bind="props"
  >
    <slot />
  </TooltipTrigger>
</template>
