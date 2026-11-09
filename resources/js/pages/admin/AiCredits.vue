<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PaginationBar from '@/components/admin/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import { dashboard as adminDashboard, aiCredits } from '@/routes/admin';
import type { Paginated } from '@/types';

type CreditEvent = {
    id: number;
    provider: string;
    source: string;
    message: string;
    outcome: string | null;
    leadName: string | null;
    teamName: string | null;
    occurredAt: string;
    finishedAt: string | null;
};

defineProps<{
    events: Paginated<CreditEvent>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'AI credits', href: aiCredits() },
        ],
    },
});

const formatWhen = (value: string | null): string => {
    if (!value) {
        return 'Not finished';
    }

    return new Date(value).toLocaleString();
};
</script>

<template>
    <Head title="AI credits" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="AI credits"
            description="Provider credit, quota, and lead-search failures. Site creation uses Anthropic (falls back to built-in copy). Lead search uses Mindcase — empty wallet shows as rejected_balance here. OpenAI only appears if a photo or other OpenAI call hits a limit."
        />

        <p
            v-if="events.total === 0"
            class="text-sm text-muted-foreground"
            data-test="ai-credits-empty"
        >
            No provider credit or lead-search errors have been recorded yet.
        </p>

        <div v-else class="panel overflow-x-auto" data-test="ai-credits-log">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="border-b text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Error time</th>
                        <th class="px-4 py-3 font-medium">Finished</th>
                        <th class="px-4 py-3 font-medium">Provider</th>
                        <th class="px-4 py-3 font-medium">Where</th>
                        <th class="px-4 py-3 font-medium">Lead / team</th>
                        <th class="px-4 py-3 font-medium">What happened</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="event in events.data"
                        :key="event.id"
                        class="border-b align-top last:border-0"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ formatWhen(event.occurredAt) }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ formatWhen(event.finishedAt) }}
                        </td>
                        <td class="px-4 py-3 font-medium">{{ event.provider }}</td>
                        <td class="px-4 py-3">{{ event.source }}</td>
                        <td class="px-4 py-3">
                            <span>{{ event.leadName ?? event.teamName ?? '—' }}</span>
                            <span
                                v-if="event.leadName && event.teamName"
                                class="block text-xs text-muted-foreground"
                            >
                                {{ event.teamName }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ event.message }}</p>
                            <p
                                v-if="event.outcome"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ event.outcome }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :paginator="events" />

        <Link :href="adminDashboard()" class="text-sm text-primary hover:underline">
            Back to admin
        </Link>
    </div>
</template>
