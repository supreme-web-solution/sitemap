<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/proposals';

defineProps<{
    proposals: {
        id: number;
        number: string;
        title: string;
        leadName: string;
        status: string;
        statusLabel: string;
        firstYear: string;
        sentAt: string | null;
    }[];
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

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Proposals"
                description="Send a proposal with a private link. When the client accepts, the invoice and recurring billing are created for you."
            />
            <Button as-child>
                <Link :href="create(teamSlug)"><Plus /> New proposal</Link>
            </Button>
        </div>

        <p v-if="!proposals.length" class="text-sm text-muted-foreground">
            No proposals yet. Open a lead and choose "New proposal".
        </p>

        <div v-else class="panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3 font-medium">Proposal</th>
                        <th class="p-3 font-medium">Client</th>
                        <th class="p-3 font-medium">First-year value</th>
                        <th class="p-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="proposal in proposals" :key="proposal.id">
                        <td class="p-3">
                            <Link
                                :href="edit({ current_team: teamSlug, proposal: proposal.id })"
                                class="font-medium hover:underline"
                                >{{ proposal.title }}</Link
                            >
                            <p class="text-xs text-muted-foreground">{{ proposal.number }}</p>
                        </td>
                        <td class="p-3">{{ proposal.leadName }}</td>
                        <td class="p-3">{{ proposal.firstYear }}</td>
                        <td class="p-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClasses[proposal.status]">
                                {{ proposal.statusLabel }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>