<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import CheckList from '@/components/CheckList.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRelativeTime } from '@/lib/format';
import { store as runHealth } from '@/routes/sites/health';
import { store as transferSite } from '@/routes/sites/transfer';
import type { SiteHealth } from '@/types';

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    health: SiteHealth;
    transferredAt: string | null;
    transferredToEmail: string | null;
    canFix?: boolean;
    fixing?: boolean;
}>();

const emit = defineEmits<{
    fix: [key: string];
}>();

const args = { current_team: props.teamSlug, site: props.siteId };
const form = useForm({ email: '' });

const check = () =>
    router.post(runHealth.url(args), {}, { preserveScroll: true });

const transfer = () =>
    form.post(transferSite.url(args), { preserveScroll: true });
</script>

<template>
    <section class="panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold">Delivery checks</h2>
            <Button type="button" size="sm" variant="outline" @click="check">
                Run health check
            </Button>
        </div>
        <p class="mt-2 text-sm text-muted-foreground">
            Uptime
            <template v-if="health.lastUptimeAt">
                {{ health.uptimeOk ? 'OK' : 'down' }}
                · {{ formatRelativeTime(health.lastUptimeAt) }}
            </template>
            <template v-else>· not checked yet</template>
        </p>
        <p class="text-sm text-muted-foreground">
            {{ health.backupCount }} stored backups
            <template v-if="health.lastCrawledAt">
                · crawled {{ formatRelativeTime(health.lastCrawledAt) }}
            </template>
        </p>
        <CheckList
            v-if="health.findings.length"
            class="mt-4"
            :items="health.findings"
            :on-fix="canFix ? (key) => emit('fix', key) : undefined"
            :fixable-keys="['alt']"
            :busy="fixing"
        />
        <ul v-if="health.brokenLinks.length" class="mt-3 grid gap-1 text-sm">
            <li v-for="link in health.brokenLinks" :key="link.id" class="break-all">
                {{ link.url }}
                <span class="text-muted-foreground">
                    ({{ link.status ?? link.error ?? 'failed' }})
                </span>
            </li>
        </ul>
        <div v-if="health.comments.length" class="mt-4">
            <h3 class="text-sm font-medium">Preview comments</h3>
            <ul class="mt-2 grid gap-2 text-sm">
                <li v-for="comment in health.comments" :key="comment.id">
                    <span class="font-medium">{{ comment.author }}</span>
                    <span v-if="comment.section" class="text-muted-foreground">
                        on {{ comment.section }}
                    </span>
                    <p>{{ comment.body }}</p>
                </li>
            </ul>
        </div>
        <form class="mt-4 grid gap-2" @submit.prevent="transfer">
            <Label for="transfer-email">Transfer ownership</Label>
            <Input
                id="transfer-email"
                v-model="form.email"
                type="email"
                required
                placeholder="client@example.com"
            />
            <p class="text-xs text-muted-foreground">
                Unpublishes the live copy, stores a backup, and emails the client.
            </p>
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Transfer
            </Button>
            <p v-if="transferredAt" class="text-xs text-muted-foreground">
                Transferred to {{ transferredToEmail }}
                {{ formatRelativeTime(transferredAt) }}.
            </p>
        </form>
    </section>
</template>
