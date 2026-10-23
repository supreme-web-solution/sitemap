<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import CheckList from '@/components/CheckList.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRelativeTime } from '@/lib/format';
import { store as storeCompetitor } from '@/routes/leads/competitor-audit';
import type { LeadProfile } from '@/types';

const props = defineProps<{
    lead: LeadProfile;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const form = useForm({ url: '' });

const submit = () =>
    form.post(
        storeCompetitor.url({
            current_team: teamSlug.value,
            lead: props.lead.id,
        }),
        { preserveScroll: true, only: ['lead'] },
    );
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Competitor audit</h2>
        <form class="mt-4 grid gap-2" @submit.prevent="submit">
            <Label for="competitor-url">Competitor URL</Label>
            <Input
                id="competitor-url"
                v-model="form.url"
                type="url"
                placeholder="https://"
                required
            />
            <InputError :message="form.errors.url" />
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Audit competitor
            </Button>
        </form>
        <p v-if="lead.competitorAuditedAt" class="mt-2 text-xs text-muted-foreground">
            Checked {{ formatRelativeTime(lead.competitorAuditedAt) }}
        </p>
        <div v-if="lead.competitorAudit" class="mt-4">
            <CheckList :items="lead.competitorAudit.checks ?? []" />
        </div>
    </section>
</template>
