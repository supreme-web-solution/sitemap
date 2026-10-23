<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/messaging';

type Account = {
    smsFrom: string | null;
    whatsappAccountId: string | null;
    timezone: string;
    quietStartHour: number;
    quietEndHour: number;
    webhookUrl: string;
    webhookSecret: string | null;
};

const props = defineProps<{
    canManage: boolean;
    available: boolean;
    connected: boolean;
    account: Account | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Messaging', href: '#' }] },
});

const page = usePage();
const teamSlug = () => page.props.currentTeam?.slug ?? '';

const form = useForm({
    api_key: '',
    sms_from: props.account?.smsFrom ?? '',
    whatsapp_account_id: props.account?.whatsappAccountId ?? '',
    timezone: props.account?.timezone ?? 'America/New_York',
    quiet_start_hour: props.account?.quietStartHour ?? 21,
    quiet_end_hour: props.account?.quietEndHour ?? 8,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        api_key: data.api_key || null,
        sms_from: data.sms_from || null,
        whatsapp_account_id: data.whatsapp_account_id || null,
    })).put(store.url(teamSlug()), {
        preserveScroll: true,
        onSuccess: () => form.reset('api_key'),
    });
};

const disconnect = () => {
    if (confirm('Disconnect messaging? Past conversations stay on each lead.')) {
        router.delete(destroy.url(teamSlug()), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Messaging" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Messaging"
            description="Text and WhatsApp leads from your own Zernio account. Messages only go to contacts who agreed, STOP replies are honoured, and nothing sends during quiet hours."
        />

        <p v-if="!available" class="panel p-4 text-sm text-amber-700 dark:text-amber-300">
            Messaging is not included in your plan. Ask your administrator to upgrade it.
        </p>

        <form v-else class="grid gap-4 panel p-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="zernio-key">Zernio API key</Label>
                <Input
                    id="zernio-key"
                    v-model="form.api_key"
                    type="password"
                    autocomplete="off"
                    :disabled="!canManage"
                    :placeholder="connected ? 'Saved. Leave blank to keep it.' : 'Paste your key'"
                />
                <InputError :message="form.errors.api_key" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="sms-from">SMS number (E.164)</Label>
                    <Input id="sms-from" v-model="form.sms_from" :disabled="!canManage" placeholder="+15551234567" />
                    <InputError :message="form.errors.sms_from" />
                </div>
                <div class="grid gap-2">
                    <Label for="wa-account">WhatsApp account ID</Label>
                    <Input id="wa-account" v-model="form.whatsapp_account_id" :disabled="!canManage" />
                    <InputError :message="form.errors.whatsapp_account_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="tz">Time zone for quiet hours</Label>
                    <Input id="tz" v-model="form.timezone" :disabled="!canManage" placeholder="America/New_York" />
                    <InputError :message="form.errors.timezone" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="quiet-start">Quiet from (hour)</Label>
                        <Input id="quiet-start" v-model.number="form.quiet_start_hour" type="number" min="0" max="23" :disabled="!canManage" />
                        <InputError :message="form.errors.quiet_start_hour" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quiet-end">Until (hour)</Label>
                        <Input id="quiet-end" v-model.number="form.quiet_end_hour" type="number" min="0" max="23" :disabled="!canManage" />
                        <InputError :message="form.errors.quiet_end_hour" />
                    </div>
                </div>
            </div>

            <div v-if="account" class="grid gap-2 rounded-md bg-muted/50 p-3 text-sm">
                <p class="font-medium">Webhook</p>
                <p class="text-muted-foreground">
                    In Zernio, add this URL as a webhook for message events and use the secret below to sign them.
                </p>
                <code class="break-all text-xs">{{ account.webhookUrl }}</code>
                <code v-if="account.webhookSecret" class="break-all text-xs">Secret: {{ account.webhookSecret }}</code>
            </div>

            <div v-if="canManage" class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ connected ? 'Save' : 'Connect' }}
                </Button>
                <Button v-if="connected" type="button" variant="ghost" @click="disconnect">Disconnect</Button>
            </div>
        </form>
    </div>
</template>