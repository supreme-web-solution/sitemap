<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, MessageSquareText } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { hourOptions } from '@/lib/hourOptions';
import { timezoneOptionsIncluding } from '@/lib/timezones';
import { index as leadsIndex } from '@/routes/leads';
import { destroy, store } from '@/routes/messaging';

type Account = {
    phone: string | null;
    whatsappReady: boolean;
    timezone: string;
    quietStartHour: number;
    quietEndHour: number;
};

const props = defineProps<{
    canManage: boolean;
    available: boolean;
    connected: boolean;
    account: Account | null;
    defaultTimezone?: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Messaging', href: '#' }] },
});

const page = usePage();
const teamSlug = () => page.props.currentTeam?.slug ?? '';

const form = useForm({
    phone: props.account?.phone ?? '',
    timezone: props.account?.timezone ?? props.defaultTimezone ?? 'America/New_York',
    quiet_start_hour: props.account?.quietStartHour ?? 21,
    quiet_end_hour: props.account?.quietEndHour ?? 8,
});

const timezones = computed(() => timezoneOptionsIncluding(form.timezone));

const quietStartHour = computed({
    get: () => String(form.quiet_start_hour),
    set: (value: string) => {
        form.quiet_start_hour = Number(value);
    },
});

const quietEndHour = computed({
    get: () => String(form.quiet_end_hour),
    set: (value: string) => {
        form.quiet_end_hour = Number(value);
    },
});

const submit = () => {
    form.put(store.url(teamSlug()), { preserveScroll: true });
};

const disconnect = () => {
    if (confirm('Disconnect messaging? Past conversations stay on each lead.')) {
        router.delete(destroy.url(teamSlug()), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Messaging" />

    <div class="mx-auto flex w-full max-w-lg flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Messaging"
            description="Connect the number you text from. Chats happen on each lead profile — open a lead and use Text & WhatsApp in the sidebar."
        />

        <p v-if="!available" class="panel p-4 text-sm text-amber-700 dark:text-amber-300">
            Messaging is not included in your plan. Ask your administrator to upgrade it.
        </p>

        <section
            v-else-if="connected && account"
            class="panel flex gap-3 p-4 text-sm"
            data-test="messaging-how-it-works"
        >
            <MessageSquareText class="size-5 shrink-0 text-primary" />
            <div class="grid gap-2">
                <p class="font-medium">Connected as {{ account.phone }}</p>
                <div class="flex flex-wrap gap-2">
                    <Badge variant="secondary">SMS</Badge>
                    <Badge v-if="account.whatsappReady" variant="secondary">WhatsApp</Badge>
                </div>
                <p class="text-muted-foreground">
                    Open a lead, confirm they agreed to messages, then send from the thread on
                    their profile.
                </p>
                <Button variant="outline" size="sm" class="mt-1 w-fit" as-child>
                    <Link :href="leadsIndex.url(teamSlug())">Go to leads</Link>
                </Button>
            </div>
        </section>

        <form v-if="available" class="grid gap-4 panel p-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="phone">Your business number</Label>
                <Input
                    id="phone"
                    v-model="form.phone"
                    :disabled="!canManage"
                    placeholder="+1 555 123 4567"
                    autocomplete="tel"
                />
                <p class="text-xs text-muted-foreground">
                    Include country code. The same number is used for SMS; WhatsApp is enabled
                    automatically when it is linked to this number on our side.
                </p>
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label>Your time zone</Label>
                <Select v-model="form.timezone" :disabled="!canManage">
                    <SelectTrigger class="w-full" aria-label="Your time zone">
                        <SelectValue placeholder="Choose a time zone" />
                    </SelectTrigger>
                    <SelectContent class="max-h-72">
                        <SelectItem
                            v-for="zone in timezones"
                            :key="zone.value"
                            :value="zone.value"
                        >
                            {{ zone.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.timezone" />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Do not send after</Label>
                    <Select v-model="quietStartHour" :disabled="!canManage">
                        <SelectTrigger class="w-full" aria-label="Do not send after">
                            <SelectValue placeholder="Choose a time" />
                        </SelectTrigger>
                        <SelectContent class="max-h-60">
                            <SelectItem
                                v-for="hour in hourOptions"
                                :key="hour.value"
                                :value="hour.value"
                            >
                                {{ hour.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.quiet_start_hour" />
                </div>
                <div class="grid gap-2">
                    <Label>Resume sending at</Label>
                    <Select v-model="quietEndHour" :disabled="!canManage">
                        <SelectTrigger class="w-full" aria-label="Resume sending at">
                            <SelectValue placeholder="Choose a time" />
                        </SelectTrigger>
                        <SelectContent class="max-h-60">
                            <SelectItem
                                v-for="hour in hourOptions"
                                :key="`end-${hour.value}`"
                                :value="hour.value"
                            >
                                {{ hour.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.quiet_end_hour" />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                We only message leads who agreed on their profile. STOP opts them out; replies
                are blocked during quiet hours.
            </p>

            <div v-if="canManage" class="flex gap-2">
                <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ connected ? 'Save' : 'Connect' }}
                </Button>
                <Button
                    v-if="connected"
                    type="button"
                    variant="ghost"
                    class="text-destructive hover:text-destructive"
                    @click="disconnect"
                >
                    Disconnect
                </Button>
            </div>
        </form>
    </div>
</template>
