<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Save, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
        steps: (Step & { id: number; position: number })[];
    };
}>();

const emit = defineEmits<{ saved: [] }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

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
    <form class="panel grid gap-4 p-5" @submit.prevent="submit">
        <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
            <div class="grid gap-2">
                <Label>Sequence name</Label>
                <Input v-model="form.name" required maxlength="80" />
                <InputError :message="form.errors.name" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="size-4" />
                Active
            </label>
        </div>

        <div
            v-for="(step, index) in form.steps"
            :key="index"
            class="grid gap-2 rounded-xl border p-3"
        >
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-medium">Step {{ index + 1 }}</p>
                <Button
                    v-if="form.steps.length > 1"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="removeStep(index)"
                >
                    <Trash2 />
                </Button>
            </div>
            <div class="grid gap-2 sm:grid-cols-[8rem_1fr]">
                <div class="grid gap-1">
                    <Label>Wait (days)</Label>
                    <Input
                        v-model.number="step.delay_days"
                        type="number"
                        min="0"
                        max="60"
                    />
                </div>
                <div class="grid gap-1">
                    <Label>Subject</Label>
                    <Input v-model="step.subject" maxlength="150" required />
                </div>
            </div>
            <textarea
                v-model="step.body"
                rows="4"
                :class="textareaClass"
                required
            />
        </div>

        <div class="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" @click="addStep">
                <Plus /> Add step
            </Button>
            <Button type="submit" size="sm" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                <Save v-else />
                Save
            </Button>
            <Button
                v-if="sequence"
                type="button"
                size="sm"
                variant="ghost"
                class="text-destructive"
                @click="remove"
            >
                Delete
            </Button>
        </div>
    </form>
</template>
