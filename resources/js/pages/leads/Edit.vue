<script setup lang="ts">
import { Head, Link, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    CircleCheck,
    FileText,
    ExternalLink,
    ImagePlus,
    LoaderCircle,
    MapPin,
    Pencil,
    Receipt,
    Sparkles,
    Star,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ActivityTimeline from '@/components/leads/ActivityTimeline.vue';
import ClientPortalCard from '@/components/leads/ClientPortalCard.vue';
import LeadConceptsCard from '@/components/leads/LeadConceptsCard.vue';
import LeadMessagesCard from '@/components/leads/LeadMessagesCard.vue';
import CompetitorAuditCard from '@/components/leads/CompetitorAuditCard.vue';
import LeadFollowUpCard from '@/components/leads/LeadFollowUpCard.vue';
import LeadScoreCard from '@/components/leads/LeadScoreCard.vue';
import LeadSequenceCard from '@/components/leads/LeadSequenceCard.vue';
import LeadTasksCard from '@/components/leads/LeadTasksCard.vue';
import ReviewSummaryCard from '@/components/leads/ReviewSummaryCard.vue';
import SalesAssistantCard from '@/components/leads/SalesAssistantCard.vue';
import OutreachCard from '@/components/leads/OutreachCard.vue';
import VerificationCard from '@/components/leads/VerificationCard.vue';
import WebsiteAuditCard from '@/components/leads/WebsiteAuditCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create as createInvoice } from '@/routes/invoices';
import { create as createProposal } from '@/routes/proposals';
import { edit as reviewLead, index as leadsIndex } from '@/routes/leads';
import { update as updateProfile } from '@/routes/leads/profile';
import { edit as editSite } from '@/routes/sites';
import type {
    EmailTemplate,
    LeadActivity,
    LeadConcepts,
    LeadMessaging,
    LeadProfile,
    Option,
    Team,
    VerificationReport,
} from '@/types';

const props = defineProps<{
    lead: LeadProfile;
    verification: VerificationReport;
    activities: LeadActivity[];
    messaging: LeadMessaging;
    concepts: LeadConcepts;
    emailTemplates: EmailTemplate[];
    mergeValues: Record<string, string>;
    vibes: Option[];
    days: string[];
    maxPhotos: number;
    members: { id: number; name: string }[];
    lostReasons: { value: string; label: string }[];
    suggestions: { title: string; detail: string }[];
    sequences: { id: number; name: string }[];
    enrollments: {
        id: number;
        name: string;
        status: string;
        nextStepAt: string | null;
        stoppedReason: string | null;
    }[];
    tasks: {
        id: number;
        title: string;
        dueOn: string | null;
        completedAt: string | null;
        assignee: string | null;
    }[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null; lead: LeadProfile }) => ({
        breadcrumbs: props.currentTeam
            ? [
                  {
                      title: 'Leads',
                      href: leadsIndex({
                          current_team: props.currentTeam.slug,
                      }),
                  },
                  {
                      title: props.lead.name,
                      href: reviewLead({
                          current_team: props.currentTeam.slug,
                          lead: props.lead.id,
                      }),
                  },
              ]
            : [],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const vibeDescriptions: Record<string, string> = {
    modern: 'Clean, bright and professional.',
    warm: 'Soft, friendly and welcoming.',
    bold: 'Dark, striking and confident.',
};

const form = useForm({
    name: props.lead.name,
    category: props.lead.category ?? '',
    description: props.lead.description ?? '',
    phone: props.lead.phone ?? '',
    email: props.lead.email ?? '',
    address: props.lead.address ?? '',
    city: props.lead.city ?? '',
    opening_hours: Object.fromEntries(
        props.days.map((day) => [day, props.lead.openingHours[day] ?? '']),
    ) as Record<string, string>,
    vibe: props.lead.vibe ?? 'modern',
    logo: null as File | null,
    remove_logo: false,
    photos: [] as File[],
    remove_photos: false,
    generate: false,
});

const logoPreview = ref<string | null>(props.lead.logoUrl);
const photoPreviews = ref<string[]>(props.lead.photoUrls);

const selectLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = file ? URL.createObjectURL(file) : props.lead.logoUrl;
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

const selectPhotos = (event: Event) => {
    const files = Array.from(
        (event.target as HTMLInputElement).files ?? [],
    ).slice(0, props.maxPhotos);

    form.photos = files;
    form.remove_photos = false;
    photoPreviews.value = files.map((file) => URL.createObjectURL(file));
};

const removePhotos = () => {
    form.photos = [];
    form.remove_photos = true;
    photoPreviews.value = [];
};

const submit = (generate: boolean) => {
    form.generate = generate;

    form.transform((data) => ({ ...data, _method: 'put' })).post(
        updateProfile.url({
            current_team: teamSlug.value,
            lead: props.lead.id,
        }),
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.logo = null;
                form.photos = [];
                form.remove_logo = false;
                form.remove_photos = false;
            },
        },
    );
};

const isBuilding = computed(() => props.lead.siteGenerationStatus === 'queued');
const isAuditing = computed(() => props.lead.websiteAuditStatus === 'queued');
const isBuildingConcepts = computed(() => props.concepts.status === 'queued');

const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['lead', 'activities', 'verification', 'mergeValues', 'concepts'] },
    { autoStart: false },
);

watch(
    () => isBuilding.value || isAuditing.value || isBuildingConcepts.value,
    (active) => (active ? startPolling() : stopPolling()),
    { immediate: true },
);

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head :title="`Review ${lead.name}`" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
        >
            <Heading
                :title="lead.name"
                description="Confirm the details, add a logo and photos, and pick a style. The website is built only from what you confirm here."
            />
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span
                    v-if="lead.rating"
                    class="inline-flex items-center gap-1 text-muted-foreground"
                >
                    <Star class="size-4 fill-amber-400 text-amber-400" />
                    {{ lead.rating.toFixed(1) }} ({{ lead.reviewsCount }})
                </span>
                <Button
                    v-if="lead.mapsUrl"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <a :href="lead.mapsUrl" target="_blank" rel="noopener">
                        <MapPin /> Google Maps
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            createInvoice(teamSlug, {
                                query: { lead: lead.id },
                            })
                        "
                    >
                        <Receipt /> Create invoice
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="createProposal(teamSlug, { query: { lead: lead.id } })"
                    >
                        <FileText /> New proposal
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <div
                    v-if="isBuilding || lead.site || lead.siteGenerationError"
                    class="flex flex-col gap-3 panel p-4 md:flex-row md:items-center md:justify-between"
                    :class="
                        lead.siteGenerationError
                            ? 'border-destructive/40 bg-destructive/5'
                            : 'bg-muted/40'
                    "
                >
                    <p class="flex items-center gap-2 text-sm font-medium">
                        <template v-if="isBuilding">
                            <LoaderCircle class="size-4 animate-spin" />
                            Building the website… this usually takes under a
                            minute.
                        </template>
                        <template v-else-if="lead.siteGenerationError">
                            {{ lead.siteGenerationError }}
                        </template>
                        <template v-else>
                            <CircleCheck class="size-4 text-emerald-600" />
                            The website is ready.
                        </template>
                    </p>
                    <div v-if="lead.site && !isBuilding" class="flex gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="lead.site.previewUrl"
                                target="_blank"
                                rel="noopener"
                            >
                                <ExternalLink /> Preview
                            </a>
                        </Button>
                        <Button size="sm" as-child>
                            <Link
                                :href="
                                    editSite({
                                        current_team: teamSlug,
                                        site: lead.site.id,
                                    })
                                "
                            >
                                <Pencil /> Edit website
                            </Link>
                        </Button>
                    </div>
                </div>

                <form
                    class="flex flex-col gap-6"
                    @submit.prevent="submit(true)"
                >
                    <section class="grid gap-4 panel p-5 md:grid-cols-2">
                        <Heading
                            variant="small"
                            title="Business details"
                            class="md:col-span-2"
                        />

                        <div class="grid gap-2">
                            <Label for="name">Business name</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="category">Category</Label>
                            <Input
                                id="category"
                                v-model="form.category"
                                placeholder="e.g. Plumber"
                            />
                            <InputError :message="form.errors.category" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" type="tel" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address">Address</Label>
                            <Input id="address" v-model="form.address" />
                            <InputError :message="form.errors.address" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="city">City</Label>
                            <Input id="city" v-model="form.city" />
                            <InputError :message="form.errors.city" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="description">About the business</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Facts only: what they do, who they serve. The website will never claim anything that is not written here."
                                :class="textareaClass"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </section>

                    <section class="panel p-5">
                        <Heading
                            variant="small"
                            title="Opening hours"
                            description="Leave a day empty to hide it."
                        />
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="day in days"
                                :key="day"
                                class="grid grid-cols-[6.5rem_1fr] items-center gap-3"
                            >
                                <Label :for="`hours-${day}`">{{ day }}</Label>
                                <Input
                                    :id="`hours-${day}`"
                                    v-model="form.opening_hours[day]"
                                    placeholder="e.g. 9 AM to 5 PM"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.opening_hours" />
                    </section>

                    <section class="grid gap-6 panel p-5 md:grid-cols-2">
                        <div class="grid content-start gap-3">
                            <Heading variant="small" title="Logo" />
                            <div class="flex items-center gap-3">
                                <div
                                    class="grid size-20 place-items-center overflow-hidden rounded-lg border bg-muted/40"
                                >
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        alt="Logo preview"
                                        class="max-h-full max-w-full object-contain"
                                    />
                                    <ImagePlus
                                        v-else
                                        class="size-6 text-muted-foreground"
                                    />
                                </div>
                                <div class="flex flex-col gap-2">
                                    <Input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        @change="selectLogo"
                                    />
                                    <Button
                                        v-if="logoPreview"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="self-start"
                                        @click="removeLogo"
                                    >
                                        <Trash2 /> Remove logo
                                    </Button>
                                </div>
                            </div>
                            <InputError :message="form.errors.logo" />
                        </div>

                        <div class="grid content-start gap-3">
                            <Heading
                                variant="small"
                                title="Photos"
                                :description="`Up to ${maxPhotos}. The first is the main image; two or more add a gallery.`"
                            />
                            <Input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                multiple
                                @change="selectPhotos"
                            />
                            <div
                                v-if="photoPreviews.length"
                                class="grid grid-cols-3 gap-2"
                            >
                                <img
                                    v-for="(photo, index) in photoPreviews"
                                    :key="photo"
                                    :src="photo"
                                    :alt="`Photo ${index + 1}`"
                                    class="aspect-[4/3] w-full rounded-md border object-cover"
                                />
                            </div>
                            <Button
                                v-if="
                                    lead.hasUploadedPhotos || form.photos.length
                                "
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="self-start"
                                @click="removePhotos"
                            >
                                <Trash2 /> Remove photos
                            </Button>
                            <InputError
                                :message="
                                    form.errors.photos ??
                                    Object.entries(form.errors).find(([key]) =>
                                        key.startsWith('photos.'),
                                    )?.[1]
                                "
                            />
                        </div>
                    </section>

                    <section class="panel p-5">
                        <Heading
                            variant="small"
                            title="Style"
                            description="Sets the template. Colours come from a palette approved for it."
                        />
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <button
                                v-for="vibe in vibes"
                                :key="vibe.value"
                                type="button"
                                :class="[
                                    'rounded-lg border p-4 text-left transition',
                                    form.vibe === vibe.value
                                        ? 'border-primary ring-2 ring-primary/30'
                                        : 'hover:bg-muted/50',
                                ]"
                                :aria-pressed="form.vibe === vibe.value"
                                @click="form.vibe = vibe.value"
                            >
                                <div class="font-medium">{{ vibe.label }}</div>
                                <div class="text-sm text-muted-foreground">
                                    {{ vibeDescriptions[vibe.value] }}
                                </div>
                            </button>
                        </div>
                        <InputError :message="form.errors.vibe" />
                    </section>

                    <div
                        class="sticky bottom-0 -mx-4 flex flex-col gap-2 border-t bg-background p-4 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="submit(false)"
                        >
                            Save details
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing || isBuilding"
                        >
                            <LoaderCircle
                                v-if="form.processing && form.generate"
                                class="animate-spin"
                            />
                            <Sparkles v-else />
                            {{
                                lead.site
                                    ? 'Save & rebuild website'
                                    : 'Save & build website'
                            }}
                        </Button>
                    </div>
                </form>
            </div>

            <aside class="flex min-w-0 flex-col gap-6">
                <LeadScoreCard :lead="lead" />
                <SalesAssistantCard :suggestions="suggestions" />
                <LeadFollowUpCard
                    :lead="lead"
                    :members="members"
                    :lost-reasons="lostReasons"
                />
                <LeadTasksCard :lead-id="lead.id" :tasks="tasks" :members="members" />
                <LeadSequenceCard
                    :lead-id="lead.id"
                    :sequences="sequences"
                    :enrollments="enrollments"
                />
                <ReviewSummaryCard :summary="lead.reviewsSummary" />
                <LeadConceptsCard :lead-id="lead.id" :concepts="concepts" />
                <ClientPortalCard :lead="lead" />
                <LeadMessagesCard :lead-id="lead.id" :messaging="messaging" />
                <OutreachCard
                    :lead="lead"
                    :templates="emailTemplates"
                    :merge-values="mergeValues"
                />
                <WebsiteAuditCard :lead="lead" />
                <CompetitorAuditCard :lead="lead" />
                <VerificationCard :verification="verification" />
                <ActivityTimeline :lead-id="lead.id" :activities="activities" />
            </aside>
        </div>
    </div>
</template>
