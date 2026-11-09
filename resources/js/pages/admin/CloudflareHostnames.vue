<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    cloudflareHostnames as adminCloudflareHostnames,
    dashboard as adminDashboard,
} from '@/routes/admin';
import { destroy as destroyHostname } from '@/routes/admin/cloudflare-hostnames';

type LinkedRecord = {
    type: 'site_domain' | 'preview';
    label: string;
    hostname: string | null;
};

type HostnameRow = {
    id: string;
    hostname: string;
    status: string | null;
    sslStatus: string | null;
    verificationErrors: string[];
    ownershipTxtName: string | null;
    ownershipTxtValue: string | null;
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

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
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

        <p
            v-else-if="hostnames.length === 0"
            class="text-sm text-muted-foreground"
            data-test="cloudflare-hostnames-empty"
        >
            No custom hostnames on this zone yet.
        </p>

        <div
            v-else
            class="panel overflow-x-auto"
            data-test="cloudflare-hostnames-table"
        >
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">Hostname</th>
                        <th class="px-3 py-2 font-medium">Hostname status</th>
                        <th class="px-3 py-2 font-medium">Certificate</th>
                        <th class="px-3 py-2 font-medium">Linked in MapMatic</th>
                        <th class="px-3 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in hostnames"
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
