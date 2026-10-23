<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import EmailTemplateForm from '@/components/outreach/EmailTemplateForm.vue';
import { Button } from '@/components/ui/button';
import { index as emailTemplatesIndex } from '@/routes/email-templates';
import type { EmailTemplate, MergeField, Team } from '@/types';

defineProps<{
    templates: EmailTemplate[];
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

const creating = ref(false);
const { copy, copied } = useClipboard();
const copiedKey = ref<string | null>(null);

const copyField = (key: string) => {
    copy(`{{${key}}}`);
    copiedKey.value = key;
};
</script>

<template>
    <Head title="Email Studio" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Email Studio"
                description="Reusable outreach messages. Merge fields are filled in for each business when you send."
            />
            <Button size="sm" @click="creating = !creating">
                <Plus /> New template
            </Button>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-5">
                <EmailTemplateForm v-if="creating" @saved="creating = false" />
                <EmailTemplateForm
                    v-for="template in templates"
                    :key="template.id"
                    :template="template"
                />
            </div>

            <aside class="panel p-5 xl:sticky xl:top-24">
                <h2 class="text-base font-semibold">Merge fields</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Click a field to copy it, then paste it into a subject or
                    message.
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
                            <span
                                v-if="copied && copiedKey === field.key"
                                class="ml-2 text-xs text-emerald-600"
                                >Copied</span
                            >
                            <span class="block text-xs text-muted-foreground">{{
                                field.description
                            }}</span>
                        </button>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</template>
