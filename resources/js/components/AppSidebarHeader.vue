<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Moon, Sparkles, Sun } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { index as leadsIndex } from '@/routes/leads';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const { isMobile, state } = useSidebar();
const { resolvedAppearance, updateAppearance } = useAppearance();

const showTrigger = computed(
    () => isMobile.value || state.value === 'collapsed',
);

const leadsUrl = computed(() =>
    page.props.currentTeam
        ? leadsIndex({ current_team: page.props.currentTeam.slug }).url
        : null,
);

const toggleTheme = () =>
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b bg-card/85 px-4 backdrop-blur-md md:px-6"
    >
        <SidebarTrigger
            v-if="showTrigger"
            class="-ml-1 text-muted-foreground"
        />
        <Breadcrumbs v-if="breadcrumbs.length > 0" :breadcrumbs="breadcrumbs" />

        <div class="ml-auto flex items-center gap-2">
            <Button
                v-if="leadsUrl"
                as-child
                variant="outline"
                size="sm"
                class="hidden h-9 rounded-full border-primary/25 px-3.5 text-primary hover:border-primary/40 sm:inline-flex"
            >
                <Link :href="leadsUrl">
                    New search
                    <Sparkles class="size-3.5" />
                </Link>
            </Button>
            <Button
                variant="outline"
                size="icon"
                class="size-9 rounded-full text-muted-foreground"
                aria-label="Toggle theme"
                @click="toggleTheme"
            >
                <Sun v-if="resolvedAppearance === 'dark'" />
                <Moon v-else />
            </Button>
            <HeaderUserMenu />
        </div>
    </header>
</template>
