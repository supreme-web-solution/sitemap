<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRelativeTime } from '@/lib/format';
import { store as transferSite } from '@/routes/sites/transfer';

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    transferredAt: string | null;
    transferredToEmail: string | null;
}>();

const form = useForm({ email: '' });

const transfer = () =>
    form.post(
        transferSite.url({ current_team: props.teamSlug, site: props.siteId }),
        { preserveScroll: true },
    );
</script>

<template>
    <section class="panel p-5" data-test="transfer-ownership">
        <h3 class="text-base font-semibold">Transfer ownership</h3>
        <p class="mt-1 text-sm text-muted-foreground">
            Enter the client’s email. This takes the live copy offline, stores a
            backup, and emails them a download link. The link does not need a
            login.
        </p>
        <form class="mt-4 grid max-w-md gap-2" @submit.prevent="transfer">
            <Label for="transfer-email">Client email</Label>
            <Input
                id="transfer-email"
                v-model="form.email"
                type="email"
                required
                autocomplete="email"
                placeholder="client@example.com"
            />
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Transfer
            </Button>
            <p v-if="transferredAt" class="text-xs text-muted-foreground">
                Transferred to {{ transferredToEmail }}
                {{ formatRelativeTime(transferredAt) }}.
            </p>
        </form>
    </section>
</template>
