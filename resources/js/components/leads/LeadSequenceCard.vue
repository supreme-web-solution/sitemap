<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    destroy as stopEnrollment,
    store as enroll,
} from '@/routes/leads/enrollments';
import { formatRelativeTime } from '@/lib/format';

type Enrollment = {
    id: number;
    name: string;
    status: string;
    nextStepAt: string | null;
    stoppedReason: string | null;
};

const props = defineProps<{
    leadId: number;
    sequences: { id: number; name: string }[];
    enrollments: Enrollment[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const form = useForm({ sequence_id: props.sequences[0]?.id ?? '' });

const submit = () =>
    form.post(
        enroll.url({ current_team: teamSlug.value, lead: props.leadId }),
        { preserveScroll: true, only: ['enrollments'] },
    );

const stop = (enrollment: Enrollment) =>
    router.delete(
        stopEnrollment.url({
            current_team: teamSlug.value,
            lead: props.leadId,
            enrollment: enrollment.id,
        }),
        { preserveScroll: true, only: ['enrollments'] },
    );
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Follow-up sequence</h2>
        <form v-if="sequences.length" class="mt-4 grid gap-2" @submit.prevent="submit">
            <Label for="sequence">Enroll in</Label>
            <select
                id="sequence"
                v-model="form.sequence_id"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
            >
                <option v-for="sequence in sequences" :key="sequence.id" :value="sequence.id">
                    {{ sequence.name }}
                </option>
            </select>
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                Enroll
            </Button>
        </form>
        <p v-else class="mt-2 text-sm text-muted-foreground">
            Create a sequence in Follow-up sequences first.
        </p>
        <ul class="mt-4 grid gap-2 text-sm">
            <li
                v-for="enrollment in enrollments"
                :key="enrollment.id"
                class="rounded-xl border px-3 py-2"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium">{{ enrollment.name }}</span>
                    <span class="text-xs capitalize text-muted-foreground">{{
                        enrollment.status
                    }}</span>
                </div>
                <p v-if="enrollment.nextStepAt" class="text-xs text-muted-foreground">
                    Next {{ formatRelativeTime(enrollment.nextStepAt) }}
                </p>
                <p v-if="enrollment.stoppedReason" class="text-xs text-muted-foreground">
                    {{ enrollment.stoppedReason }}
                </p>
                <Button
                    v-if="enrollment.status === 'active'"
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="mt-1 h-7 px-2"
                    @click="stop(enrollment)"
                >
                    Stop
                </Button>
            </li>
        </ul>
    </section>
</template>
