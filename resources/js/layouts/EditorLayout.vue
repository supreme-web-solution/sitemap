<script setup lang="ts">
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="flex h-dvh flex-col overflow-hidden bg-background">
        <ImpersonationBanner />
        <main class="flex min-h-0 flex-1 flex-col">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
