<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { ChevronRight, Plus } from '@lucide/vue';
import { computed } from 'vue';
import NavMenuList from '@/components/NavMenuList.vue';
import SidebarNavIcon from '@/components/SidebarNavIcon.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { SidebarGroup } from '@/components/ui/sidebar';
import { playSidebarIconDrawWithin } from '@/composables/useSidebarIconDraw';
import { useNavItemActive } from '@/composables/useNavItemActive';
import type { NavItem } from '@/types';

const props = defineProps<{
    label: string;
    icon: LucideIcon;
    items: NavItem[];
    subItems?: NavItem[];
    subLabel?: string;
    action?: { title: string; href: NavItem['href'] };
}>();

const { isAnyNavItemActive } = useNavItemActive();

const sectionActive = computed(
    () =>
        isAnyNavItemActive(props.items) ||
        (props.subItems ? isAnyNavItemActive(props.subItems) : false),
);

const defaultOpen = computed(() => sectionActive.value);

function onTriggerHover(event: MouseEvent): void {
    playSidebarIconDrawWithin(event.currentTarget as HTMLElement);
}
</script>

<template>
    <Collapsible :default-open="defaultOpen" as-child>
        <SidebarGroup class="px-3 py-0.5">
            <div class="flex h-9 w-full items-center gap-0.5">
                <CollapsibleTrigger
                    class="group/trigger flex h-9 min-w-0 flex-1 items-center gap-2 rounded-xl px-2.5 text-sidebar-foreground/80 hover:bg-sidebar-accent data-[state=open]:bg-sidebar-accent/70 data-[state=open]:text-sidebar-accent-foreground"
                    :class="
                        sectionActive
                            ? 'bg-sidebar-accent/50 font-medium text-sidebar-accent-foreground [&_[data-sidebar-nav-icon]]:text-primary'
                            : 'font-normal'
                    "
                    @mouseenter="onTriggerHover"
                >
                    <SidebarNavIcon :icon="icon" />
                    <span
                        class="min-w-0 flex-1 truncate text-left text-[0.9rem]"
                    >
                        {{ label }}
                    </span>
                    <ChevronRight
                        class="size-3.5 shrink-0 text-sidebar-foreground/45 transition-transform group-data-[state=open]/trigger:rotate-90 group-data-[state=open]/trigger:text-sidebar-foreground/70"
                    />
                </CollapsibleTrigger>
                <Link
                    v-if="action"
                    :href="action.href"
                    :title="action.title"
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <Plus class="size-3.5" />
                    <span class="sr-only">{{ action.title }}</span>
                </Link>
            </div>

            <CollapsibleContent
                class="data-[state=closed]:animate-collapse-up data-[state=open]:animate-collapse-down"
            >
                <div
                    class="mt-0.5 ml-[1.2rem] border-l border-sidebar-border/80 pl-2.5 group-data-[collapsible=icon]:hidden"
                >
                    <NavMenuList :items="items" grouped />

                    <template v-if="subItems?.length">
                        <p
                            class="mt-2 px-2 text-[0.65rem] font-medium tracking-wide text-sidebar-foreground/45 uppercase"
                        >
                            {{ subLabel ?? 'Shortcuts' }}
                        </p>
                        <NavMenuList :items="subItems" compact grouped />
                    </template>
                </div>
            </CollapsibleContent>
        </SidebarGroup>
    </Collapsible>
</template>
