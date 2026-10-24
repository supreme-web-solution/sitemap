<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, LoaderCircle, PhoneCall, Send } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as emailTemplatesIndex } from '@/routes/email-templates';
import { index as mailInboxIndex } from '@/routes/mail-inbox';
import { store as storeInboundEmail } from '@/routes/leads/inbound-email';
import { store as storeOutreach } from '@/routes/leads/outreach';
import type { EmailTemplate, LeadProfile } from '@/types';

const props = defineProps<{
    lead: LeadProfile;
    templates: EmailTemplate[];
    mergeValues: Record<string, string>;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const render = (text: string): string =>
    text.replace(
        /\{\{\s*(\w+)\s*\}\}/g,
        (token, key: string) => props.mergeValues[key] ?? token,
    );

const selectedId = ref<string>(String(props.templates[0]?.id ?? ''));

const form = useForm({
    mode: 'send' as 'send' | 'log',
    subject: '',
    body: '',
});

const replyForm = useForm({
    subject: '',
    snippet: '',
});

const applyTemplate = () => {
    const template = props.templates.find(
        (candidate) => String(candidate.id) === selectedId.value,
    );

    form.subject = template ? render(template.subject) : '';
    form.body = template ? render(template.body) : '';
};

watch(selectedId, applyTemplate, { immediate: true });

const needsPreview = computed(
    () =>
        props.mergeValues.preview_url === '' &&
        /preview_url/.test(
            props.templates.find(
                (template) => String(template.id) === selectedId.value,
            )?.body ?? '',
        ),
);

const submit = (mode: 'send' | 'log') => {
    form.mode = mode;

    form.post(
        storeOutreach.url({
            current_team: teamSlug.value,
            lead: props.lead.id,
        }),
        { preserveScroll: true },
    );
};

const { copy, copied } = useClipboard();

const textareaClass =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15';
</script>

<template>
    <section class="panel p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold">Outreach</h2>
            <Link
                :href="emailTemplatesIndex({ current_team: teamSlug })"
                class="text-xs font-medium text-primary hover:underline"
                >Edit templates</Link
            >
        </div>

        <div class="mt-4 grid gap-4">
            <div class="grid gap-2">
                <Label>Template</Label>
                <Select v-model="selectedId">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Choose a template" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="template in templates"
                            :key="template.id"
                            :value="String(template.id)"
                        >
                            {{ template.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <p
                v-if="needsPreview"
                class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
            >
                This template links to the website preview, but no website has
                been built yet. Build it first so the link is filled in.
            </p>

            <div class="grid gap-2">
                <Label for="outreach-subject">Subject</Label>
                <Input id="outreach-subject" v-model="form.subject" />
                <InputError :message="form.errors.subject" />
            </div>

            <div class="grid gap-2">
                <Label for="outreach-body">Message</Label>
                <textarea
                    id="outreach-body"
                    v-model="form.body"
                    rows="9"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.body" />
                <InputError :message="form.errors.mode" />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    :disabled="form.processing || !lead.email"
                    :title="
                        lead.email
                            ? `Send to ${lead.email}`
                            : 'Add an email address first'
                    "
                    data-test="send-outreach"
                    @click="submit('send')"
                >
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Send v-else />
                    Send email
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    data-test="log-outreach"
                    @click="submit('log')"
                >
                    <PhoneCall /> Log as contacted
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    @click="copy(`${form.subject}\n\n${form.body}`)"
                >
                    <Check v-if="copied" class="text-emerald-600" />
                    <Copy v-else />
                    {{ copied ? 'Copied' : 'Copy' }}
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Sending sets the stage to Contacted and schedules a follow-up in
                3 days. Connect your
                <Link
                    :href="mailInboxIndex({ current_team: teamSlug })"
                    class="font-medium text-primary hover:underline"
                    >email inbox</Link
                >
                to import replies automatically, or log one below.
            </p>

            <div class="mt-2 grid gap-3 border-t border-border pt-4">
                <h3 class="text-sm font-medium">Log email reply</h3>
                <div class="grid gap-2">
                    <Label for="reply-subject">Subject</Label>
                    <Input id="reply-subject" v-model="replyForm.subject" />
                    <InputError :message="replyForm.errors.subject" />
                </div>
                <div class="grid gap-2">
                    <Label for="reply-snippet">Reply excerpt</Label>
                    <textarea
                        id="reply-snippet"
                        v-model="replyForm.snippet"
                        rows="4"
                        :class="textareaClass"
                    />
                    <InputError :message="replyForm.errors.snippet" />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="replyForm.processing"
                    data-test="log-inbound-email"
                    @click="
                        replyForm.post(
                            storeInboundEmail.url({
                                current_team: teamSlug,
                                lead: lead.id,
                            }),
                            { preserveScroll: true },
                        )
                    "
                >
                    <LoaderCircle
                        v-if="replyForm.processing"
                        class="animate-spin"
                    />
                    Log reply on timeline
                </Button>
            </div>
        </div>
    </section>
</template>
