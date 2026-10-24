<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Compass,
    FileText,
    Gauge,
    Globe,
    Inbox,
    Layers,
    Megaphone,
    Rocket,
    ShieldCheck,
    Kanban,
    LayoutGrid,
    Mail,
    MapPinned,
    MessageSquareText,
    Package,
    Palette,
    Receipt,
    Repeat,
    Search,
    Settings,
    Target,
    TrendingUp,
    Wallet,
    Users,
    HandCoins,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavCollapsibleSection from '@/components/NavCollapsibleSection.vue';
import NavMain from '@/components/NavMain.vue';
import SidebarSearch from '@/components/SidebarSearch.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminPlansIndex } from '@/routes/admin/plans';
import { index as adminTeamsIndex } from '@/routes/admin/teams';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as emailTemplatesIndex } from '@/routes/email-templates';
import { index as mailInboxIndex } from '@/routes/mail-inbox';
import { edit as idealCustomerEdit } from '@/routes/ideal-customer';
import { index as researchIndex } from '@/routes/research';
import { index as reportsIndex } from '@/routes/reports';
import { index as sequencesIndex } from '@/routes/sequences';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as recurringIndex } from '@/routes/recurring-invoices';
import { board as leadsBoard, index as leadsIndex } from '@/routes/leads';
import { index as messagingIndex } from '@/routes/messaging';
import { index as packagesIndex } from '@/routes/packages';
import { index as paymentsIndex } from '@/routes/payments';
import { index as planIndex } from '@/routes/plan';
import { index as proposalsIndex } from '@/routes/proposals';
import { edit as brandingEdit } from '@/routes/branding';
import { edit as editProfile } from '@/routes/profile';
import { index as sitesIndex } from '@/routes/sites';
import { index as teamsIndex } from '@/routes/teams';
import type { LeadStatus, NavItem } from '@/types';

const page = usePage();

const team = computed(() => page.props.currentTeam);

const dashboardUrl = computed(() =>
    team.value ? dashboard(team.value.slug).url : '/',
);

const homeItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardUrl.value,
        icon: LayoutGrid,
        exact: true,
    },
]);

const pipelineStages: { status: LeadStatus; title: string; dot: string }[] = [
    { status: 'new', title: 'New leads', dot: 'bg-sky-500' },
    { status: 'site_generated', title: 'Site generated', dot: 'bg-indigo-500' },
    { status: 'contacted', title: 'Contacted', dot: 'bg-amber-500' },
    { status: 'interested', title: 'Interested', dot: 'bg-violet-500' },
    { status: 'won', title: 'Won', dot: 'bg-emerald-500' },
    { status: 'lost', title: 'Lost', dot: 'bg-rose-400' },
];

const pipelineShortcuts = computed<NavItem[]>(() => {
    if (!team.value) {
        return [];
    }

    return pipelineStages.map((stage) => ({
        title: stage.title,
        dot: stage.dot,
        href: leadsIndex(
            { current_team: team.value!.slug },
            { query: { status: stage.status } },
        ).url,
    }));
});

const discoverItems = computed<NavItem[]>(() => {
    if (!team.value) {
        return [];
    }

    const slug = team.value.slug;

    return [
        {
            title: 'Leads',
            href: leadsIndex({ current_team: slug }).url,
            icon: MapPinned,
            exact: true,
        },
        {
            title: 'Niche research',
            href: researchIndex({ current_team: slug }).url,
            icon: Search,
        },
        {
            title: 'Ideal customer',
            href: idealCustomerEdit({ current_team: slug }).url,
            icon: Target,
        },
    ];
});

const buildItems = computed<NavItem[]>(() => {
    if (!team.value) {
        return [];
    }

    return [
        {
            title: 'Websites',
            href: sitesIndex({ current_team: team.value.slug }).url,
            icon: Globe,
        },
    ];
});

const sellItems = computed<NavItem[]>(() => {
    if (!team.value) {
        return [];
    }

    const slug = team.value.slug;

    return [
        {
            title: 'Pipeline board',
            href: leadsBoard({ current_team: slug }).url,
            icon: Kanban,
            exact: true,
        },
        {
            title: 'Email Studio',
            href: emailTemplatesIndex({ current_team: slug }).url,
            icon: Mail,
        },
        {
            title: 'Email inbox',
            href: mailInboxIndex({ current_team: slug }).url,
            icon: Inbox,
        },
        {
            title: 'Sequences',
            href: sequencesIndex({ current_team: slug }).url,
            icon: Repeat,
        },
        {
            title: 'Messaging',
            href: messagingIndex({ current_team: slug }).url,
            icon: MessageSquareText,
        },
        {
            title: 'Proposals',
            href: proposalsIndex({ current_team: slug }).url,
            icon: FileText,
        },
        {
            title: 'Packages',
            href: packagesIndex({ current_team: slug }).url,
            icon: Package,
        },
    ];
});

const growItems = computed<NavItem[]>(() => {
    if (!team.value) {
        return [];
    }

    const slug = team.value.slug;

    return [
        {
            title: 'Invoices',
            href: invoicesIndex({ current_team: slug }).url,
            icon: Receipt,
        },
        {
            title: 'Recurring billing',
            href: recurringIndex({ current_team: slug }).url,
            icon: HandCoins,
        },
        {
            title: 'Payments',
            href: paymentsIndex({ current_team: slug }).url,
            icon: Wallet,
        },
        {
            title: 'Reports',
            href: reportsIndex({ current_team: slug }).url,
            icon: FileText,
        },
    ];
});

const deliverItems = computed<NavItem[]>(() => {
    if (!team.value) {
        return [];
    }

    return [
        {
            title: 'Branding & previews',
            href: brandingEdit({ current_team: team.value.slug }).url,
            icon: Palette,
        },
    ];
});

const adminItems: NavItem[] = [
    { title: 'Overview', href: adminDashboard().url, icon: ShieldCheck, exact: true },
    { title: 'Users', href: adminUsersIndex().url, icon: Users },
    { title: 'Teams', href: adminTeamsIndex().url, icon: Users },
    { title: 'Plans', href: adminPlansIndex().url, icon: Layers },
];

const workspaceItems = computed<NavItem[]>(() => [
    ...(team.value
        ? [
              {
                  title: 'Plan & usage',
                  href: planIndex({ current_team: team.value.slug }).url,
                  icon: Gauge,
              },
          ]
        : []),
    { title: 'Teams', href: teamsIndex().url, icon: Users },
    { title: 'Settings', href: editProfile().url, icon: Settings },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-sidebar-border">
        <SidebarHeader class="gap-4 px-3 pt-4 pb-2">
            <div class="flex items-center gap-1 px-1.5">
                <Link
                    :href="dashboardUrl"
                    class="flex flex-1 items-center gap-2.5 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                >
                    <AppLogo />
                </Link>
                <SidebarTrigger
                    class="text-muted-foreground group-data-[collapsible=icon]:hidden"
                />
            </div>
            <SidebarSearch />
        </SidebarHeader>

        <SidebarContent class="gap-1 pt-1">
            <NavMain label="Home" :items="homeItems" />

            <template v-if="team">
                <NavCollapsibleSection
                    label="Discover"
                    :icon="Compass"
                    :items="discoverItems"
                    :sub-items="pipelineShortcuts"
                    sub-label="Pipeline stages"
                    :action="{ title: 'New search', href: dashboardUrl }"
                />
                <NavCollapsibleSection
                    label="Build"
                    :icon="Globe"
                    :items="buildItems"
                />
                <NavCollapsibleSection
                    label="Sell"
                    :icon="Megaphone"
                    :items="sellItems"
                />
                <NavCollapsibleSection
                    label="Grow"
                    :icon="TrendingUp"
                    :items="growItems"
                />
                <NavCollapsibleSection
                    label="Deliver"
                    :icon="Rocket"
                    :items="deliverItems"
                />
            </template>

            <NavMain
                v-if="page.props.auth.isAdmin"
                label="Admin"
                :items="adminItems"
            />
        </SidebarContent>

        <SidebarFooter class="gap-2 px-3 pb-4">
            <NavMain label="Workspace" :items="workspaceItems" />
            <TeamSwitcher />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
