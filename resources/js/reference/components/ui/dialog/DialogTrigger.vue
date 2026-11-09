<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/components/ui/dialog/DialogTrigger.vue -->
<script setup lang="ts">
import type { DialogTriggerProps } from "reka-ui"
import { DialogTrigger } from "reka-ui"

const props = defineProps<DialogTriggerProps>()
</script>

<template>
  <DialogTrigger
    data-slot="dialog-trigger"
    v-bind="props"
  >
    <slot />
  </DialogTrigger>
</template>
