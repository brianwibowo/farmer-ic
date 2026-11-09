<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/components/ui/dialog/DialogFooter.vue -->
<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { DialogClose } from "reka-ui"
import { cn } from "@/reference/lib/utils"
import { Button } from '@/reference/components/ui/button'

const props = withDefaults(defineProps<{
  class?: HTMLAttributes["class"]
  showCloseButton?: boolean
}>(), {
  showCloseButton: false,
})
</script>

<template>
  <div
    data-slot="dialog-footer"
    :class="cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', props.class)"
  >
    <slot />
    <DialogClose v-if="showCloseButton" as-child>
      <Button variant="outline">
        Close
      </Button>
    </DialogClose>
  </div>
</template>
