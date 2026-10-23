<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as adminDashboard } from '@/routes/admin';
import { destroy, index, store, update } from '@/routes/admin/plans';
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

const open = ref(false);
const editing = ref<Plan | null>(null);

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

const startCreating = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.limits = blankLimits();
    open.value = true;
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
    open.value = true;
};

const slugify = () => {
    if (!editing.value && !form.slug) {
        form.slug = form.name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
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

        <form
            v-if="open"
            class="grid gap-4 panel p-5"
            data-test="plan-form"
            @submit.prevent="submit"
        >
            <h2 class="text-base font-semibold">
                {{ editing ? `Edit ${editing.name}` : 'New plan' }}
            </h2>

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
                <div class="flex flex-wrap gap-4 text-sm">
                    <label
                        v-for="feature in features"
                        :key="feature.key"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="form.features"
                            type="checkbox"
                            class="size-4"
                            :value="feature.key"
                        />
                        {{ feature.label }}
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.is_default"
                        type="checkbox"
                        class="size-4"
                    />
                    Default plan for teams without one
                </label>
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Active
                </label>
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ editing ? 'Save plan' : 'Create plan' }}
                </Button>
                <Button type="button" variant="ghost" @click="open = false"
                    >Cancel</Button
                >
            </div>
        </form>

        <p v-if="!plans.length" class="text-sm text-muted-foreground">
            No plans yet. Every team is currently unlimited.
        </p>

        <div class="grid gap-4 md:grid-cols-2">
            <article v-for="plan in plans" :key="plan.id" class="panel p-5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold">
                            {{ plan.name }}
                            <span
                                v-if="plan.is_default"
                                class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-normal text-sky-700"
                                >Default</span
                            >
                            <span
                                v-if="!plan.is_active"
                                class="ml-1 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-normal text-zinc-600"
                                >Inactive</span
                            >
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ money(plan.price_cents) }} ·
                            {{ plan.teams_count }} teams
                        </p>
                    </div>
                    <div class="flex gap-1">
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
                <p v-if="plan.description" class="mt-2 text-sm">
                    {{ plan.description }}
                </p>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    <template v-for="metric in metrics" :key="metric.key">
                        <dt class="text-muted-foreground">{{ metric.label }}</dt>
                        <dd class="text-right font-medium">
                            {{ limitText(plan, metric.key) }}
                        </dd>
                    </template>
                </dl>
                <p class="mt-3 text-xs text-muted-foreground">
                    {{
                        plan.features.length
                            ? features
                                  .filter((f) => plan.features.includes(f.key))
                                  .map((f) => f.label)
                                  .join(' · ')
                            : 'No extra features'
                    }}
                </p>
            </article>
        </div>
    </div>
</template>