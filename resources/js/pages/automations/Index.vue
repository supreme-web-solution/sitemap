<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Zap } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SELECT_PICK } from '@/lib/selectSentinels';
import {
    index as automationsIndex,
    update as updateAutomations,
} from '@/routes/automations';
import type { Team } from '@/types';

type Settings = {
    enabled: boolean;
    default_sequence_id: number | null;
    enroll_sequence_on_lead_created: boolean;
    audit_website_on_lead_created: boolean;
    queue_site_on_lead_created: boolean;
    draft_proposal_on_site_generated: boolean;
    default_package_ids: number[];
    move_interested_when_preview_in_outreach: boolean;
    move_interested_on_proposal_sent: boolean;
    onboarding_on_invoice_paid: boolean;
    reaudit_websites_every_days: number;
};

const props = defineProps<{
    settings: Settings;
    sequences: { id: number; name: string; isActive: boolean }[];
    packages: { id: number; name: string }[];
    recentRuns: {
        id: number;
        triggerLabel: string;
        summary: string;
        leadName: string | null;
        createdAt: string | null;
    }[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Automations',
                href: props.currentTeam
                    ? automationsIndex({ current_team: props.currentTeam.slug }).url
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const form = useForm({ ...props.settings });

const sequenceSelect = computed({
    get: () =>
        form.default_sequence_id ? String(form.default_sequence_id) : SELECT_PICK,
    set: (value: string) => {
        form.default_sequence_id = value === SELECT_PICK ? null : Number(value);
    },
});

const togglePackage = (id: number, checked: boolean) => {
    const current = new Set(form.default_package_ids ?? []);
    if (checked) {
        current.add(id);
    } else {
        current.delete(id);
    }
    form.default_package_ids = [...current];
};

const save = () => {
    if (!teamSlug.value) {
        return;
    }

    form.put(updateAutomations({ current_team: teamSlug.value }).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Automations" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-8 px-4 py-8 lg:px-6">
        <Heading
            title="Automations"
            description="Configure background workflows once — new leads, sites, outreach, proposals, payments, and scheduled re-audits. This is separate from the Command Center chat employee."
        />

        <form
            class="space-y-6 rounded-xl border border-border bg-card p-6 shadow-sm"
            @submit.prevent="save"
        >
            <div class="flex items-center gap-2 text-sm font-medium">
                <Zap class="size-4 text-amber-500" />
                Workflow engine
            </div>

            <div class="flex items-start gap-3 rounded-lg border border-border/60 bg-muted/30 p-4">
                <Checkbox
                    id="enabled"
                    :checked="form.enabled"
                    @update:checked="(v: boolean) => (form.enabled = v)"
                />
                <div class="space-y-1">
                    <Label for="enabled" class="cursor-pointer font-medium">
                        Master automation switch
                    </Label>
                    <p class="text-sm text-muted-foreground">
                        When off, triggers are skipped (Command Center can still run steps
                        manually).
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label>Default outreach sequence</Label>
                    <Select v-model="sequenceSelect">
                        <SelectTrigger>
                            <SelectValue placeholder="None" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="SELECT_PICK">None</SelectItem>
                            <SelectItem
                                v-for="seq in sequences"
                                :key="seq.id"
                                :value="String(seq.id)"
                            >
                                {{ seq.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label for="reaudit">Re-audit websites every (days)</Label>
                    <Input
                        id="reaudit"
                        v-model.number="form.reaudit_websites_every_days"
                        type="number"
                        min="0"
                        max="365"
                    />
                    <p class="text-xs text-muted-foreground">0 = disabled</p>
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium">When a new lead is saved</p>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="form.enroll_sequence_on_lead_created"
                        @update:checked="
                            (v: boolean) => (form.enroll_sequence_on_lead_created = v)
                        "
                    />
                    Enroll in default sequence
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="form.audit_website_on_lead_created"
                        @update:checked="
                            (v: boolean) => (form.audit_website_on_lead_created = v)
                        "
                    />
                    Queue website audit
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="form.queue_site_on_lead_created"
                        @update:checked="
                            (v: boolean) => (form.queue_site_on_lead_created = v)
                        "
                    />
                    Queue AI website generation
                </label>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium">Pipeline & delivery</p>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="form.draft_proposal_on_site_generated"
                        @update:checked="
                            (v: boolean) => (form.draft_proposal_on_site_generated = v)
                        "
                    />
                    Draft proposal when site is generated
                </label>
                <div
                    v-if="form.draft_proposal_on_site_generated && packages.length"
                    class="ml-6 space-y-2"
                >
                    <label
                        v-for="pkg in packages"
                        :key="pkg.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :checked="form.default_package_ids.includes(pkg.id)"
                            @update:checked="(v: boolean) => togglePackage(pkg.id, v)"
                        />
                        {{ pkg.name }}
                    </label>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="form.move_interested_when_preview_in_outreach"
                        @update:checked="
                            (v: boolean) =>
                                (form.move_interested_when_preview_in_outreach = v)
                        "
                    />
                    Move to Interested when preview is in outreach
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="form.move_interested_on_proposal_sent"
                        @update:checked="
                            (v: boolean) => (form.move_interested_on_proposal_sent = v)
                        "
                    />
                    Move to Interested when proposal is sent
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="form.onboarding_on_invoice_paid"
                        @update:checked="
                            (v: boolean) => (form.onboarding_on_invoice_paid = v)
                        "
                    />
                    Onboarding tasks when invoice is paid
                </label>
            </div>

            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                Save automations
            </Button>
        </form>

        <section class="space-y-3 rounded-xl border border-border bg-card p-6 shadow-sm">
            <p class="text-sm font-medium">Recent runs</p>
            <ul v-if="recentRuns.length" class="space-y-2 text-sm">
                <li
                    v-for="run in recentRuns"
                    :key="run.id"
                    class="rounded-md border border-border/60 px-3 py-2"
                >
                    <p class="font-medium">
                        {{ run.triggerLabel }}
                        <span v-if="run.leadName" class="text-muted-foreground">
                            · {{ run.leadName }}
                        </span>
                    </p>
                    <p class="text-muted-foreground">{{ run.summary }}</p>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">No automation runs yet.</p>
        </section>
    </div>
</template>
