<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { LifeBuoy } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    page: {
        title: string;
        paragraphs: string[];
        supportUrl: string;
        supportEmail: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Upgrades', href: '#' }],
    },
});
</script>

<template>
    <Head :title="page.title" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading :title="page.title" description="Your purchase is active." />

        <section class="panel space-y-4 p-5 text-sm leading-relaxed">
            <p v-for="(paragraph, index) in page.paragraphs" :key="index">
                {{ paragraph }}
            </p>
            <div class="flex flex-wrap gap-2 pt-2">
                <Button as-child>
                    <a :href="page.supportUrl" target="_blank" rel="noopener noreferrer">
                        <LifeBuoy /> Open support desk
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="`mailto:${page.supportEmail}`">{{ page.supportEmail }}</a>
                </Button>
            </div>
        </section>
    </div>
</template>
