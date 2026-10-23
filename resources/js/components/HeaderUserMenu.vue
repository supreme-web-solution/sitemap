<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';

const user = usePage().props.auth.user;
const { getInitials } = useInitials();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="group flex items-center gap-1.5 rounded-full p-0.5 pr-1.5 outline-none hover:bg-accent focus-visible:ring-4 focus-visible:ring-primary/15 data-[state=open]:bg-accent"
                data-test="sidebar-menu-button"
                :aria-label="`Account menu for ${user.name}`"
            >
                <Avatar class="size-8 ring-2 ring-primary/15">
                    <AvatarImage
                        v-if="user.avatar"
                        :src="user.avatar"
                        :alt="user.name"
                    />
                    <AvatarFallback
                        class="bg-gradient-to-br from-violet-400 to-primary text-xs font-semibold text-white"
                    >
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
                <ChevronDown
                    class="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="min-w-60 rounded-xl p-1.5"
            align="end"
            :side-offset="8"
        >
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
