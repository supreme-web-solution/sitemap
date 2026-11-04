<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { computed } from 'vue';
import {
    injectMergeFieldTarget,
    useMergeFieldActions,
} from '@/composables/useMergeFieldTarget';
import type { MergeField } from '@/types';

const props = withDefaults(
    defineProps<{
        mergeFields: MergeField[];
        context?: 'sequence' | 'template';
    }>(),
    {
        context: 'sequence',
    },
);

const mergeTarget = injectMergeFieldTarget();

const { applyMergeField, copiedKey, tokenFor } = useMergeFieldActions(
    mergeTarget,
    props.context === 'template'
        ? {
              insertToast: 'Added where your cursor is in the template.',
              copyToast:
                  'Click inside the subject or message, then click the tag again to insert — or paste with Ctrl+V.',
          }
        : {
              insertToast: 'Added where your cursor is in the step.',
              copyToast:
                  'Click inside a step’s subject or body, then click the tag again to insert — or paste with Ctrl+V.',
          },
);

const stepOne = computed(() =>
    props.context === 'template'
        ? 'Click inside the template’s Subject or Message.'
        : 'Click inside a step’s Subject or Body.',
);
</script>

<template>
    <aside
        class="panel flex flex-col gap-4 p-5 xl:sticky xl:top-24 xl:max-h-[calc(100dvh-6rem)] xl:overflow-y-auto xl:overscroll-y-contain"
    >
        <div>
            <h2 class="text-base font-semibold">Personalization tags</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                These fill in automatically for each lead when you send an
                email.
            </p>
        </div>

        <ol
            class="list-decimal space-y-1 pl-4 text-xs text-muted-foreground"
        >
            <li>
                {{ stepOne }}
            </li>
            <li>
                Click a tag below to
                <strong class="font-medium text-foreground">insert</strong> it
                (or copy if no field is focused).
            </li>
        </ol>

        <ul class="flex flex-col gap-2" data-test="merge-fields">
            <li v-for="field in mergeFields" :key="field.key">
                <button
                    type="button"
                    class="flex w-full items-start gap-2 rounded-xl border px-3 py-2.5 text-left transition hover:border-primary/40 hover:bg-accent/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20"
                    :class="
                        copiedKey === field.key
                            ? 'border-primary/50 bg-primary/5'
                            : ''
                    "
                    @click="applyMergeField(field.key)"
                >
                    <Check
                        v-if="copiedKey === field.key"
                        class="mt-0.5 size-4 shrink-0 text-emerald-600"
                        aria-hidden="true"
                    />
                    <Copy
                        v-else
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        <code class="text-sm font-medium text-primary">{{
                            tokenFor(field.key)
                        }}</code>
                        <span class="mt-0.5 block text-xs text-muted-foreground">{{
                            field.description
                        }}</span>
                    </span>
                </button>
            </li>
        </ul>
    </aside>
</template>
