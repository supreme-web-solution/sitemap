<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { index as recurringIndex, site as linkSite } from '@/routes/recurring-invoices';

export type ClientServiceRow = {
    id: number;
    label: string;
    total: string;
    intervalLabel: string;
    status: 'active' | 'paused' | 'ended';
};

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    linked: ClientServiceRow[];
    attachable: ClientServiceRow[];
}>();

const selectedId = ref<number | ''>('');

const statusLabel = (status: ClientServiceRow['status']) => {
    if (status === 'active') {
        return 'Active';
    }

    if (status === 'paused') {
        return 'Paused';
    }

    return 'Ended';
};

const canAttach = computed(() => props.attachable.length > 0);

const attach = () => {
    if (!selectedId.value) {
        return;
    }

    router.put(
        linkSite.url({
            current_team: props.teamSlug,
            recurringInvoice: selectedId.value,
        }),
        { site_id: props.siteId },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedId.value = '';
            },
        },
    );
};

const unlink = (row: ClientServiceRow) => {
    router.put(
        linkSite.url({
            current_team: props.teamSlug,
            recurringInvoice: row.id,
        }),
        { site_id: null },
        { preserveScroll: true },
    );
};
</script>

<template>
    <section class="panel mt-4 p-5" data-test="client-services">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold">Client services</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Hosting and maintenance you bill this client — paid to you, not
                    MapMatic. Link a recurring schedule so it is clear what covers
                    this site.
                </p>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="recurringIndex.url(teamSlug)">Recurring invoices</Link>
            </Button>
        </div>

        <ul v-if="linked.length" class="mt-4 grid gap-2">
            <li
                v-for="row in linked"
                :key="row.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/70 px-3 py-2 text-sm"
            >
                <div>
                    <p class="font-medium">{{ row.label }}</p>
                    <p class="text-muted-foreground">
                        {{ row.total }} · {{ row.intervalLabel }} ·
                        {{ statusLabel(row.status) }}
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="unlink(row)"
                >
                    Unlink
                </Button>
            </li>
        </ul>
        <p v-else class="mt-4 text-sm text-muted-foreground">
            No recurring Hosting or Maintenance is linked to this site yet.
        </p>

        <div v-if="canAttach" class="mt-4 flex flex-wrap items-end gap-2">
            <label class="grid min-w-[14rem] flex-1 gap-1 text-sm">
                <span class="text-muted-foreground">Link a schedule for this client</span>
                <select
                    v-model="selectedId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">Choose a schedule…</option>
                    <option
                        v-for="row in attachable"
                        :key="row.id"
                        :value="row.id"
                    >
                        {{ row.label }} · {{ row.total }} ({{ statusLabel(row.status) }})
                    </option>
                </select>
            </label>
            <Button type="button" size="sm" :disabled="!selectedId" @click="attach">
                Link
            </Button>
        </div>
    </section>
</template>
