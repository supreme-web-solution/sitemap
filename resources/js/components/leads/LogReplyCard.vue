<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, MessageCircleReply } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as mailInboxIndex } from '@/routes/mail-inbox';
import { store as storeInboundEmail } from '@/routes/leads/inbound-email';

const props = defineProps<{
    leadId: number;
    inboxConnected: boolean;
    imapSyncEnabled: boolean;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const form = useForm({
    subject: '',
    snippet: '',
});

const submit = () => {
    form.post(
        storeInboundEmail.url({
            current_team: teamSlug.value,
            lead: props.leadId,
        }),
        { preserveScroll: true, onSuccess: () => form.reset() },
    );
};

const textareaClass =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15';
</script>

<template>
    <section class="panel p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold">Log reply</h2>
            <Link
                :href="mailInboxIndex({ current_team: teamSlug }).url"
                class="text-xs font-medium text-primary hover:underline"
            >
                Inbox settings
            </Link>
        </div>

        <p class="mt-2 text-xs text-muted-foreground">
            <template v-if="imapSyncEnabled && inboxConnected">
                Replies sync automatically when they hit your connected mailbox.
                Use this if you need to record one manually.
            </template>
            <template v-else-if="inboxConnected">
                Inbox polling is off on this server — log replies here until sync
                is enabled.
            </template>
            <template v-else>
                Connect your reply mailbox under Inbox settings, or log replies
                here when a prospect answers outreach.
            </template>
        </p>

        <form class="mt-4 grid gap-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="reply-subject">Subject</Label>
                <Input id="reply-subject" v-model="form.subject" />
                <InputError :message="form.errors.subject" />
            </div>
            <div class="grid gap-2">
                <Label for="reply-snippet">What they said</Label>
                <textarea
                    id="reply-snippet"
                    v-model="form.snippet"
                    rows="4"
                    placeholder="Paste the key lines from their email"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.snippet" />
            </div>
            <Button
                type="submit"
                variant="outline"
                :disabled="form.processing"
                data-test="log-inbound-reply"
            >
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                <MessageCircleReply v-else />
                Log reply on timeline
            </Button>
        </form>
    </section>
</template>
