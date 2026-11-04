/** Placeholder values for Select components that map to empty strings in forms. */
export const SELECT_ALL = '__all__';
export const SELECT_ONE_TIME = '__one_time__';
export const SELECT_DEFAULT = '__default__';
export const SELECT_PICK = '__pick__';

export function emptyToSelect(
    value: string | null | undefined,
    sentinel: string = SELECT_ALL,
): string {
    return value ? value : sentinel;
}

export function selectToEmpty(value: string, sentinel: string = SELECT_ALL): string {
    return value === sentinel ? '' : value;
}
