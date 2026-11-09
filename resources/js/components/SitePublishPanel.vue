<script setup lang="ts">
import { router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    Copy,
    ExternalLink,
    Globe,
    LoaderCircle,
    Plus,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, watch } from 'vue';
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
import {
    destroy as unpublishStaging,
    promote as promoteStaging,
    store as publishStaging,
} from '@/routes/sites/staging';
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

const httpsActivating = computed(() =>
    props.publishing.domains.some(
        (domain) => domain.isVerified && !domain.sslActive,
    ),
);

const { start: startHttpsPoll, stop: stopHttpsPoll } = usePoll(
    5000,
    { only: ['publishing'] },
    { autoStart: false },
);

watch(
    httpsActivating,
    (active) => (active ? startHttpsPoll() : stopHttpsPoll()),
    { immediate: true },
);

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

const toggleStaging = () => {
    const url = props.publishing.isStagingPublished
        ? unpublishStaging.url(routeArgs())
        : publishStaging.url(routeArgs());

    router[props.publishing.isStagingPublished ? 'delete' : 'post'](
        url,
        {},
        { preserveScroll: true },
    );
};

const promoteToLive = () => {
    if (
        !confirm(
            'Publish this site to production using the staging build? Custom domain DNS must still be verified.',
        )
    ) {
        return;
    }

    router.post(promoteStaging.url(routeArgs()), {}, { preserveScroll: true });
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
            Client domains cannot be pointed at this server yet. On the host,
            set <code>SITES_CNAME_TARGET</code> to the hostname a client should
            CNAME to, or <code>SITES_SERVER_IP</code> to this server’s public
            IP. Until one of those is set, only the ownership TXT record can be
            checked.
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
                            domain.sslActive
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : domain.isVerified
                                  ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
                                  : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                        ]"
                    >
                        {{
                            domain.sslActive
                                ? 'Live HTTPS'
                                : domain.isVerified
                                  ? 'DNS verified — HTTPS activating'
                                  : 'Waiting for DNS'
                        }}
                    </span>
                    <div class="ml-auto flex gap-1">
                        <Button
                            v-if="!domain.sslActive"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="check(domain)"
                        >
                            <RefreshCw />
                            {{ domain.isVerified ? 'Refresh HTTPS' : 'Check DNS' }}
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

                <div
                    v-if="!domain.sslActive && domain.records.length"
                    class="mt-3 grid gap-2"
                >
                    <p class="text-xs text-muted-foreground">
                        <template v-if="domain.isVerified">
                            Ownership is verified. Records marked done can stay
                            as they are — finish any still needed for HTTPS
                            <template v-if="publishing.cnameTarget">
                                (especially the CNAME to
                                <span class="font-mono">{{ publishing.cnameTarget }}</span>)
                            </template>
                            .
                        </template>
                        <template v-else>
                            Add these at the domain’s DNS host, then click Check
                            DNS. Green checks appear once we can see each record.
                        </template>
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-muted-foreground">
                                <tr>
                                    <th class="py-1 pr-3 font-medium">Status</th>
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
                                    :class="
                                        record.status === 'done'
                                            ? 'text-muted-foreground'
                                            : ''
                                    "
                                >
                                    <td class="py-1.5 pr-3">
                                        <span
                                            class="inline-flex items-center gap-1 font-medium"
                                            :class="
                                                record.status === 'done'
                                                    ? 'text-emerald-700 dark:text-emerald-300'
                                                    : 'text-amber-700 dark:text-amber-300'
                                            "
                                        >
                                            <CheckCircle2
                                                v-if="record.status === 'done'"
                                                class="size-3.5"
                                            />
                                            <Circle
                                                v-else
                                                class="size-3.5"
                                            />
                                            {{
                                                record.status === 'done'
                                                    ? 'Done'
                                                    : 'Needed'
                                            }}
                                        </span>
                                    </td>
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
                                                v-if="record.status !== 'done'"
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
                </div>

                <p
                    v-if="
                        domain.isVerified &&
                        !domain.sslActive &&
                        !domain.error &&
                        domain.records.every((record) => record.status === 'done')
                    "
                    class="mt-2 text-xs text-sky-700 dark:text-sky-300"
                >
                    All DNS checks look good. Waiting for Cloudflare to finish
                    the certificate — this updates automatically.
                    <template v-if="domain.lastCheckedAt">
                        Last checked
                        {{ formatRelativeTime(domain.lastCheckedAt) }}.
                    </template>
                </p>

                <p
                    v-if="domain.error"
                    class="mt-2 text-xs"
                    :class="
                        domain.isVerified
                            ? 'text-sky-700 dark:text-sky-300'
                            : 'text-rose-600 dark:text-rose-300'
                    "
                >
                    {{ domain.error }}
                    <template v-if="domain.lastCheckedAt">
                        Last checked
                        {{ formatRelativeTime(domain.lastCheckedAt) }}.
                    </template>
                </p>
            </li>
        </ul>

        <div
            class="mt-4 rounded-xl border border-dashed p-4"
            data-test="staging-panel"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold">Staging host</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Review on a production-like URL at
                        <code class="text-xs">{{ publishing.stagingHostname }}</code>
                        before going live. Staging is hidden from search engines
                        and does not need a custom domain.
                    </p>
                    <p
                        v-if="publishing.isStagingPublished"
                        class="mt-2 text-sm"
                    >
                        <a
                            :href="publishing.stagingUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 font-medium underline"
                        >
                            {{ publishing.stagingUrl }}
                            <ExternalLink class="size-3.5" />
                        </a>
                        <span
                            v-if="publishing.stagingPublishedAt"
                            class="text-muted-foreground"
                        >
                            · staging since
                            {{
                                formatRelativeTime(publishing.stagingPublishedAt)
                            }}
                        </span>
                    </p>
                    <p v-else class="mt-2 text-xs text-muted-foreground">
                        Point DNS wildcard
                        <code>* .{{ publishing.stagingBaseHost }}</code>
                        at the app edge when you deploy.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        data-test="toggle-staging"
                        @click="toggleStaging"
                    >
                        {{
                            publishing.isStagingPublished
                                ? 'Remove staging'
                                : 'Publish to staging'
                        }}
                    </Button>
                    <Button
                        v-if="publishing.canPromoteFromStaging"
                        type="button"
                        data-test="promote-staging"
                        @click="promoteToLive"
                    >
                        Promote to live
                    </Button>
                </div>
            </div>
        </div>

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
