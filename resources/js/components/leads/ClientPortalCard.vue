<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, Copy, UsersRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { destroy, store } from '@/routes/leads/portal';
import type { LeadProfile } from '@/types';

const props = defineProps<{ lead: LeadProfile }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const copied = ref(false);

const args = computed(() => ({
    current_team: teamSlug.value,
    lead: props.lead.id,
}));

const copy = async () => {
    if (!props.lead.portalUrl) {
        return;
    }

    await navigator.clipboard.writeText(props.lead.portalUrl);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
};

const turnOff = () => {
    if (confirm('Turn the portal off? The current link will stop working.')) {
        router.delete(destroy.url(args.value), { preserveScroll: true });
    }
};
</script>

<template>
    <section class="panel p-5" data-test="client-portal-card">
        <h2 class="flex items-center gap-2 text-base font-semibold">
            <UsersRound class="size-4" /> Client portal
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
            One private link where your client reviews and approves the
            website, leaves feedback, reads proposals and pays invoices.
        </p>

        <p
            v-if="!lead.portalAvailable"
            class="mt-3 text-sm text-amber-700 dark:text-amber-300"
        >
            The client portal is not included in your plan.
        </p>

        <template v-else-if="lead.portalUrl">
            <div class="mt-3 flex items-center gap-2">
                <input
                    :value="lead.portalUrl"
                    readonly
                    class="h-9 min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 text-xs"
                    aria-label="Portal link"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <Button size="icon-sm" variant="outline" aria-label="Copy link" @click="copy">
                    <Check v-if="copied" />
                    <Copy v-else />
                </Button>
            </div>
            <p v-if="lead.site" class="mt-2 text-xs text-muted-foreground">
                {{
                    lead.site.approvedAt
                        ? 'The client approved the website.'
                        : 'Waiting for the client to approve the website.'
                }}
            </p>
            <div class="mt-3 flex gap-2">
                <Button size="sm" variant="outline" @click="router.post(store.url(args), {}, { preserveScroll: true })">
                    New link
                </Button>
                <Button size="sm" variant="ghost" @click="turnOff">Turn off</Button>
            </div>
        </template>

        <Button
            v-else
            class="mt-3"
            size="sm"
            @click="router.post(store.url(args), {}, { preserveScroll: true })"
        >
            Create portal link
        </Button>
    </section>
</template>