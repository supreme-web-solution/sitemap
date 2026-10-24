<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import NavMenuList from '@/components/NavMenuList.vue';
import {
    SidebarGroup,
    SidebarGroupAction,
    SidebarGroupLabel,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

withDefaults(
    defineProps<{
        label: string;
        items: NavItem[];
        action?: { title: string; href: NavItem['href'] };
    }>(),
    { action: undefined },
);
</script>

<template>
    <SidebarGroup class="px-3 py-1">
        <SidebarGroupLabel
            v-if="label"
            class="h-7 px-2 eyebrow text-sidebar-foreground/55"
        >
            {{ label }}
        </SidebarGroupLabel>
        <SidebarGroupAction
            v-if="action"
            as-child
            :title="action.title"
            class="top-2 size-5 rounded-full bg-sidebar-accent text-sidebar-foreground/70 hover:bg-accent hover:text-accent-foreground"
        >
            <Link :href="action.href">
                <Plus class="size-3.5" />
                <span class="sr-only">{{ action.title }}</span>
            </Link>
        </SidebarGroupAction>
        <NavMenuList :items="items" />
    </SidebarGroup>
</template>
