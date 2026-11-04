<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { CircleCheck, Mail } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
const form = useForm({
    sequence_id: props.sequences[0]?.id ?? (0 as number),
});

const sequenceSelect = computed({
    get: () => String(form.sequence_id),
    set: (value: string) => {
        form.sequence_id = Number(value);
    },
});

const activeEnrollments = computed(() =>
    props.enrollments.filter((enrollment) => enrollment.status === 'active'),
);

const pastEnrollments = computed(() =>
    props.enrollments.filter((enrollment) => enrollment.status !== 'active'),
);

const submit = () =>
    form.post(
        enroll.url({ current_team: teamSlug.value, lead: props.leadId }),
        {
            preserveScroll: true,
            only: ['enrollments'],
            onSuccess: () => {
                const name =
                    props.sequences.find(
                        (sequence) => sequence.id === form.sequence_id,
                    )?.name ?? 'sequence';
                toast.success(`Enrolled in ${name}`);
            },
            onError: () => {
                const message =
                    form.errors.sequence_id ||
                    'Could not enroll. Add an email on this lead first.';
                toast.error(message);
            },
        },
    );

const stop = (enrollment: Enrollment) =>
    router.delete(
        stopEnrollment.url({
            current_team: teamSlug.value,
            lead: props.leadId,
            enrollment: enrollment.id,
        }),
        {
            preserveScroll: true,
            only: ['enrollments'],
            onSuccess: () => toast.success('Removed from sequence'),
        },
    );
</script>

<template>
    <section class="panel p-5">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold">Follow-up sequence</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Timed emails send automatically while enrolled (queue).
                </p>
            </div>
            <Badge
                v-if="activeEnrollments.length"
                variant="secondary"
                class="shrink-0 bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
            >
                <CircleCheck class="size-3" />
                Enrolled
            </Badge>
        </div>

        <div v-if="activeEnrollments.length" class="mt-4 grid gap-2">
            <p class="text-xs font-medium text-muted-foreground">
                Active ({{ activeEnrollments.length }})
            </p>
            <ul class="grid gap-2 text-sm">
                <li
                    v-for="enrollment in activeEnrollments"
                    :key="enrollment.id"
                    class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 px-3 py-2.5 dark:border-emerald-900 dark:bg-emerald-950/30"
                    data-test="active-enrollment"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium">{{ enrollment.name }}</p>
                            <p
                                v-if="enrollment.nextStepAt"
                                class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <Mail class="size-3.5 shrink-0" />
                                Next email
                                {{ formatRelativeTime(enrollment.nextStepAt) }}
                            </p>
                        </div>
                        <Badge
                            variant="secondary"
                            class="capitalize shrink-0 bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-100"
                        >
                            {{ enrollment.status }}
                        </Badge>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="mt-2 h-7 border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="stop(enrollment)"
                    >
                        Remove from sequence
                    </Button>
                </li>
            </ul>
        </div>

        <p
            v-else-if="sequences.length"
            class="mt-4 text-sm text-muted-foreground"
        >
            Not enrolled yet — pick a sequence below.
        </p>

        <form
            v-if="sequences.length"
            class="mt-4 grid gap-2 border-t pt-4"
            @submit.prevent="submit"
        >
            <Label for="sequence">{{
                activeEnrollments.length ? 'Enroll in another' : 'Enroll in'
            }}</Label>
            <Select v-model="sequenceSelect">
                <SelectTrigger id="sequence" class="w-full" aria-label="Follow-up sequence">
                    <SelectValue placeholder="Choose sequence" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="sequence in sequences"
                        :key="sequence.id"
                        :value="String(sequence.id)"
                    >
                        {{ sequence.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.sequence_id" />
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                {{ form.processing ? 'Enrolling…' : 'Enroll' }}
            </Button>
        </form>
        <p v-else class="mt-4 text-sm text-muted-foreground">
            Create a sequence in Follow-up sequences first.
        </p>

        <ul v-if="pastEnrollments.length" class="mt-4 grid gap-2 border-t pt-4 text-sm">
            <p class="text-xs font-medium text-muted-foreground">Past</p>
            <li
                v-for="enrollment in pastEnrollments"
                :key="enrollment.id"
                class="rounded-xl border px-3 py-2 text-muted-foreground"
            >
                <div class="flex items-center justify-between gap-2">
                    <span>{{ enrollment.name }}</span>
                    <span class="text-xs capitalize">{{ enrollment.status }}</span>
                </div>
                <p v-if="enrollment.stoppedReason" class="mt-0.5 text-xs">
                    {{ enrollment.stoppedReason }}
                </p>
            </li>
        </ul>
    </section>
</template>
