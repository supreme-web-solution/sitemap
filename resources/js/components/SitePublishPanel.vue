<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    Copy,
    ExternalLink,
    Globe,
    LoaderCircle,
    Plus,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRelativeTime } from '@/lib/format';
import {
    destroy as removeDomain,
    store as addDomain,
    verify as verifyDomain,
} from '@/routes/sites/domains';
import {
    destroy as unpublishSite,
    store as publishSite,
} from '@/routes/sites/publish';
import type { SiteDomain, SitePublishing } from '@/types';

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    publishing: SitePublishing;
}>();

const page = usePage();
const publishError = computed(
    () => (page.props.errors as Record<string, string | undefined>).publish,
);

const form = useForm({ hostname: '' });
const { copy } = useClipboard();

const routeArgs = () => ({ current_team: props.teamSlug, site: props.siteId });

const add = () =>
    form.post(addDomain.url(routeArgs()), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

const check = (domain: SiteDomain) =>
    router.post(
        verifyDomain.url({ ...routeArgs(), domain: domain.id }),
        {},
        { preserveScroll: true },
    );

const remove = (domain: SiteDomain) => {
    if (!confirm(`Remove ${domain.hostname}?`)) {
        return;
    }

    router.delete(removeDomain.url({ ...routeArgs(), domain: domain.id }), {
        preserveScroll: true,
    });
};

const togglePublish = () => {
    if (
        props.publishing.isPublished &&
        !confirm(
            'Take this website offline? Visitors will see a not found page.',
        )
    ) {
        return;
    }

    const url = props.publishing.isPublished
        ? unpublishSite.url(routeArgs())
        : publishSite.url(routeArgs());

    router[props.publishing.isPublished ? 'delete' : 'post'](
        url,
        {},
        { preserveScroll: true },
    );
};

const copyValue = async (value: string) => {
    await copy(value);
    toast.success('Copied');
};
</script>

<template>
    <section class="panel p-5" data-test="publish-panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <Globe class="size-4" /> Publish on your own domain
                    <span
                        :class="[
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            publishing.isPublished
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'bg-muted text-muted-foreground',
                        ]"
                    >
                        {{ publishing.isPublished ? 'Live' : 'Not published' }}
                    </span>
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Connect a domain you own and this website is served from it
                    over HTTPS, with its own sitemap and search settings. The
                    preview link stays private to search engines.
                </p>
                <p
                    v-if="publishing.isPublished && publishing.liveUrl"
                    class="mt-2 text-sm"
                >
                    <a
                        :href="publishing.liveUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 font-medium underline"
                    >
                        {{ publishing.liveUrl }}
                        <ExternalLink class="size-3.5" />
                    </a>
                    <span
                        v-if="publishing.publishedAt"
                        class="text-muted-foreground"
                    >
                        · live since
                        {{ formatRelativeTime(publishing.publishedAt) }}
                    </span>
                </p>
            </div>
            <Button
                type="button"
                :variant="publishing.isPublished ? 'outline' : 'default'"
                :disabled="!publishing.isPublished && !publishing.canPublish"
                data-test="toggle-publish"
                @click="togglePublish"
            >
                {{ publishing.isPublished ? 'Unpublish' : 'Publish website' }}
            </Button>
        </div>

        <InputError class="mt-2" :message="publishError" />

        <p
            v-if="!publishing.dnsConfigured"
            class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
        >
            The server address for customer domains is not configured yet, so
            only the ownership record can be checked. Set
            <code>SITES_CNAME_TARGET</code> or <code>SITES_SERVER_IP</code>.
        </p>

        <ul v-if="publishing.domains.length" class="mt-4 grid gap-3">
            <li
                v-for="domain in publishing.domains"
                :key="domain.id"
                class="rounded-xl border p-3"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ domain.hostname }}</span>
                    <span
                        v-if="domain.isPrimary"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                        >Main address</span
                    >
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            domain.isVerified
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                        ]"
                    >
                        {{ domain.isVerified ? 'Verified' : 'Waiting for DNS' }}
                    </span>
                    <div class="ml-auto flex gap-1">
                        <Button
                            v-if="!domain.isVerified"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="check(domain)"
                        >
                            <RefreshCw /> Check DNS
                        </Button>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            :aria-label="`Remove ${domain.hostname}`"
                            @click="remove(domain)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>

                <div v-if="!domain.isVerified" class="mt-3 grid gap-2">
                    <p class="text-xs text-muted-foreground">
                        Add these records where the domain's DNS is managed
                        (your registrar or Cloudflare):
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-muted-foreground">
                                <tr>
                                    <th class="py-1 pr-3 font-medium">Type</th>
                                    <th class="py-1 pr-3 font-medium">Name</th>
                                    <th class="py-1 font-medium">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="record in domain.records"
                                    :key="record.type + record.name"
                                    class="border-t align-top"
                                >
                                    <td class="py-1.5 pr-3 font-mono">
                                        {{ record.type }}
                                    </td>
                                    <td class="py-1.5 pr-3 font-mono break-all">
                                        {{ record.name }}
                                    </td>
                                    <td class="py-1.5">
                                        <span
                                            class="inline-flex items-start gap-1"
                                        >
                                            <span class="font-mono break-all">{{
                                                record.value
                                            }}</span>
                                            <Button
                                                type="button"
                                                size="icon-sm"
                                                variant="ghost"
                                                class="size-6"
                                                aria-label="Copy value"
                                                @click="copyValue(record.value)"
                                            >
                                                <Copy />
                                            </Button>
                                        </span>
                                        <span
                                            class="block text-muted-foreground"
                                            >{{ record.purpose }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-if="domain.error"
                        class="text-xs text-rose-600 dark:text-rose-300"
                    >
                        {{ domain.error }}
                        <template v-if="domain.lastCheckedAt">
                            Last checked
                            {{ formatRelativeTime(domain.lastCheckedAt) }}.
                        </template>
                    </p>
                </div>
            </li>
        </ul>

        <form
            class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end"
            @submit.prevent="add"
        >
            <div class="grid flex-1 gap-2">
                <Label for="hostname">Add a domain</Label>
                <Input
                    id="hostname"
                    v-model="form.hostname"
                    placeholder="www.yourclient.com"
                />
            </div>
            <Button type="submit" :disabled="form.processing || !form.hostname">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                <Plus v-else /> Add domain
            </Button>
        </form>
        <InputError class="mt-2" :message="form.errors.hostname" />
    </section>
</template>
