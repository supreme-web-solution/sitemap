import { useClipboard } from '@vueuse/core';
import { inject, provide, ref, type InjectionKey, type Ref } from 'vue';
import { toast } from 'vue-sonner';

export type MergeFieldTarget = {
    insertToken: (token: string) => void;
};

const mergeFieldTargetKey: InjectionKey<Ref<MergeFieldTarget | null>> =
    Symbol('mergeFieldTarget');

/** Register a focused subject/body field so tags can insert at the cursor. */
export function provideMergeFieldTarget(): Ref<MergeFieldTarget | null> {
    const target = ref<MergeFieldTarget | null>(null);
    provide(mergeFieldTargetKey, target);

    return target;
}

export function injectMergeFieldTarget(): Ref<MergeFieldTarget | null> {
    return inject(mergeFieldTargetKey, ref(null));
}

type MergeFieldActionLabels = {
    insertToast?: string;
    copyToast?: string;
};

export function useMergeFieldActions(
    mergeTarget: Ref<MergeFieldTarget | null>,
    labels: MergeFieldActionLabels = {},
) {
    const { copy } = useClipboard();
    const copiedKey = ref<string | null>(null);
    let resetTimer: ReturnType<typeof setTimeout> | null = null;

    const tokenFor = (key: string): string => `{{${key}}}`;

    const applyMergeField = async (key: string): Promise<void> => {
        const token = tokenFor(key);

        try {
            await copy(token);
        } catch {
            toast.error('Could not copy to clipboard.');

            return;
        }

        copiedKey.value = key;

        if (resetTimer) {
            clearTimeout(resetTimer);
        }

        resetTimer = setTimeout(() => {
            copiedKey.value = null;
        }, 2500);

        if (mergeTarget.value) {
            mergeTarget.value.insertToken(token);
            toast.success(`Inserted ${token}`, {
                description:
                    labels.insertToast ??
                    'Added where your cursor is in the field.',
            });

            return;
        }

        toast.success(`Copied ${token}`, {
            description:
                labels.copyToast ??
                'Click inside subject or message, then click the tag again to insert — or paste with Ctrl+V.',
        });
    };

    return { applyMergeField, copiedKey, tokenFor };
}
