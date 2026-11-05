<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, RefreshCw } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    destroy as destroyPreviewDomain,
    store as storePreviewDomain,
    verify as verifyPreviewDomainRoute,
} from '@/routes/branding/preview-domain';
import { update } from '@/routes/branding';
import { destroy as disconnectMailbox, store as storeMailbox, sync as syncMailbox } from '@/routes/mail-inbox';
import { edit as editProfile } from '@/routes/profile';

type DnsRecord = {
    type: string;
    name: string;
    value: string;
    purpose: string;
};

type MailboxConnection = {
    emailAddress: string;
    statusLabel: string;
    syncAvailable: boolean;
    lastSyncAt: string | null;
    lastSyncError: string | null;
    imapHost: string | null;
    imapPort: number | null;
    imapEncryption: string | null;
    imapUsername: string | null;
};

const props = defineProps<{
    canManage: boolean;
    canUsePreviewDomain: boolean;
    accountEmail: string;
    accountEmailOwner: string;
    brand: { name: string; logoUrl: string; color: string; email: string };
    mailbox: {
        imapSyncEnabled: boolean;
        connected: boolean;
        connection: MailboxConnection | null;
    };
    preview: { name: string; poweredBy: boolean };
    previewDomain: {
        hostname: string;
        verified: boolean;
        checkError: string | null;
        dnsRecords: DnsRecord[];
    } | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Branding', href: '#' }] },
});

const page = usePage();
const teamSlug = page.props.currentTeam?.slug ?? '';

const form = useForm({
    name: props.brand.name,
    logo_url: props.brand.logoUrl,
    color: props.brand.color,
});

const mailboxOpen = ref(false);

const mailboxForm = useForm({
    provider: 'imap' as const,
    email_address: props.mailbox.connection?.emailAddress ?? '',
    imap_host: props.mailbox.connection?.imapHost ?? '',
    imap_port: props.mailbox.connection?.imapPort ?? 993,
    imap_encryption: props.mailbox.connection?.imapEncryption ?? 'ssl',
    imap_username: props.mailbox.connection?.imapUsername ?? '',
    imap_password: '',
});

onMounted(() => {
    const openedFromLink = page.url.includes('mailbox=1');
    const hasMailboxError = Object.keys(mailboxForm.errors).length > 0;

    if (openedFromLink || hasMailboxError) {
        mailboxOpen.value = true;
    }
});

const previewDomainForm = useForm({
    hostname: props.previewDomain?.hostname ?? '',
});

const submit = () => {
    form.transform((data) => ({
        name: data.name || null,
        logo_url: data.logo_url || null,
        color: data.color || null,
    })).put(update.url(teamSlug), {
        preserveScroll: true,
    });
};

const savePreviewDomain = () => {
    previewDomainForm.post(storePreviewDomain.url(teamSlug), {
        preserveScroll: true,
    });
};

const verifyPreviewDomain = () => {
    router.post(verifyPreviewDomainRoute.url(teamSlug), {}, { preserveScroll: true });
};

const removePreviewDomain = () => {
    router.delete(destroyPreviewDomain.url(teamSlug), { preserveScroll: true });
};

const saveMailbox = () => {
    mailboxForm
        .transform((data) => ({
            ...data,
            imap_password: data.imap_password || null,
        }))
        .put(storeMailbox.url(teamSlug), {
            preserveScroll: true,
            onSuccess: () => {
                mailboxForm.reset('imap_password');
                mailboxOpen.value = false;
            },
        });
};

const queueMailboxSync = () => {
    router.post(syncMailbox.url(teamSlug), {}, { preserveScroll: true });
};

const disconnectInbox = () => {
    if (!confirm('Stop reading this mailbox? Replies already on leads stay there.')) {
        return;
    }

    router.delete(disconnectMailbox.url(teamSlug), { preserveScroll: true });
};
</script>

<template>
    <Head title="Branding" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Branding"
            description="Your clients see this on the client portal, proposals and invoice pages instead of the platform's name."
        />

        <form class="grid gap-4 panel p-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="brand-name">Agency name</Label>
                    <Input id="brand-name" v-model="form.name" :disabled="!canManage" placeholder="Your agency" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="brand-email">Email</Label>
                    <TooltipProvider :delay-duration="200">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <div>
                                    <Input
                                        id="brand-email"
                                        :model-value="accountEmail"
                                        type="email"
                                        disabled
                                    />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs">
                                This is {{ accountEmailOwner || 'the team owner' }}'s account email from registration. Outreach replies go here, and clients see it on proposals and invoices.
                                <a :href="editProfile().url" class="mt-1 block underline">Change it in Profile</a>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <p class="text-xs text-muted-foreground">
                        Hover the address to see where it comes from.
                    </p>
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="brand-logo">Logo URL (https)</Label>
                    <Input id="brand-logo" v-model="form.logo_url" type="url" :disabled="!canManage" placeholder="https://example.com/logo.png" />
                    <InputError :message="form.errors.logo_url" />
                </div>
                <div class="grid gap-2">
                    <Label for="brand-color">Accent colour</Label>
                    <div class="flex items-center gap-2">
                        <input id="brand-color" v-model="form.color" type="color" :disabled="!canManage" class="h-9 w-14 rounded border border-input" />
                        <span class="text-sm text-muted-foreground">{{ form.color }}</span>
                    </div>
                    <InputError :message="form.errors.color" />
                </div>
            </div>

            <p class="text-sm text-muted-foreground">
                <template v-if="preview.poweredBy">
                    Public pages show "Powered by {{ page.props.name }}". The
                    white-label feature removes it. Ask your administrator to
                    upgrade your plan.
                </template>
                <template v-else>
                    Public pages are white-labelled: no platform branding is shown.
                </template>
            </p>

            <div v-if="canManage">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Save branding
                </Button>
            </div>

            <div class="rounded-lg border border-border/70 bg-muted/30 p-4">
                <p class="text-sm font-medium">Read replies on the lead</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Optional. Leave this off if you read mail in Gmail or Outlook. Turn it on only when you want those replies copied onto the lead’s activity.
                </p>
                <p v-if="mailbox.connected && mailbox.connection" class="mt-3 text-sm">
                    Reading
                    <span class="font-medium">{{ mailbox.connection.emailAddress }}</span>
                    · {{ mailbox.connection.statusLabel }}
                </p>
                <p v-if="mailbox.connection?.lastSyncError" class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                    {{ mailbox.connection.lastSyncError }}
                </p>
                <p v-if="!mailbox.imapSyncEnabled" class="mt-2 text-xs text-muted-foreground">
                    Automatic checking is off on this server. You can still save the mailbox, then use Log reply on the lead until checking is enabled.
                </p>
                <div v-if="canManage" class="mt-3 flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" @click="mailboxOpen = true">
                        {{ mailbox.connected ? 'Mailbox settings' : 'Read replies in the app' }}
                    </Button>
                    <Button
                        v-if="mailbox.connected && mailbox.connection?.syncAvailable"
                        type="button"
                        variant="outline"
                        @click="queueMailboxSync"
                    >
                        <RefreshCw class="size-4" />
                        Check now
                    </Button>
                    <Button
                        v-if="mailbox.connected"
                        type="button"
                        variant="ghost"
                        @click="disconnectInbox"
                    >
                        Stop reading
                    </Button>
                </div>
            </div>
        </form>

        <Dialog v-model:open="mailboxOpen">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Read replies</DialogTitle>
                    <DialogDescription>
                        This can be a different mailbox from {{ accountEmail }}. Use it when that account’s mail settings are hard to get, such as Gmail. Replies still need to arrive in the mailbox you connect.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="saveMailbox">
                    <div class="grid gap-2">
                        <Label for="inbox-email">Mailbox email</Label>
                        <Input id="inbox-email" v-model="mailboxForm.email_address" type="email" autocomplete="email" />
                        <InputError :message="mailboxForm.errors.email_address" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="imap-host">IMAP host</Label>
                            <Input id="imap-host" v-model="mailboxForm.imap_host" placeholder="imap.example.com" />
                            <InputError :message="mailboxForm.errors.imap_host" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="imap-port">Port</Label>
                            <Input id="imap-port" v-model.number="mailboxForm.imap_port" type="number" />
                            <InputError :message="mailboxForm.errors.imap_port" />
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Encryption</Label>
                            <Select v-model="mailboxForm.imap_encryption">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ssl">SSL</SelectItem>
                                    <SelectItem value="tls">TLS</SelectItem>
                                    <SelectItem value="none">None</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="mailboxForm.errors.imap_encryption" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="imap-user">Username</Label>
                            <Input id="imap-user" v-model="mailboxForm.imap_username" />
                            <InputError :message="mailboxForm.errors.imap_username" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="imap-pass">Password</Label>
                        <Input
                            id="imap-pass"
                            v-model="mailboxForm.imap_password"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="mailbox.connected ? 'Leave blank to keep the current password' : ''"
                        />
                        <InputError :message="mailboxForm.errors.imap_password" />
                    </div>
                    <Button type="submit" :disabled="mailboxForm.processing">
                        <LoaderCircle v-if="mailboxForm.processing" class="animate-spin" />
                        Save mailbox
                    </Button>
                </form>
            </DialogContent>
        </Dialog>

        <section v-if="previewDomain" class="grid gap-4 panel p-5">
            <div>
                <h2 class="text-lg font-semibold">Preview domain</h2>
                <p class="text-sm text-muted-foreground">
                    Serve shareable website previews on your own hostname (for example
                    <span class="font-mono">previews.youragency.com</span>) instead of this app’s URL.
                </p>
            </div>

            <form v-if="canManage" class="grid gap-3" @submit.prevent="savePreviewDomain">
                <div class="grid gap-2">
                    <Label for="preview-hostname">Hostname</Label>
                    <Input
                        id="preview-hostname"
                        v-model="previewDomainForm.hostname"
                        placeholder="previews.youragency.com"
                    />
                    <InputError :message="previewDomainForm.errors.hostname" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="previewDomainForm.processing">Save hostname</Button>
                    <Button
                        v-if="previewDomain.hostname"
                        type="button"
                        variant="secondary"
                        @click="verifyPreviewDomain"
                    >
                        Check DNS
                    </Button>
                    <Button
                        v-if="previewDomain.hostname"
                        type="button"
                        variant="ghost"
                        @click="removePreviewDomain"
                    >
                        Remove
                    </Button>
                </div>
            </form>

            <p v-if="previewDomain.verified" class="text-sm text-emerald-600 dark:text-emerald-400">
                Verified — outreach and copy-link use this hostname.
            </p>
            <p v-else-if="previewDomain.checkError" class="text-sm text-destructive">
                {{ previewDomain.checkError }}
            </p>

            <div v-if="previewDomain.dnsRecords.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground">
                            <th class="pb-2 pr-3">Type</th>
                            <th class="pb-2 pr-3">Name</th>
                            <th class="pb-2 pr-3">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(record, index) in previewDomain.dnsRecords" :key="index">
                            <td class="py-1 pr-3 font-mono">{{ record.type }}</td>
                            <td class="py-1 pr-3 font-mono">{{ record.name }}</td>
                            <td class="py-1 pr-3 font-mono">{{ record.value }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
