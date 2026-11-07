<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { index as leadsIndex } from '@/routes/leads';
import { create, edit } from '@/routes/proposals';
import type { Paginated } from '@/types';

type ProposalRow = {
    id: number;
    number: string;
    title: string;
    leadName: string;
    status: string;
    statusLabel: string;
    firstYear: string;
    sentAt: string | null;
};

defineProps<{
    proposals: Paginated<ProposalRow>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Proposals', href: '#' }] },
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const statusClasses: Record<string, string> = {
    draft: 'bg-zinc-100 text-zinc-700',
    sent: 'bg-sky-100 text-sky-700',
    accepted: 'bg-emerald-100 text-emerald-700',
    declined: 'bg-rose-100 text-rose-700',
    expired: 'bg-amber-100 text-amber-700',
};
</script>

<template>
    <Head title="Proposals" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Proposals"
                description="Send a proposal with a private link. When the client accepts, the invoice and recurring billing are created for you."
            />
            <Button as-child>
                <Link :href="create(teamSlug)"><Plus /> New proposal</Link>
            </Button>
        </div>

        <EmptyState
            v-if="!proposals.data.length"
            title="No proposals yet"
            description="Send a private link with your packages. When the client accepts, we create the invoice and recurring billing for you."
            :icon="FileText"
        >
            <Button as-child>
                <Link :href="create(teamSlug)"><Plus /> New proposal</Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="leadsIndex({ current_team: teamSlug })">
                    Open leads
                </Link>
            </Button>
        </EmptyState>

        <div v-else class="flex flex-col gap-3">
            <div class="panel overflow-hidden">
                <table class="w-full table-fixed text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Proposal</th>
                            <th class="p-3 font-medium">Client</th>
                            <th class="p-3 font-medium">First-year value</th>
                            <th class="p-3 font-medium">Status</th>
                            <th class="w-28 p-3 text-right font-medium">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="proposal in proposals.data"
                            :key="proposal.id"
                        >
                            <td class="p-3">
                                <Link
                                    :href="
                                        edit({
                                            current_team: teamSlug,
                                            proposal: proposal.id,
                                        })
                                    "
                                    class="font-medium hover:underline"
                                    >{{ proposal.title }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ proposal.number }}
                                </p>
                            </td>
                            <td class="p-3">{{ proposal.leadName }}</td>
                            <td class="p-3">{{ proposal.firstYear }}</td>
                            <td class="p-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="statusClasses[proposal.status]"
                                >
                                    {{ proposal.statusLabel }}
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <Button size="sm" variant="outline" as-child>
                                    <Link
                                        :href="
                                            edit({
                                                current_team: teamSlug,
                                                proposal: proposal.id,
                                            })
                                        "
                                        data-test="proposal-open"
                                    >
                                        Open
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationBar :paginator="proposals" />
        </div>
    </div>
</template>
