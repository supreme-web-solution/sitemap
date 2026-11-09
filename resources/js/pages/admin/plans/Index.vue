<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BarChart3,
    LoaderCircle,
    Pencil,
    Plus,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { dashboard as adminDashboard } from '@/routes/admin';
import { destroy, index, store, update } from '@/routes/admin/plans';
import { index as teamsIndex } from '@/routes/admin/teams';
import type { Plan, PlanFeatureOption, PlanMetric } from '@/types';

const props = defineProps<{
    plans: Plan[];
    metrics: PlanMetric[];
    features: PlanFeatureOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Plans', href: index() },
        ],
    },
});

const blankLimits = () =>
    Object.fromEntries(props.metrics.map((metric) => [metric.key, ''])) as Record<
        string,
        number | ''
    >;

const formOpen = ref(false);
const editing = ref<Plan | null>(null);
const teamsPlan = ref<Plan | null>(null);
const analyticsPlan = ref<Plan | null>(null);

const form = useForm({
    name: '',
    slug: '',
    description: '',
    price: 0 as number | '',
    is_default: false,
    is_active: true,
    limits: blankLimits(),
    features: [] as string[],
});

watch(formOpen, (isOpen) => {
    if (!isOpen) {
        editing.value = null;
        form.clearErrors();
    }
});

const startCreating = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    form.limits = blankLimits();
    form.features = [];
    formOpen.value = true;
};

const startEditing = (plan: Plan) => {
    editing.value = plan;
    form.clearErrors();
    form.name = plan.name;
    form.slug = plan.slug;
    form.description = plan.description ?? '';
    form.price = plan.price_cents / 100;
    form.is_default = plan.is_default;
    form.is_active = plan.is_active;
    form.limits = {
        ...blankLimits(),
        ...Object.fromEntries(
            Object.entries(plan.limits).map(([key, value]) => [key, value ?? '']),
        ),
    };
    form.features = [...plan.features];
    formOpen.value = true;
};

const slugify = () => {
    if (!editing.value && !form.slug) {
        form.slug = form.name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
};

const featureOn = (key: string) => form.features.includes(key);

const setFeature = (key: string, on: boolean) => {
    if (on) {
        if (!form.features.includes(key)) {
            form.features = [...form.features, key];
        }

        return;
    }

    form.features = form.features.filter((feature) => feature !== key);
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
        },
    };

    form.transform((data) => ({
        ...data,
        description: data.description || null,
        limits: Object.fromEntries(
            Object.entries(data.limits).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        ),
    }));

    if (editing.value) {
        form.put(update.url(editing.value.id), options);

        return;
    }

    form.post(store.url(), options);
};

const remove = (plan: Plan) => {
    if (
        confirm(
            `Delete ${plan.name}? Its ${plan.teams_count ?? 0} teams fall back to the default plan.`,
        )
    ) {
        router.delete(destroy.url(plan.id), { preserveScroll: true });
    }
};

const money = (cents: number) =>
    cents === 0
        ? 'Free'
        : new Intl.NumberFormat('en-US', {
              style: 'currency',
              currency: 'USD',
          }).format(cents / 100) + '/mo';

const limitText = (plan: Plan, key: string) => {
    const value = plan.limits[key];

    return value === null || value === undefined ? 'Unlimited' : String(value);
};

const featureLabels = (plan: Plan) =>
    props.features
        .filter((feature) => plan.features.includes(feature.key))
        .map((feature) => feature.label);

const analyticsSummary = computed(() => {
    const analytics = analyticsPlan.value?.analytics;

    if (!analytics) {
        return [];
    }

    return [
        { label: 'Teams', value: analytics.teams_count },
        { label: 'Members', value: analytics.members_count },
        { label: 'Saved leads', value: analytics.leads_count },
        { label: 'Websites', value: analytics.sites_count },
    ];
});

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Plans" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Plans"
                description="Set what each plan includes, then assign plans to teams. The default plan applies to teams without one. With no plans at all, everything is unlimited. Plans are assigned by you; there is no self-serve billing."
            />
            <Button @click="startCreating"><Plus /> New plan</Button>
        </div>

        <EmptyState
            v-if="!plans.length"
            title="No plans yet"
            description="Every team is currently unlimited. Create a plan to start capping usage and assigning features."
            :icon="Plus"
        >
            <Button @click="startCreating"><Plus /> New plan</Button>
        </EmptyState>

        <div v-else class="grid gap-4 md:grid-cols-2">
            <article
                v-for="plan in plans"
                :key="plan.id"
                class="panel flex flex-col gap-4 p-5"
                data-test="plan-card"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="font-semibold">
                            {{ plan.name }}
                            <span
                                v-if="plan.is_default"
                                class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-normal text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                                >Default</span
                            >
                            <span
                                v-if="!plan.is_active"
                                class="ml-1 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-normal text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                >Inactive</span
                            >
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ money(plan.price_cents) }} ·
                            {{ plan.teams_count ?? 0 }} teams
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="View analytics"
                            @click="analyticsPlan = plan"
                        >
                            <BarChart3 />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="View teams"
                            @click="teamsPlan = plan"
                        >
                            <Users />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Edit plan"
                            @click="startEditing(plan)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Delete plan"
                            @click="remove(plan)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>

                <p v-if="plan.description" class="text-sm">
                    {{ plan.description }}
                </p>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    <template v-for="metric in metrics" :key="metric.key">
                        <dt class="text-muted-foreground">{{ metric.label }}</dt>
                        <dd class="text-right font-medium">
                            {{ limitText(plan, metric.key) }}
                        </dd>
                    </template>
                </dl>

                <div
                    v-if="featureLabels(plan).length"
                    class="border-t border-border pt-3 text-xs text-muted-foreground"
                >
                    {{ featureLabels(plan).join(' · ') }}
                </div>
                <p v-else class="border-t border-border pt-3 text-xs text-muted-foreground">
                    No extra features
                </p>
            </article>
        </div>

        <Dialog v-model:open="formOpen">
            <DialogScrollContent class="max-w-2xl" data-test="plan-form">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? `Edit ${editing.name}` : 'New plan' }}
                    </DialogTitle>
                    <DialogDescription>
                        Caps and features apply to every team assigned to this plan.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="plan-name">Name</Label>
                            <Input id="plan-name" v-model="form.name" @blur="slugify" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-slug">Slug</Label>
                            <Input id="plan-slug" v-model="form.slug" />
                            <InputError :message="form.errors.slug" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-price">Price (USD per month)</Label>
                            <Input
                                id="plan-price"
                                v-model.number="form.price"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                            <InputError :message="form.errors.price" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="plan-description">Description</Label>
                        <textarea
                            id="plan-description"
                            v-model="form.description"
                            rows="2"
                            maxlength="500"
                            :class="textareaClass"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>Limits (leave blank for unlimited)</Label>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="metric in metrics"
                                :key="metric.key"
                                class="grid gap-1"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >{{ metric.label }} {{ metric.period }}</span
                                >
                                <Input
                                    v-model="form.limits[metric.key]"
                                    type="number"
                                    min="0"
                                    placeholder="Unlimited"
                                    :aria-label="metric.label"
                                />
                                <InputError
                                    :message="
                                        (form.errors as Record<string, string>)[
                                            `limits.${metric.key}`
                                        ]
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Features</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="feature in features"
                                :key="feature.key"
                                class="flex items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3"
                            >
                                <span class="text-sm font-medium">{{ feature.label }}</span>
                                <Switch
                                    :model-value="featureOn(feature.key)"
                                    :aria-label="feature.label"
                                    @update:model-value="
                                        (on: boolean) => setFeature(feature.key, on === true)
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            class="flex items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Default plan</p>
                                <p class="text-xs text-muted-foreground">
                                    Used by teams without an assigned plan
                                </p>
                            </div>
                            <Switch
                                :model-value="form.is_default"
                                aria-label="Default plan for teams without one"
                                @update:model-value="
                                    (on: boolean) => (form.is_default = on === true)
                                "
                            />
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Active</p>
                                <p class="text-xs text-muted-foreground">
                                    Inactive plans stay assigned but are hidden from new picks
                                </p>
                            </div>
                            <Switch
                                :model-value="form.is_active"
                                aria-label="Active"
                                @update:model-value="
                                    (on: boolean) => (form.is_active = on === true)
                                "
                            />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            {{ editing ? 'Save plan' : 'Create plan' }}
                        </Button>
                        <Button type="button" variant="ghost" @click="formOpen = false">
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogScrollContent>
        </Dialog>

        <Dialog :open="!!teamsPlan" @update:open="(open) => !open && (teamsPlan = null)">
            <DialogScrollContent class="max-w-xl" data-test="plan-teams-modal">
                <DialogHeader>
                    <DialogTitle>
                        Teams on {{ teamsPlan?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ teamsPlan?.teams_count ?? 0 }} teams currently use this plan.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="teamsPlan" class="grid gap-3">
                    <p
                        v-if="!(teamsPlan.teams?.length ?? 0)"
                        class="text-sm text-muted-foreground"
                    >
                        No teams are on this plan yet.
                    </p>
                    <ul v-else class="grid gap-2">
                        <li
                            v-for="team in teamsPlan.teams"
                            :key="team.id"
                            class="flex items-start justify-between gap-3 rounded-xl border px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ team.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ team.owner ?? 'No owner' }}
                                    <span v-if="team.is_personal"> · Personal</span>
                                </p>
                            </div>
                            <p class="shrink-0 text-xs text-muted-foreground">
                                {{ team.members_count }} members ·
                                {{ team.sites_count }} sites ·
                                {{ team.leads_count }} leads
                            </p>
                        </li>
                    </ul>
                    <Button variant="outline" class="w-fit" as-child>
                        <Link
                            :href="
                                teamsIndex.url({
                                    query: { plan: String(teamsPlan.id) },
                                })
                            "
                        >
                            Manage teams
                        </Link>
                    </Button>
                </div>
            </DialogScrollContent>
        </Dialog>

        <Dialog
            :open="!!analyticsPlan"
            @update:open="(open) => !open && (analyticsPlan = null)"
        >
            <DialogScrollContent class="max-w-xl" data-test="plan-analytics-modal">
                <DialogHeader>
                    <DialogTitle>
                        Analytics for {{ analyticsPlan?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Totals across every team on this plan. Monthly usage is for
                        {{ new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' }) }}.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="analyticsPlan?.analytics" class="grid gap-4">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div
                            v-for="stat in analyticsSummary"
                            :key="stat.label"
                            class="rounded-xl border bg-muted/30 px-3 py-3 text-center"
                        >
                            <p class="text-lg font-semibold tabular-nums">{{ stat.value }}</p>
                            <p class="text-xs text-muted-foreground">{{ stat.label }}</p>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 text-sm font-medium">Usage this month</h4>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <template
                                v-for="row in analyticsPlan.analytics.usage_this_month"
                                :key="row.key"
                            >
                                <dt class="text-muted-foreground">{{ row.label }}</dt>
                                <dd class="text-right font-medium tabular-nums">
                                    {{ row.used }}
                                </dd>
                            </template>
                        </dl>
                    </div>
                </div>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
