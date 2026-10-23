<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { MessageSquareText } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as messagingIndex } from '@/routes/messaging';
import { destroy as removeConsent, store as recordConsent } from '@/routes/leads/consent';
import { store as sendMessage } from '@/routes/leads/messages';
import type { LeadMessaging } from '@/types';

const props = defineProps<{ leadId: number; messaging: LeadMessaging }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const args = computed(() => ({ current_team: teamSlug.value, lead: props.leadId }));

const form = useForm({
    channel: (props.messaging.smsReady ? 'sms' : 'whatsapp') as 'sms' | 'whatsapp',
    body: '',
    template_name: '',
    template_language: 'en_US',
    template_params: '',
});

const needsTemplate = computed(() => form.channel === 'whatsapp' && !props.messaging.hasWhatsAppChat);

const canSend = computed(
    () =>
        props.messaging.connected &&
        props.messaging.consentAt !== null &&
        props.messaging.optedOutAt === null &&
        props.messaging.phone !== null &&
        (props.messaging.smsReady || props.messaging.whatsappReady),
);

const submit = () => {
    form.transform((data) => ({
        body: data.body || null,
        template_name: needsTemplate.value ? data.template_name : null,
        template_language: needsTemplate.value ? data.template_language : null,
        template_params: needsTemplate.value
            ? data.template_params.split(',').map((value) => value.trim()).filter(Boolean)
            : [],
    })).post(sendMessage.url(args.value), {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};

const statusClass: Record<string, string> = {
    queued: 'text-amber-600',
    sent: 'text-muted-foreground',
    delivered: 'text-emerald-600',
    failed: 'text-rose-600',
    received: 'text-sky-600',
};
</script>

<template>
    <section class="panel p-5" data-test="lead-messages-card">
        <h2 class="flex items-center gap-2 text-base font-semibold">
            <MessageSquareText class="size-4" /> Text &amp; WhatsApp
        </h2>

        <p v-if="!messaging.available" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
            Messaging is not included in your plan.
        </p>

        <p v-else-if="!messaging.connected" class="mt-3 text-sm text-muted-foreground">
            Connect your Zernio account to text and WhatsApp leads.
            <Link :href="messagingIndex.url(teamSlug)" class="underline">Set it up</Link>
        </p>

        <p v-else-if="!messaging.phone" class="mt-3 text-sm text-muted-foreground">
            Add a valid phone number to this lead to message them.
        </p>

        <template v-else>
            <p v-if="messaging.optedOutAt" class="mt-3 text-sm text-rose-600">
                This contact opted out. Messages are blocked until they reply START.
            </p>

            <div v-else-if="!messaging.consentAt" class="mt-3 grid gap-2 text-sm">
                <p class="text-muted-foreground">
                    Only message people who agreed to hear from you. Confirm to unlock sending.
                </p>
                <Button
                    size="sm"
                    variant="outline"
                    @click="router.put(recordConsent.url(args), { confirmed: true }, { preserveScroll: true })"
                >
                    They agreed to receive messages
                </Button>
            </div>

            <ol v-if="messaging.messages.length" class="mt-3 grid max-h-72 gap-2 overflow-y-auto text-sm">
                <li
                    v-for="message in messaging.messages"
                    :key="message.id"
                    class="max-w-[85%] rounded-lg px-3 py-2"
                    :class="message.direction === 'in' ? 'bg-muted' : 'ml-auto bg-primary/10'"
                >
                    <p class="whitespace-pre-wrap break-words">{{ message.body }}</p>
                    <p class="mt-1 text-[11px]" :class="statusClass[message.status]">
                        {{ message.channel === 'sms' ? 'SMS' : 'WhatsApp' }} · {{ message.status }}
                        <span v-if="message.error"> · {{ message.error }}</span>
                    </p>
                </li>
            </ol>

            <form v-if="canSend" class="mt-3 grid gap-2" @submit.prevent="submit">
                <div v-if="messaging.smsReady && messaging.whatsappReady" class="flex gap-2 text-sm">
                    <label class="flex items-center gap-1"><input v-model="form.channel" type="radio" value="sms" /> SMS</label>
                    <label class="flex items-center gap-1"><input v-model="form.channel" type="radio" value="whatsapp" /> WhatsApp</label>
                </div>

                <template v-if="needsTemplate">
                    <p class="text-xs text-muted-foreground">
                        WhatsApp chats start with a template you got approved. Replies open a 24-hour window for free text.
                    </p>
                    <Input v-model="form.template_name" placeholder="Template name" aria-label="Template name" />
                    <InputError :message="form.errors.template_name" />
                    <Input v-model="form.template_language" placeholder="Language, e.g. en_US" aria-label="Template language" />
                    <Input v-model="form.template_params" placeholder="Variables, comma separated" aria-label="Template variables" />
                </template>
                <template v-else>
                    <textarea
                        v-model="form.body"
                        rows="3"
                        maxlength="1000"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        placeholder="Write a message"
                        aria-label="Message"
                    />
                    <InputError :message="form.errors.body" />
                </template>
                <InputError :message="form.errors.channel" />

                <div class="flex items-center gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">Send</Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="router.delete(removeConsent.url(args), { preserveScroll: true })"
                    >
                        Remove consent
                    </Button>
                </div>
            </form>
        </template>
    </section>
</template>