<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SidebarNavIcon from '@/components/SidebarNavIcon.vue';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { playSidebarIconDrawWithin } from '@/composables/useSidebarIconDraw';
import { useNavItemActive } from '@/composables/useNavItemActive';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{
        items: NavItem[];
        compact?: boolean;
        /** Indented under a section with a left connector line */
        grouped?: boolean;
    }>(),
    { compact: false, grouped: false },
);

const { isNavItemActive } = useNavItemActive();

const buttonClass =
    'relative font-normal text-sidebar-foreground/80 hover:bg-sidebar-accent data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground data-[active=true]:[&_[data-sidebar-nav-icon]]:text-primary';

const activeRailClass =
    'data-[active=true]:before:absolute data-[active=true]:before:top-1/2 data-[active=true]:before:-left-3 data-[active=true]:before:h-5 data-[active=true]:before:w-[3px] data-[active=true]:before:-translate-y-1/2 data-[active=true]:before:rounded-r-full data-[active=true]:before:bg-primary data-[active=true]:before:content-[\'\']';

function onItemHover(event: MouseEvent): void {
    playSidebarIconDrawWithin(event.currentTarget as HTMLElement);
}
</script>

<template>
    <SidebarMenu class="gap-0.5">
        <SidebarMenuItem v-for="item in items" :key="item.title">
            <SidebarMenuButton
                as-child
                :is-active="isNavItemActive(item)"
                :tooltip="item.title"
                :class="[
                    buttonClass,
                    !props.grouped && activeRailClass,
                    props.grouped
                        ? 'rounded-lg px-2'
                        : 'rounded-xl px-2.5',
                    compact
                        ? 'h-8 text-xs'
                        : 'h-9 text-[0.9rem]',
                ]"
            >
                <Link :href="item.href" @mouseenter="onItemHover">
                    <span
                        v-if="item.dot"
                        class="mx-[0.3rem] size-2 shrink-0 rounded-full ring-4 ring-sidebar-accent/80"
                        :class="item.dot"
                    />
                    <SidebarNavIcon
                        v-else-if="item.icon"
                        :icon="item.icon"
                        :size="compact ? 'compact' : 'default'"
                    />
                    <span>{{ item.title }}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
