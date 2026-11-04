<script setup lang="ts">
import { LoaderCircle, Sparkles } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { requestHeaders } from '@/lib/siteEditor/http';
import { generate as generateSequence } from '@/routes/sequences';

import type { SequenceAiDraft } from '@/types/outreach';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    teamSlug: string;
    available: boolean;
}>();

const emit = defineEmits<{
    generated: [draft: SequenceAiDraft];
}>();

const prompt = ref(
    'Follow up after I send a local business their free website preview. Four emails, friendly and not pushy.',
);
const error = ref<string | null>(null);
const generating = ref(false);

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const submit = async () => {
    if (!props.available) {
        return;
    }

    error.value = null;
    generating.value = true;

    try {
        const response = await fetch(
            generateSequence.url({ current_team: props.teamSlug }),
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    ...requestHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ prompt: prompt.value.trim() }),
            },
        );

        const body = (await response.json().catch(() => null)) as
            | SequenceAiDraft
            | { message?: string; errors?: Record<string, string[]> }
            | null;

        if (!response.ok) {
            const first = body && 'errors' in body && body.errors
                ? Object.values(body.errors)[0]?.[0]
                : undefined;
            error.value =
                first ??
                (body && 'message' in body ? body.message : null) ??
                'Could not generate the sequence.';

            return;
        }

        if (!body || !('steps' in body) || !Array.isArray(body.steps)) {
            error.value = 'Unexpected response from the AI service.';

            return;
        }

        emit('generated', body);
        open.value = false;
        toast.success('Sequence drafted', {
            description: 'Review the four emails, then save when it looks right.',
        });
    } finally {
        generating.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="max-w-lg" data-test="sequence-ai-dialog">
            <DialogHeader>
                <DialogTitle>Draft sequence with AI</DialogTitle>
                <DialogDescription>
                    Describe who you email and what you want to achieve. We
                    create four timed steps (like day 1, 3, 6, 10) with
                    personalization tags you can edit.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="!available"
                class="rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                Add ANTHROPIC_API_KEY in your server environment to draft
                sequences (same key as AI site planning).
            </div>

            <form v-else class="grid gap-3" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="sequence-ai-prompt">What should this sequence do?</Label>
                    <textarea
                        id="sequence-ai-prompt"
                        v-model="prompt"
                        rows="5"
                        required
                        minlength="10"
                        :class="textareaClass"
                        placeholder="e.g. After I send a plumber their preview site, remind them gently and offer to hop on a quick call."
                    />
                    <InputError :message="error ?? undefined" />
                </div>
                <Button type="submit" :disabled="generating">
                    <LoaderCircle v-if="generating" class="animate-spin" />
                    <Sparkles v-else />
                    Generate 4 emails
                </Button>
            </form>
        </DialogScrollContent>
    </Dialog>
</template>
