<script setup lang="ts">
import { SelectItem } from '@/components/ui/select';
import { selectOptionValue, type SelectValue } from './selectValue';

defineProps<{ value: SelectValue; disabled?: boolean }>();
</script>

<template>
    <SelectItem
        :value="selectOptionValue(value)"
        :disabled="disabled"
        class="min-h-10 cursor-pointer rounded-lg py-2 break-words whitespace-normal [@media(pointer:coarse)]:min-h-11"
    >
        <slot />
    </SelectItem>
</template>
