<script setup lang="ts">
import type {
    ExecutiveCellRow,
    ExecutiveColumn,
} from '@/types/owner-executive';
defineProps<{
    columns: ExecutiveColumn[];
    rows: ExecutiveCellRow[];
    caption: string;
    selectable?: boolean;
}>();
defineEmits<{ select: [row: ExecutiveCellRow] }>();
</script>

<template>
    <div
        class="max-h-[32rem] overflow-auto rounded-lg border border-border"
        tabindex="0"
        :aria-label="caption"
    >
        <table class="w-full text-left text-sm whitespace-nowrap">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead
                class="sticky top-0 z-10 bg-muted text-xs text-muted-foreground"
            >
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-3 font-medium"
                        :class="column.numeric ? 'text-right' : ''"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr
                    v-for="(row, index) in rows"
                    :key="row.id ?? index"
                    class="hover:bg-muted/40"
                >
                    <td
                        v-for="(column, colIndex) in columns"
                        :key="column.key"
                        class="px-4 py-3"
                        :class="column.numeric ? 'text-right tabular-nums' : ''"
                    >
                        <button
                            v-if="selectable && colIndex === 0"
                            type="button"
                            class="min-h-11 text-left font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                            @click="$emit('select', row)"
                        >
                            {{ row[column.key] }}
                        </button>
                        <span v-else>{{ row[column.key] }}</span>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td
                        :colspan="columns.length"
                        class="px-4 py-10 text-center text-muted-foreground"
                    >
                        Tidak ada data untuk pilihan ini.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
