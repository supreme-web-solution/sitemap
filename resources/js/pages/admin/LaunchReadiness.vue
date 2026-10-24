<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import {
    dashboard as adminDashboard,
    launchReadiness as adminLaunchReadiness,
} from '@/routes/admin';

type Check = {
    id: string;
    category: string;
    status: 'pass' | 'warn' | 'fail';
    title: string;
    message: string;
    hint: string | null;
};

defineProps<{
    report: {
        ok: boolean;
        summary: { pass: number; warn: number; fail: number };
        checks: Check[];
    };
    liveCommand: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Launch readiness', href: adminLaunchReadiness() },
        ],
    },
});

const statusClass = (status: Check['status']) => {
    switch (status) {
        case 'pass':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
        case 'warn':
            return 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200';
        case 'fail':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300';
    }
};
</script>

<template>
    <Head title="Launch readiness" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Launch readiness"
            description="Same checks as ops:verify on the server. Warnings are acceptable in local dev; failures must be cleared before production cutover."
        />

        <div
            class="panel flex flex-wrap items-center justify-between gap-3 p-4"
            data-test="launch-readiness-summary"
        >
            <div class="flex flex-wrap gap-3 text-sm">
                <span
                    class="rounded-full px-2.5 py-0.5 font-medium"
                    :class="
                        report.ok
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15'
                            : 'bg-rose-100 text-rose-800 dark:bg-rose-500/15'
                    "
                >
                    {{ report.ok ? 'Ready (no failures)' : 'Fix failures first' }}
                </span>
                <span class="text-muted-foreground"
                    >{{ report.summary.pass }} pass ·
                    {{ report.summary.warn }} warn ·
                    {{ report.summary.fail }} fail</span
                >
            </div>
            <Link
                :href="adminDashboard()"
                class="text-sm text-primary hover:underline"
                >Back to admin</Link
            >
        </div>

        <p class="text-sm text-muted-foreground">
            On Forge SSH after deploying:
            <code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{
                liveCommand
            }}</code>
        </p>

        <ul class="flex flex-col gap-2">
            <li
                v-for="check in report.checks"
                :key="check.id"
                class="panel p-4"
                :data-test="`launch-check-${check.id}`"
            >
                <div class="flex flex-wrap items-start gap-2">
                    <span
                        class="rounded-md px-2 py-0.5 text-xs font-semibold uppercase"
                        :class="statusClass(check.status)"
                    >
                        {{ check.status }}
                    </span>
                    <span
                        class="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >{{ check.category }}</span
                    >
                    <p class="w-full text-sm font-medium">{{ check.title }}</p>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ check.message }}
                </p>
                <p v-if="check.hint" class="mt-2 text-xs text-muted-foreground">
                    {{ check.hint }}
                </p>
            </li>
        </ul>
    </div>
</template>
