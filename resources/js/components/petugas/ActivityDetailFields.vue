<script setup lang="ts">
import { computed, watch } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { useErrorDisclosure } from '@/composables/useErrorDisclosure';
import { areaMaximum, formatActivityNumber } from '@/lib/activityInputLimits';
export type ActivityField = {
    key: string;
    label: string;
    type: string;
    optional?: boolean;
    options?: string[];
    optionLabels?: Record<string, string>;
    hint?: string;
    placeholder?: string;
    step?: string;
    max?: number;
    when?: [string, string];
    required_when?: { key: string; value: string };
};
const props = withDefaults(
    defineProps<{
        fields: ActivityField[];
        errors: Record<string, string>;
        areaLimitM2: number | null;
        compact?: boolean;
    }>(),
    { compact: false },
);
const values = defineModel<Record<string, string>>({ required: true });
watch(
    () => values.value.area_unit,
    (unit, previous) => {
        if (!previous || unit === previous || !values.value.area) return;
        values.value.area = String(
            Number(
                (
                    Number(values.value.area) * (unit === 'Ha' ? 0.0001 : 10000)
                ).toFixed(4),
            ),
        );
    },
);
const visible = computed(() =>
    props.fields.filter(
        (f) => !f.when || values.value[f.when[0]] === f.when[1],
    ),
);
const { open: optionalOpen, onToggle: syncOptionalOpen } = useErrorDisclosure(
    () =>
        visible.value.some(
            (field) =>
                isFieldOptional(field) &&
                !!props.errors[`details.${field.key}`],
        ),
);
const fieldClass =
    'block min-h-12 w-full rounded-xl border border-border bg-card px-3 py-2 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error';
function isFieldOptional(field: ActivityField): boolean {
    if (field.required_when) {
        return (
            values.value[field.required_when.key] !== field.required_when.value
        );
    }

    return !!field.optional;
}
function fieldMaximum(field: ActivityField): number | undefined {
    return field.key === 'area'
        ? areaMaximum(field.max, props.areaLimitM2, values.value.area_unit)
        : field.max;
}
function fieldLayout(field: ActivityField, optional: boolean): string {
    if (!props.compact) return '';
    const pairs: Record<string, string> = {
        area: 'area_unit',
        weight: 'weight_unit',
        quantity: 'quantity_unit',
    };
    const group = visible.value.filter(
        (other) => isFieldOptional(other) === optional,
    );
    const index = group.findIndex((other) => other.key === field.key);
    const previous = group[index - 1];
    const paired =
        (pairs[field.key] !== undefined &&
            group[index + 1]?.key === pairs[field.key]) ||
        (previous !== undefined && pairs[previous.key] === field.key);
    return paired ? '' : 'col-span-2';
}
</script>
<template>
    <div class="space-y-4">
        <template v-for="group in [false, true]" :key="String(group)">
            <component
                :is="group ? 'details' : 'div'"
                v-if="visible.some((f) => isFieldOptional(f) === group)"
                class="space-y-4"
                :open="group && optionalOpen ? true : undefined"
                @toggle="syncOptionalOpen"
            >
                <summary
                    v-if="group"
                    class="cursor-pointer rounded-xl py-2 text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Detail tambahan (opsional)
                </summary>
                <div
                    :class="
                        compact
                            ? 'grid grid-cols-[minmax(0,1fr)_minmax(5.75rem,0.55fr)] gap-x-3 gap-y-4'
                            : 'space-y-4'
                    "
                >
                    <div
                        v-for="field in visible.filter(
                            (f) => isFieldOptional(f) === group,
                        )"
                        :key="field.key"
                        class="min-w-0 space-y-2"
                        :class="fieldLayout(field, group)"
                    >
                        <label
                            :for="`detail-${field.key}`"
                            class="text-sm font-medium"
                            >{{ field.label
                            }}{{ !isFieldOptional(field) ? ' *' : '' }}</label
                        >
                        <AppSelect
                            v-if="field.type === 'select'"
                            :id="`detail-${field.key}`"
                            v-model="values[field.key]"
                            :required="!isFieldOptional(field)"
                            :aria-label="field.label"
                            :class="[
                                fieldClass,
                                { 'petugas-activity-field': compact },
                            ]"
                            :aria-invalid="!!errors[`details.${field.key}`]"
                            :aria-describedby="
                                [
                                    field.hint
                                        ? `detail-hint-${field.key}`
                                        : null,
                                    errors[`details.${field.key}`]
                                        ? `detail-error-${field.key}`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' ') || undefined
                            "
                            ><AppSelectOption
                                v-for="option in field.options"
                                :key="option"
                                :value="option"
                                >{{
                                    field.optionLabels?.[option] ?? option
                                }}</AppSelectOption
                            ></AppSelect
                        >
                        <textarea
                            v-else-if="
                                ['description', 'immediate_action'].includes(
                                    field.key,
                                )
                            "
                            :id="`detail-${field.key}`"
                            v-model="values[field.key]"
                            rows="3"
                            maxlength="5000"
                            :required="!isFieldOptional(field)"
                            :class="[
                                fieldClass,
                                { 'petugas-activity-field': compact },
                            ]"
                            :aria-invalid="!!errors[`details.${field.key}`]"
                            :aria-describedby="
                                errors[`details.${field.key}`]
                                    ? `detail-error-${field.key}`
                                    : undefined
                            "
                        />
                        <input
                            v-else
                            :id="`detail-${field.key}`"
                            v-model="values[field.key]"
                            :type="field.type"
                            :required="!isFieldOptional(field)"
                            :min="
                                field.key === 'area'
                                    ? values.area_unit === 'Ha'
                                        ? 0.0001
                                        : 1
                                    : field.step
                            "
                            :step="
                                field.key === 'area'
                                    ? values.area_unit === 'Ha'
                                        ? 0.0001
                                        : 1
                                    : field.step
                            "
                            :max="fieldMaximum(field)"
                            :maxlength="field.type === 'text' ? 100 : undefined"
                            :placeholder="
                                field.placeholder ??
                                (compact && field.type === 'number'
                                    ? '0'
                                    : undefined)
                            "
                            :inputmode="
                                field.type === 'number' ? 'decimal' : undefined
                            "
                            :class="[
                                fieldClass,
                                { 'petugas-activity-field': compact },
                            ]"
                            :aria-invalid="!!errors[`details.${field.key}`]"
                            :aria-describedby="
                                [
                                    field.hint ||
                                    (field.type === 'number' &&
                                        fieldMaximum(field) != null)
                                        ? `detail-hint-${field.key}`
                                        : null,
                                    errors[`details.${field.key}`]
                                        ? `detail-error-${field.key}`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' ') || undefined
                            "
                        />
                        <p
                            v-if="field.hint"
                            :id="`detail-hint-${field.key}`"
                            class="text-xs text-muted-foreground"
                        >
                            {{ field.hint }}
                        </p>
                        <p
                            v-else-if="
                                field.type === 'number' &&
                                fieldMaximum(field) != null
                            "
                            :id="`detail-hint-${field.key}`"
                            class="text-xs text-muted-foreground"
                        >
                            Maksimal
                            {{ formatActivityNumber(fieldMaximum(field)!)
                            }}{{
                                field.key === 'area'
                                    ? ` ${values.area_unit}`
                                    : ''
                            }}.
                        </p>
                        <p
                            v-if="errors[`details.${field.key}`]"
                            :id="`detail-error-${field.key}`"
                            class="text-xs text-error"
                            aria-live="polite"
                        >
                            {{ errors[`details.${field.key}`] }}
                        </p>
                    </div>
                </div>
            </component>
        </template>
    </div>
</template>
