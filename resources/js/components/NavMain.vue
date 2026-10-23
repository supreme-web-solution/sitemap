<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import {
    SidebarGroup,
    SidebarGroupAction,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

withDefaults(
    defineProps<{
        label: string;
        items: NavItem[];
        action?: { title: string; href: NavItem['href'] };
    }>(),
    { action: undefined },
);

const { isCurrentUrl } = useCurrentUrl();

const isActive = (item: NavItem) =>
    !item.dot && isCurrentUrl(item.href, undefined, !item.exact);
</script>

<template>
    <SidebarGroup class="px-3 py-1">
        <SidebarGroupLabel class="h-7 px-2 eyebrow text-sidebar-foreground/55">
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
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item)"
                    :tooltip="item.title"
                    class="relative h-9 rounded-xl px-2.5 text-[0.9rem] font-normal text-sidebar-foreground/80 hover:bg-sidebar-accent data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground data-[active=true]:before:absolute data-[active=true]:before:top-1/2 data-[active=true]:before:-left-3 data-[active=true]:before:h-5 data-[active=true]:before:w-[3px] data-[active=true]:before:-translate-y-1/2 data-[active=true]:before:rounded-r-full data-[active=true]:before:bg-primary data-[active=true]:before:content-[''] [&>svg]:size-[1.05rem] [&>svg]:text-sidebar-foreground/60 data-[active=true]:[&>svg]:text-primary"
                >
                    <Link :href="item.href">
                        <span
                            v-if="item.dot"
                            class="mx-[0.3rem] size-2 shrink-0 rounded-full ring-4 ring-sidebar-accent/80"
                            :class="item.dot"
                        />
                        <component :is="item.icon" v-else-if="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
