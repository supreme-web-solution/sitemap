<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyPreviewDomain,
    store as storePreviewDomain,
    verify as verifyPreviewDomainRoute,
} from '@/routes/branding/preview-domain';
import { update } from '@/routes/branding';

type DnsRecord = {
    type: string;
    name: string;
    value: string;
    purpose: string;
};

const props = defineProps<{
    canManage: boolean;
    canUsePreviewDomain: boolean;
    brand: { name: string; logoUrl: string; color: string; email: string };
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
    email: props.brand.email,
});

const previewDomainForm = useForm({
    hostname: props.previewDomain?.hostname ?? '',
});

const submit = () => {
    form.transform((data) => ({
        name: data.name || null,
        logo_url: data.logo_url || null,
        color: data.color || null,
        email: data.email || null,
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
                    <Label for="brand-email">Support email</Label>
                    <Input id="brand-email" v-model="form.email" type="email" :disabled="!canManage" />
                    <InputError :message="form.errors.email" />
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
        </form>

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
