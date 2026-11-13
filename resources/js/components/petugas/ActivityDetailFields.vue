<script setup lang="ts">
import { computed, watch } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { areaMaximum, formatActivityNumber } from '@/lib/activityInputLimits';
export type ActivityField = {
    key: string;
    label: string;
    type: string;
    optional?: boolean;
    options?: string[];
    step?: string;
    max?: number;
    when?: [string, string];
};
const props = defineProps<{
    fields: ActivityField[];
    errors: Record<string, string>;
    areaLimitM2: number | null;
}>();
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
const bedAreaError = computed(
    () =>
        props.errors['details.bed_area'] ??
        (props.fields.some((field) => field.key === 'bed_count')
            ? props.errors['details.area']
            : undefined),
);
const fieldClass =
    'block min-h-12 w-full rounded-xl border border-border bg-card px-3 py-2 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error';
function fieldMaximum(field: ActivityField): number | undefined {
    return field.key === 'area'
        ? areaMaximum(field.max, props.areaLimitM2, values.value.area_unit)
        : field.max;
}
</script>
<template>
    <div class="space-y-4">
        <template v-for="group in [false, true]" :key="String(group)">
            <component
                :is="group ? 'details' : 'div'"
                v-if="visible.some((f) => !!f.optional === group)"
                class="space-y-4"
                :open="
                    group &&
                    visible.some(
                        (f) => f.optional && errors[`details.${f.key}`],
                    )
                        ? true
                        : undefined
                "
            >
                <summary
                    v-if="group"
                    class="cursor-pointer py-2 text-sm font-medium text-secondary"
                >
                    Detail tambahan (opsional)
                </summary>
                <div
                    v-for="field in visible.filter(
                        (f) => !!f.optional === group,
                    )"
                    :key="field.key"
                    class="space-y-2"
                >
                    <label :for="`detail-${field.key}`" class="text-sm"
                        >{{ field.label
                        }}{{ !field.optional ? ' *' : '' }}</label
                    >
                    <AppSelect
                        v-if="field.type === 'select'"
                        :id="`detail-${field.key}`"
                        v-model="values[field.key]"
                        :required="!field.optional"
                        :aria-label="field.label"
                        :class="fieldClass"
                        :aria-invalid="!!errors[`details.${field.key}`]"
                        ><AppSelectOption
                            v-for="option in field.options"
                            :key="option"
                            :value="option"
                            >{{ option }}</AppSelectOption
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
                        :required="!field.optional"
                        :class="fieldClass"
                        :aria-invalid="!!errors[`details.${field.key}`]"
                    />
                    <input
                        v-else
                        :id="`detail-${field.key}`"
                        v-model="values[field.key]"
                        :type="field.type"
                        :required="!field.optional"
                        :min="
                            field.key === 'area'
                                ? values.area_unit === 'Ha'
                                    ? 0.0001
                                    : 1
                                : field.key === 'moisture'
                                  ? 0
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
                        :class="fieldClass"
                        :aria-invalid="
                            !!errors[`details.${field.key}`] ||
                            (!!bedAreaError &&
                                ['bed_count', 'length_m', 'width_m'].includes(
                                    field.key,
                                ))
                        "
                        :aria-describedby="
                            [
                                field.type === 'number' &&
                                fieldMaximum(field) != null
                                    ? `detail-hint-${field.key}`
                                    : null,
                                errors[`details.${field.key}`]
                                    ? `detail-error-${field.key}`
                                    : null,
                                bedAreaError &&
                                ['bed_count', 'length_m', 'width_m'].includes(
                                    field.key,
                                )
                                    ? 'detail-bed-area-error'
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' ') || undefined
                        "
                    />
                    <p
                        v-if="
                            field.type === 'number' &&
                            fieldMaximum(field) != null
                        "
                        :id="`detail-hint-${field.key}`"
                        class="text-xs text-muted-foreground"
                    >
                        Maksimal {{ formatActivityNumber(fieldMaximum(field)!)
                        }}{{
                            field.key === 'area' ? ` ${values.area_unit}` : ''
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
            </component>
        </template>
        <p
            v-if="values.bed_count && values.length_m && values.width_m"
            class="rounded-xl bg-primary/10 p-3 text-sm"
        >
            Luas bedengan:
            {{
                Number(values.bed_count) *
                Number(values.length_m) *
                Number(values.width_m)
            }}
            m²
        </p>
        <p
            v-if="bedAreaError"
            id="detail-bed-area-error"
            class="text-xs text-error"
            aria-live="polite"
        >
            {{ bedAreaError }}
        </p>
    </div>
</template>
