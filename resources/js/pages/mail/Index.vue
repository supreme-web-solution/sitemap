<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, RefreshCw } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
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
import { destroy, store, sync } from '@/routes/mail-inbox';
import { formatRelativeTime } from '@/lib/format';

type Connection = {
    provider: string;
    providerLabel: string;
    emailAddress: string;
    status: string;
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
    imapSyncEnabled: boolean;
    connected: boolean;
    connection: Connection | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Email inbox', href: '#' }] },
});

const page = usePage();
const teamSlug = () => page.props.currentTeam?.slug ?? '';

const form = useForm({
    provider: 'imap' as const,
    email_address: props.connection?.emailAddress ?? '',
    imap_host: props.connection?.imapHost ?? '',
    imap_port: props.connection?.imapPort ?? 993,
    imap_encryption: props.connection?.imapEncryption ?? 'ssl',
    imap_username: props.connection?.imapUsername ?? '',
    imap_password: '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        imap_password: data.imap_password || null,
    })).put(store.url(teamSlug()), {
        preserveScroll: true,
        onSuccess: () => form.reset('imap_password'),
    });
};

const queueSync = () => {
    router.post(sync.url(teamSlug()), {}, { preserveScroll: true });
};

const disconnect = () => {
    if (
        confirm(
            'Disconnect this inbox? Imported replies stay on your leads.',
        )
    ) {
        router.delete(destroy.url(teamSlug()), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Email inbox" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Email inbox"
            description="Connect the IMAP mailbox where prospects reply to your outreach. Replies appear on each lead timeline and can move the deal to Interested."
        />

        <p
            v-if="!imapSyncEnabled"
            class="panel p-4 text-sm text-amber-800 dark:text-amber-300"
        >
            Automatic inbox polling is not enabled on this server yet. Save your
            IMAP settings here for when your host turns sync on, and use
            <strong>Log reply</strong> on each lead profile in the meantime.
        </p>

        <div
            v-if="connected && connection"
            class="panel grid gap-2 p-4 text-sm"
        >
            <p>
                <span class="text-muted-foreground">Status:</span>
                {{ connection.statusLabel }}
            </p>
            <p v-if="connection.lastSyncAt">
                <span class="text-muted-foreground">Last sync:</span>
                {{ formatRelativeTime(connection.lastSyncAt) }}
            </p>
            <p
                v-if="connection.lastSyncError"
                class="text-amber-800 dark:text-amber-300"
            >
                {{ connection.lastSyncError }}
            </p>
            <div v-if="canManage && connection.syncAvailable" class="flex gap-2 pt-1">
                <Button type="button" size="sm" variant="outline" @click="queueSync">
                    <RefreshCw class="size-4" />
                    Sync now
                </Button>
                <Button type="button" size="sm" variant="ghost" @click="disconnect">
                    Disconnect
                </Button>
            </div>
        </div>

        <form
            v-if="canManage"
            class="grid gap-4 panel p-5"
            @submit.prevent="submit"
        >
            <input type="hidden" name="provider" value="imap" />

            <div class="grid gap-2">
                <Label for="inbox-email">Mailbox email</Label>
                <Input
                    id="inbox-email"
                    v-model="form.email_address"
                    type="email"
                    autocomplete="email"
                />
                <InputError :message="form.errors.email_address" />
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="imap-host">IMAP host</Label>
                    <Input id="imap-host" v-model="form.imap_host" placeholder="imap.example.com" />
                    <InputError :message="form.errors.imap_host" />
                </div>
                <div class="grid gap-2">
                    <Label for="imap-port">Port</Label>
                    <Input
                        id="imap-port"
                        v-model.number="form.imap_port"
                        type="number"
                    />
                    <InputError :message="form.errors.imap_port" />
                </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Encryption</Label>
                    <Select v-model="form.imap_encryption">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="ssl">SSL</SelectItem>
                            <SelectItem value="tls">TLS</SelectItem>
                            <SelectItem value="none">None</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.imap_encryption" />
                </div>
                <div class="grid gap-2">
                    <Label for="imap-user">Username</Label>
                    <Input id="imap-user" v-model="form.imap_username" />
                    <InputError :message="form.errors.imap_username" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="imap-pass">Password</Label>
                <Input
                    id="imap-pass"
                    v-model="form.imap_password"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="
                        connected ? 'Leave blank to keep current' : ''
                    "
                />
                <InputError :message="form.errors.imap_password" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Save inbox settings
            </Button>
        </form>
    </div>
</template>
