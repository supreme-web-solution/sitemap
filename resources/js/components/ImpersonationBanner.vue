<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { UserRoundCog } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { stop } from '@/routes/impersonation';

const page = usePage();
</script>

<template>
    <div
        v-if="page.props.impersonating"
        class="flex flex-wrap items-center justify-between gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950"
        data-test="impersonation-banner"
    >
        <span class="flex items-center gap-2">
            <UserRoundCog class="size-4" />
            You are viewing the app as {{ page.props.auth.user.name }}.
        </span>
        <Button
            size="sm"
            variant="secondary"
            @click="router.post(stop.url())"
        >
            Back to {{ page.props.impersonating.name }}
        </Button>
    </div>
</template>