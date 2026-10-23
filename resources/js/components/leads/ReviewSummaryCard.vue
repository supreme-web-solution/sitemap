<script setup lang="ts">
defineProps<{
    summary: {
        reviewCount: number;
        averageRating: number | null;
        praise: string[];
        complaints: string[];
    } | null;
}>();
</script>

<template>
    <section v-if="summary" class="panel p-5">
        <h2 class="text-base font-semibold">Review summary</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            From {{ summary.reviewCount }} stored reviews
            <template v-if="summary.averageRating">
                · {{ summary.averageRating }} average
            </template>
        </p>
        <p v-if="summary.praise.length" class="mt-3 text-sm">
            <span class="font-medium">Praise:</span>
            {{ summary.praise.join(', ') }}
        </p>
        <p v-if="summary.complaints.length" class="mt-2 text-sm">
            <span class="font-medium">Complaints:</span>
            {{ summary.complaints.join(', ') }}
        </p>
        <p
            v-if="!summary.praise.length && !summary.complaints.length"
            class="mt-3 text-sm text-muted-foreground"
        >
            Not enough overlapping themes yet.
        </p>
    </section>
</template>
