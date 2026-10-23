<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import SequenceForm from '@/components/outreach/SequenceForm.vue';
import { Button } from '@/components/ui/button';
import { index as sequencesIndex } from '@/routes/sequences';
import type { MergeField, Team } from '@/types';

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
    sequences: Sequence[];
    mergeFields: MergeField[];
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

const creating = ref(false);
const { copy } = useClipboard();
const copiedKey = ref<string | null>(null);

const copyField = (key: string) => {
    copy(`{{${key}}}`);
    copiedKey.value = key;
};
</script>

<template>
    <Head title="Follow-up sequences" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Follow-up sequences"
                description="Automatic emails on day 1, 3, 6 and 10 after you enroll a lead. Sending stops if the deal is won or lost."
            />
            <Button size="sm" @click="creating = !creating">
                <Plus /> New sequence
            </Button>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-5">
                <SequenceForm v-if="creating" @saved="creating = false" />
                <SequenceForm
                    v-for="sequence in sequences"
                    :key="sequence.id"
                    :sequence="sequence"
                />
            </div>

            <aside class="panel p-5 xl:sticky xl:top-24">
                <h2 class="text-base font-semibold">Merge fields</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Click a field to copy it into a step.
                </p>
                <ul class="mt-4 flex flex-col gap-2">
                    <li v-for="field in mergeFields" :key="field.key">
                        <button
                            type="button"
                            class="w-full rounded-xl border px-3 py-2 text-left transition hover:border-primary/40 hover:bg-accent/50"
                            @click="copyField(field.key)"
                        >
                            <code class="text-sm font-medium text-primary"
                                >&#123;&#123;{{ field.key }}&#125;&#125;</code
                            >
                            <span class="mt-0.5 block text-xs text-muted-foreground">{{
                                field.description
                            }}</span>
                        </button>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</template>
