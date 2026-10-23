<script setup lang="ts">
import CheckList from '@/components/CheckList.vue';
import type { VerificationReport } from '@/types';

defineProps<{
    verification: VerificationReport;
}>();
</script>

<template>
    <section class="panel p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">Content check</h2>
                <p class="text-sm text-muted-foreground">
                    The site only states what is verified. Fill the gaps for
                    better copy.
                </p>
            </div>
            <span class="text-2xl font-semibold tabular-nums"
                >{{ verification.readiness }}%</span
            >
        </div>

        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
            <div
                class="h-full rounded-full bg-gradient-to-r from-violet-400 to-emerald-500 transition-[width] duration-500"
                :style="{ width: `${verification.readiness}%` }"
            />
        </div>

        <CheckList class="mt-5" :items="verification.items" />
    </section>
</template>
