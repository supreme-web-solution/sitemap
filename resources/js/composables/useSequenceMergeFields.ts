import {
    injectMergeFieldTarget,
    provideMergeFieldTarget,
    useMergeFieldActions,
    type MergeFieldTarget,
} from '@/composables/useMergeFieldTarget';

export type SequenceMergeTarget = MergeFieldTarget;

export const provideSequenceMergeTarget = provideMergeFieldTarget;
export const injectSequenceMergeTarget = injectMergeFieldTarget;

export { useMergeFieldActions };
