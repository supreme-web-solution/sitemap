<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import PaginationBar from '@/components/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import EmailTemplateForm from '@/components/outreach/EmailTemplateForm.vue';
import MergeFieldsPanel from '@/components/outreach/MergeFieldsPanel.vue';
import { Button } from '@/components/ui/button';
import { provideMergeFieldTarget } from '@/composables/useMergeFieldTarget';
import { index as emailTemplatesIndex } from '@/routes/email-templates';
import type { EmailTemplate, MergeField, Paginated, Team } from '@/types';

defineProps<{
    templates: Paginated<EmailTemplate>;
    mergeFields: MergeField[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Email Studio',
                href: props.currentTeam
                    ? emailTemplatesIndex({
                          current_team: props.currentTeam.slug,
                      })
                    : '/',
            },
        ],
    }),
});

provideMergeFieldTarget();

const creating = ref(false);
</script>

<template>
    <Head title="Email Studio" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Email Studio"
                description="Reusable outreach messages for the lead list and pipeline. Tags on the right personalize each send."
            />
            <Button size="sm" @click="creating = !creating">
                <Plus /> New template
            </Button>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="flex min-w-0 flex-col gap-5">
                <EmailTemplateForm v-if="creating" @saved="creating = false" />
                <EmailTemplateForm
                    v-for="template in templates.data"
                    :key="template.id"
                    :template="template"
                />
                <PaginationBar :paginator="templates" />
            </div>

            <MergeFieldsPanel context="template" :merge-fields="mergeFields" />
        </div>
    </div>
</template>
