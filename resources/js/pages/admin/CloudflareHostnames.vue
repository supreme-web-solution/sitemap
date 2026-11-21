<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    cloudflareHostnames as adminCloudflareHostnames,
    dashboard as adminDashboard,
} from '@/routes/admin';
import { destroy as destroyHostname } from '@/routes/admin/cloudflare-hostnames';

type HostnameKind = 'site_domain' | 'preview' | 'orphan';

type LinkedRecord = {
    type: 'site_domain' | 'preview';
    label: string;
    hostname: string | null;
    account: { name: string; email: string } | null;
};

type HostnameRow = {
    id: string;
    hostname: string;
    status: string | null;
    sslStatus: string | null;
    verificationErrors: string[];
    ownershipTxtName: string | null;
    ownershipTxtValue: string | null;
    kind: HostnameKind;
    linked: LinkedRecord | null;
};

const props = defineProps<{
    enabled: boolean;
    error: string | null;
    hostnames: HostnameRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Cloudflare hostnames', href: adminCloudflareHostnames() },
        ],
    },
});

const kindFilter = ref<'all' | HostnameKind>('all');

const kindFilters: { value: 'all' | HostnameKind; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'site_domain', label: 'Site hosting' },
    { value: 'preview', label: 'Brand / preview' },
    { value: 'orphan', label: 'Orphans' },
];

const kindLabel = (kind: HostnameKind) => {
    if (kind === 'site_domain') {
        return 'Site hosting';
    }

    if (kind === 'preview') {
        return 'Brand / preview';
    }

    return 'Orphan';
};

const kindClass = (kind: HostnameKind) => {
    if (kind === 'site_domain') {
        return 'bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-200';
    }

    if (kind === 'preview') {
        return 'bg-violet-100 text-violet-900 dark:bg-violet-500/15 dark:text-violet-200';
    }

    return 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200';
};

const filteredHostnames = computed(() => {
    if (kindFilter.value === 'all') {
        return props.hostnames;
    }

    return props.hostnames.filter((row) => row.kind === kindFilter.value);
});

const statusClass = (status: string | null) => {
    if (status === 'active') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    if (status && status.includes('error')) {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200';
};

const refresh = () => {
    router.reload({ only: ['hostnames', 'error', 'enabled'] });
};

const remove = (row: HostnameRow) => {
    if (
        !confirm(
            `Delete ${row.hostname} from Cloudflare? This does not remove the domain from MapMatic sites — only the Cloudflare custom hostname.`,
        )
    ) {
        return;
    }

    router.delete(destroyHostname.url(row.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Cloudflare hostnames" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Cloudflare custom hostnames"
                description="Live list from the mapmaticapp.com SaaS zone. Delete orphans (for example leftover test hostnames) without opening the Cloudflare dashboard."
            />
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-test="cloudflare-hostnames-refresh"
                @click="refresh"
            >
                <RefreshCw />
                Refresh
            </Button>
        </div>

        <p
            v-if="!enabled"
            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-500/10 dark:text-amber-200"
            data-test="cloudflare-hostnames-disabled"
        >
            Cloudflare is not configured. Set
            <code class="text-xs">CLOUDFLARE_API_TOKEN</code> and
            <code class="text-xs">CLOUDFLARE_ZONE_ID</code> on the server.
        </p>

        <p
            v-else-if="error"
            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-500/10 dark:text-rose-200"
            data-test="cloudflare-hostnames-error"
        >
            {{ error }}
        </p>

        <template v-else>
            <div
                class="flex flex-wrap gap-2"
                data-test="cloudflare-hostnames-filters"
            >
                <Button
                    v-for="filter in kindFilters"
                    :key="filter.value"
                    type="button"
                    size="sm"
                    :variant="kindFilter === filter.value ? 'default' : 'outline'"
                    :data-test="`cloudflare-filter-${filter.value}`"
                    @click="kindFilter = filter.value"
                >
                    {{ filter.label }}
                    <span class="text-xs opacity-70">
                        ({{
                            filter.value === 'all'
                                ? hostnames.length
                                : hostnames.filter((row) => row.kind === filter.value)
                                      .length
                        }})
                    </span>
                </Button>
            </div>

            <p
                v-if="hostnames.length === 0"
                class="text-sm text-muted-foreground"
                data-test="cloudflare-hostnames-empty"
            >
                No custom hostnames on this zone yet.
            </p>

            <p
                v-else-if="filteredHostnames.length === 0"
                class="text-sm text-muted-foreground"
                data-test="cloudflare-hostnames-filter-empty"
            >
                No hostnames match this filter.
            </p>

            <div
                v-else
                class="panel overflow-x-auto"
                data-test="cloudflare-hostnames-table"
            >
                <table class="w-full min-w-[64rem] text-left text-sm">
                    <thead class="text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Hostname</th>
                            <th class="px-3 py-2 font-medium">Type</th>
                            <th class="px-3 py-2 font-medium">Hostname status</th>
                            <th class="px-3 py-2 font-medium">Certificate</th>
                            <th class="px-3 py-2 font-medium">Account</th>
                            <th class="px-3 py-2 font-medium">Linked in MapMatic</th>
                            <th class="px-3 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in filteredHostnames"
                            :key="row.id"
                            class="border-t align-top"
                        >
                            <td class="px-3 py-3">
                                <p class="font-medium font-mono text-xs sm:text-sm">
                                    {{ row.hostname }}
                                </p>
                                <p
                                    v-if="row.verificationErrors.length"
                                    class="mt-1 text-xs text-rose-700 dark:text-rose-300"
                                >
                                    {{ row.verificationErrors.join(' · ') }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="kindClass(row.kind)"
                                    data-test="cloudflare-hostname-kind"
                                >
                                    {{ kindLabel(row.kind) }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(row.status)"
                                >
                                    {{ row.status ?? 'unknown' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(row.sslStatus)"
                                >
                                    {{ row.sslStatus ?? 'unknown' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-xs" data-test="cloudflare-hostname-account">
                                <template v-if="row.linked?.account">
                                    <p class="font-medium text-foreground">
                                        {{ row.linked.account.name }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ row.linked.account.email }}
                                    </p>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-3 py-3 text-xs text-muted-foreground">
                                <template v-if="row.linked">
                                    {{ row.linked.label }}
                                    <span
                                        v-if="row.linked.hostname"
                                        class="mt-0.5 block font-mono"
                                        >{{ row.linked.hostname }}</span
                                    >
                                </template>
                                <template v-else>
                                    <span class="text-amber-700 dark:text-amber-300"
                                        >Orphan — not linked in MapMatic</span
                                    >
                                </template>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    :aria-label="`Delete ${row.hostname}`"
                                    @click="remove(row)"
                                >
                                    <Trash2 />
                                    Delete
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <p class="text-sm text-muted-foreground">
            <Link
                :href="adminDashboard()"
                class="text-primary hover:underline"
            >
                Back to admin
            </Link>
        </p>
    </div>
</template>
