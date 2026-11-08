<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();

/** Full-viewport boards should not scroll the main chrome — columns scroll inside. */
const locksViewport = computed(() => page.component === 'leads/Board');
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="min-h-0 min-w-0 overflow-hidden"
        >
            <ImpersonationBanner />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                class="flex min-h-0 flex-1 flex-col"
                :class="
                    locksViewport ? 'overflow-hidden' : 'overflow-y-auto'
                "
            >
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
