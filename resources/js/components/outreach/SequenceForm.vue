<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Plus, Save, Trash2 } from '@lucide/vue';
import { computed, nextTick, watch } from 'vue';
import type { SequenceAiDraft } from '@/types/outreach';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { injectSequenceMergeTarget } from '@/composables/useSequenceMergeFields';
import {
    destroy as destroySequence,
    store as storeSequence,
    update as updateSequence,
} from '@/routes/sequences';

type Step = { delayDays: number; subject: string; body: string };

const props = defineProps<{
    sequence?: {
        id: number;
        name: string;
        isActive: boolean;
        enrollmentCount?: number;
        steps: (Step & { id: number; position: number })[];
    };
    seed?: SequenceAiDraft | null;
}>();

const emit = defineEmits<{ saved: [] }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const mergeTarget = injectSequenceMergeTarget();

const form = useForm({
    name: props.sequence?.name ?? '',
    is_active: props.sequence?.isActive ?? true,
    steps: (props.sequence?.steps ?? [
        { delayDays: 1, subject: '', body: '' },
    ]).map((step) => ({
        delay_days: step.delayDays,
        subject: step.subject,
        body: step.body,
    })),
});

const applySeed = (seed: SequenceAiDraft) => {
    form.name = seed.name;
    form.steps = seed.steps.map((step) => ({
        delay_days: step.delay_days,
        subject: step.subject,
        body: step.body,
    }));
    form.clearErrors();
};

watch(
    () => props.seed,
    (seed) => {
        if (seed) {
            applySeed(seed);
        }
    },
    { immediate: true },
);

const stepTimingHint = (index: number): string => {
    const days = Number(form.steps[index]?.delay_days ?? 0);

    if (index === 0) {
        if (days === 0) {
            return 'Sends as soon as someone is enrolled';
        }

        return `Sends ${days} day${days === 1 ? '' : 's'} after enroll`;
    }

    return `Waits ${days} day${days === 1 ? '' : 's'} after the previous email, then sends`;
};

const registerMergeInsert = (
    stepIndex: number,
    field: 'subject' | 'body',
    element: HTMLInputElement | HTMLTextAreaElement,
) => {
    if (!mergeTarget) {
        return;
    }

    mergeTarget.value = {
        insertToken: (token: string) => {
            const step = form.steps[stepIndex];

            if (!step) {
                return;
            }

            const current = field === 'subject' ? step.subject : step.body;
            const start = element.selectionStart ?? current.length;
            const end = element.selectionEnd ?? start;
            const next = current.slice(0, start) + token + current.slice(end);

            if (field === 'subject') {
                step.subject = next;
            } else {
                step.body = next;
            }

            nextTick(() => {
                element.focus();
                const cursor = start + token.length;
                element.setSelectionRange(cursor, cursor);
            });
        },
    };
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.sequence) {
                form.reset();
                emit('saved');
            }
        },
    };

    if (props.sequence) {
        form.put(
            updateSequence.url({
                current_team: teamSlug.value,
                followUpSequence: props.sequence.id,
            }),
            options,
        );

        return;
    }

    form.post(storeSequence.url({ current_team: teamSlug.value }), options);
};

const remove = () => {
    if (!props.sequence || !confirm(`Delete "${props.sequence.name}"?`)) {
        return;
    }

    form.delete(
        destroySequence.url({
            current_team: teamSlug.value,
            followUpSequence: props.sequence.id,
        }),
        { preserveScroll: true },
    );
};

const addStep = () =>
    form.steps.push({ delay_days: 2, subject: '', body: '' });

const removeStep = (index: number) => {
    if (form.steps.length > 1) {
        form.steps.splice(index, 1);
    }
};

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <form class="panel grid gap-5 p-5" @submit.prevent="submit">
        <div
            class="flex flex-wrap items-start justify-between gap-3 border-b pb-4"
        >
            <div class="grid gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <Mail class="size-4 text-primary" aria-hidden="true" />
                    <span class="text-sm font-medium text-muted-foreground"
                        >Email sequence</span
                    >
                    <Badge
                        v-if="sequence && sequence.enrollmentCount"
                        variant="secondary"
                        class="font-normal"
                    >
                        {{ sequence.enrollmentCount }} enrolled
                    </Badge>
                </div>
                <p class="max-w-xl text-xs text-muted-foreground">
                    Emails run top to bottom. Each step waits the number of
                    days shown, then sends once. Stops if the lead replies, wins,
                    or is marked lost.
                </p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="size-4" />
                Active
            </label>
        </div>

        <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
            <div class="grid gap-2">
                <Label>Sequence name</Label>
                <Input v-model="form.name" required maxlength="80" />
                <InputError :message="form.errors.name" />
            </div>
        </div>

        <div class="relative space-y-0">
            <div
                v-if="form.steps.length > 1"
                class="absolute top-5 bottom-5 left-[1.125rem] w-px bg-border"
                aria-hidden="true"
            />

            <div
                v-for="(step, index) in form.steps"
                :key="index"
                class="relative flex gap-4 pb-8 last:pb-0"
                data-test="sequence-step"
            >
                <div
                    class="relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-primary bg-background text-sm font-semibold text-primary"
                    :aria-label="`Email ${index + 1}`"
                >
                    {{ index + 1 }}
                </div>

                <div
                    class="min-w-0 flex-1 space-y-3 rounded-xl border bg-muted/15 p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium">Email {{ index + 1 }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ stepTimingHint(index) }}
                            </p>
                        </div>
                        <Button
                            v-if="form.steps.length > 1"
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Remove step"
                            @click="removeStep(index)"
                        >
                            <Trash2 />
                        </Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-[7rem_1fr]">
                        <div class="grid gap-1">
                            <Label :for="`delay-${index}`">Wait (days)</Label>
                            <Input
                                :id="`delay-${index}`"
                                v-model.number="step.delay_days"
                                type="number"
                                min="0"
                                max="60"
                            />
                            <p class="text-[11px] leading-snug text-muted-foreground">
                                {{
                                    index === 0
                                        ? 'After enroll'
                                        : 'After prior email'
                                }}
                            </p>
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`subject-${index}`">Subject</Label>
                            <Input
                                :id="`subject-${index}`"
                                v-model="step.subject"
                                maxlength="150"
                                required
                                placeholder="e.g. A free website idea for {{business_name}}"
                                @focus="
                                    registerMergeInsert(
                                        index,
                                        'subject',
                                        $event.target as HTMLInputElement,
                                    )
                                "
                            />
                        </div>
                    </div>

                    <div class="grid gap-1">
                        <Label :for="`body-${index}`">Email body</Label>
                        <textarea
                            :id="`body-${index}`"
                            v-model="step.body"
                            rows="5"
                            :class="textareaClass"
                            required
                            placeholder="Write the message. Use tags from the panel on the right — click a field here first, then click a tag."
                            @focus="
                                registerMergeInsert(
                                    index,
                                    'body',
                                    $event.target as HTMLTextAreaElement,
                                )
                            "
                        />
                    </div>
                </div>
            </div>
        </div>

        <InputError :message="form.errors.steps" />

        <div class="flex flex-wrap gap-2 border-t pt-4">
            <Button type="button" size="sm" variant="outline" @click="addStep">
                <Plus /> Add email step
            </Button>
            <Button type="submit" size="sm" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                <Save v-else />
                Save sequence
            </Button>
            <Button
                v-if="sequence"
                type="button"
                size="sm"
                variant="ghost"
                class="text-destructive"
                @click="remove"
            >
                Delete sequence
            </Button>
        </div>
    </form>
</template>
