<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    FileText,
    Gauge,
    Globe,
    Layers,
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
    Wallet,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { edit as idealCustomerEdit } from '@/routes/ideal-customer';
import { index as researchIndex } from '@/routes/research';
import { index as reportsIndex } from '@/routes/reports';
import { index as sequencesIndex } from '@/routes/sequences';
import { index as invoicesIndex } from '@/routes/invoices';
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

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const overviewItems = computed<NavItem[]>(() => {
    const team = page.props.currentTeam;

    return [
        {
            title: 'Dashboard',
            href: dashboardUrl.value,
            icon: LayoutGrid,
            exact: true,
        },
        ...(team
            ? [
                  {
                      title: 'Leads',
                      href: leadsIndex({ current_team: team.slug }).url,
                      icon: MapPinned,
                  },
                  {
                      title: 'Pipeline board',
                      href: leadsBoard({ current_team: team.slug }).url,
                      icon: Kanban,
                  },
                  {
                      title: 'Websites',
                      href: sitesIndex({ current_team: team.slug }).url,
                      icon: Globe,
                  },
                  {
                      title: 'Email Studio',
                      href: emailTemplatesIndex({ current_team: team.slug })
                          .url,
                      icon: Mail,
                  },
                  {
                      title: 'Sequences',
                      href: sequencesIndex({ current_team: team.slug }).url,
                      icon: Repeat,
                  },
                  {
                      title: 'Reports',
                      href: reportsIndex({ current_team: team.slug }).url,
                      icon: FileText,
                  },
                  {
                      title: 'Niche research',
                      href: researchIndex({ current_team: team.slug }).url,
                      icon: Search,
                  },
                  {
                      title: 'Ideal customer',
                      href: idealCustomerEdit({ current_team: team.slug }).url,
                      icon: Target,
                  },
                  {
                      title: 'Messaging',
                      href: messagingIndex({ current_team: team.slug }).url,
                      icon: MessageSquareText,
                  },
                  {
                      title: 'Proposals',
                      href: proposalsIndex({ current_team: team.slug }).url,
                      icon: FileText,
                  },
                  {
                      title: 'Packages',
                      href: packagesIndex({ current_team: team.slug }).url,
                      icon: Package,
                  },
                  {
                      title: 'Invoices',
                      href: invoicesIndex({ current_team: team.slug }).url,
                      icon: Receipt,
                  },
                  {
                      title: 'Payments',
                      href: paymentsIndex({ current_team: team.slug }).url,
                      icon: Wallet,
                  },
              ]
            : []),
    ];
});

const pipelineStages: { status: LeadStatus; title: string; dot: string }[] = [
    { status: 'new', title: 'New leads', dot: 'bg-sky-500' },
    { status: 'contacted', title: 'Contacted', dot: 'bg-amber-500' },
    { status: 'interested', title: 'Interested', dot: 'bg-violet-500' },
    { status: 'won', title: 'Won', dot: 'bg-emerald-500' },
    { status: 'lost', title: 'Lost', dot: 'bg-rose-400' },
];

const pipelineItems = computed<NavItem[]>(() => {
    const team = page.props.currentTeam;

    if (!team) {
        return [];
    }

    return pipelineStages.map((stage) => ({
        title: stage.title,
        dot: stage.dot,
        href: leadsIndex(
            { current_team: team.slug },
            { query: { status: stage.status } },
        ).url,
    }));
});

const adminItems: NavItem[] = [
    { title: 'Overview', href: adminDashboard().url, icon: ShieldCheck, exact: true },
    { title: 'Users', href: adminUsersIndex().url, icon: Users },
    { title: 'Teams', href: adminTeamsIndex().url, icon: Users },
    { title: 'Plans', href: adminPlansIndex().url, icon: Layers },
];

const toolItems = computed<NavItem[]>(() => [
    ...(page.props.currentTeam
        ? [
              {
                  title: 'Plan & usage',
                  href: planIndex({ current_team: page.props.currentTeam.slug }).url,
                  icon: Gauge,
              },
              {
                  title: 'Branding',
                  href: brandingEdit({ current_team: page.props.currentTeam.slug }).url,
                  icon: Palette,
              },
          ]
        : []),
    { title: 'Teams', href: teamsIndex().url, icon: Users },
    { title: 'Settings', href: editProfile().url, icon: Settings },
]);

const leadsUrl = computed(() =>
    page.props.currentTeam
        ? leadsIndex({ current_team: page.props.currentTeam.slug }).url
        : '/',
);
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

        <SidebarContent class="gap-2 pt-2">
            <NavMain label="Overview" :items="overviewItems" />
            <NavMain v-if="page.props.auth.isAdmin" label="Admin" :items="adminItems" />
            <NavMain
                v-if="pipelineItems.length"
                label="Pipeline"
                :items="pipelineItems"
                :action="{ title: 'New search', href: leadsUrl }"
            />
        </SidebarContent>

        <SidebarFooter class="gap-3 px-3 pb-4">
            <NavMain label="Tools" :items="toolItems" class="px-0" />
            <TeamSwitcher />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
