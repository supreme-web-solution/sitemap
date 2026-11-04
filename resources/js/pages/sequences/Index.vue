<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Mail, Plus, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import PaginationBar from '@/components/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import MergeFieldsPanel from '@/components/outreach/MergeFieldsPanel.vue';
import SequenceAiDialog from '@/components/outreach/SequenceAiDialog.vue';
import SequenceForm from '@/components/outreach/SequenceForm.vue';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { provideSequenceMergeTarget } from '@/composables/useSequenceMergeFields';
import { index as sequencesIndex } from '@/routes/sequences';
import type { MergeField, Paginated, Team } from '@/types';
import type { SequenceAiDraft } from '@/types/outreach';

type Sequence = {
    id: number;
    name: string;
    isActive: boolean;
    enrollmentCount: number;
    steps: {
        id: number;
        position: number;
        delayDays: number;
        subject: string;
        body: string;
    }[];
};

defineProps<{
    sequences: Paginated<Sequence>;
    mergeFields: MergeField[];
    sequenceAiAvailable: boolean;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Follow-up sequences',
                href: props.currentTeam
                    ? sequencesIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

provideSequenceMergeTarget();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const creating = ref(false);
const aiDialogOpen = ref(false);
const aiSeed = ref<SequenceAiDraft | null>(null);
const createFormKey = ref(0);

const onAiGenerated = (draft: SequenceAiDraft) => {
    aiSeed.value = draft;
    createFormKey.value += 1;
    creating.value = true;
};
</script>

<template>
    <Head title="Follow-up sequences" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Follow-up sequences"
                description="Timed emails after you enroll a lead on their profile — e.g. day 1, 3, 6, and 10. Stops when they reply or the deal closes."
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    data-test="sequence-ai-open"
                    @click="aiDialogOpen = true"
                >
                    <Sparkles /> Draft with AI
                </Button>
                <Button size="sm" @click="creating = !creating">
                    <Plus /> New sequence
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="flex min-w-0 flex-col gap-5">
                <SequenceForm
                    v-if="creating"
                    :key="createFormKey"
                    :seed="aiSeed"
                    @saved="
                        creating = false;
                        aiSeed = null;
                    "
                />
                <EmptyState
                    v-else-if="!sequences.data.length"
                    title="No follow-up sequences yet"
                    description="Enroll leads after first contact and send timed emails on day 1, 3, 6, and 10 — until they reply, win, or opt out."
                    :icon="Mail"
                >
                    <Button size="sm" @click="aiDialogOpen = true">
                        <Sparkles /> Draft with AI
                    </Button>
                    <Button size="sm" variant="outline" @click="creating = true">
                        <Plus /> Blank sequence
                    </Button>
                </EmptyState>
                <template v-else>
                    <SequenceForm
                        v-for="sequence in sequences.data"
                        :key="sequence.id"
                        :sequence="sequence"
                    />
                    <PaginationBar :paginator="sequences" />
                </template>
            </div>

            <MergeFieldsPanel context="sequence" :merge-fields="mergeFields" />
        </div>

        <SequenceAiDialog
            v-model:open="aiDialogOpen"
            :team-slug="teamSlug"
            :available="sequenceAiAvailable"
            @generated="onAiGenerated"
        />
    </div>
</template>
