export type SelectValue = string | number | boolean | null;

// Objects allow explicit empty/null options, while `by="key"` matches numeric
// IDs from query strings without changing the selected option's actual type.
export function selectOptionValue(value: SelectValue | undefined): {
    key: string;
    value: SelectValue;
} {
    return {
        key:
            value == null
                ? 'null:'
                : typeof value === 'boolean'
                  ? `boolean:${value}`
                  : `value:${value}`,
        value: value ?? null,
    };
}
