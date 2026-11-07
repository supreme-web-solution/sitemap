<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, Copy, LoaderCircle } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { connect, disconnect, update as updatePayment } from '@/routes/payments';
import type { PaymentProviderSummary } from '@/types';

const props = defineProps<{
    provider: PaymentProviderSummary;
    teamSlug: string;
    canManage: boolean;
}>();

const isStripe = computed(() => props.provider.value === 'stripe');
const isPayPal = computed(() => props.provider.value === 'paypal');
const isPaystack = computed(() => props.provider.value === 'paystack');
const isFlutterwave = computed(() => props.provider.value === 'flutterwave');
const usesSecretKey = computed(
    () => isStripe.value || isPaystack.value || isFlutterwave.value,
);

const formOpen = ref(false);
const { copy } = useClipboard();

const form = useForm({
    secret_key: '',
    webhook_secret: '',
    client_id: '',
    client_secret: '',
    mode: 'live',
    webhook_id: '',
    webhook_hash: '',
});

const routeArgs = computed(() => ({
    current_team: props.teamSlug,
    provider: props.provider.value,
}));

const blurb = computed(() => {
    if (isStripe.value) {
        return 'Let clients pay invoices by card.';
    }

    if (isPayPal.value) {
        return 'Let clients pay with their PayPal balance or card.';
    }

    if (isPaystack.value) {
        return 'Card payments for Nigeria and other African markets.';
    }

    return 'Card payments via Flutterwave.';
});

watch(formOpen, (open) => {
    if (!open) {
        form.clearErrors();
        form.reset();
    }
});

const openForm = () => {
    form.clearErrors();
    form.reset();
    formOpen.value = true;
};

const submit = () => {
    form.transform((data) => {
        if (isStripe.value || isPaystack.value) {
            return {
                secret_key: data.secret_key,
                webhook_secret: data.webhook_secret,
            };
        }

        if (isFlutterwave.value) {
            return {
                secret_key: data.secret_key,
                webhook_hash: data.webhook_hash,
            };
        }

        return {
            client_id: data.client_id,
            client_secret: data.client_secret,
            mode: data.mode,
            webhook_id: data.webhook_id,
        };
    }).put(connect.url(routeArgs.value), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            formOpen.value = false;
        },
    });
};

const remove = () => {
    if (
        !confirm(
            `Disconnect ${props.provider.label}? Customers will no longer be able to pay open invoices with it.`,
        )
    ) {
        return;
    }

    router.delete(disconnect.url(routeArgs.value), { preserveScroll: true });
};

const setEnabled = (enabled: boolean) => {
    router.patch(
        updatePayment.url(routeArgs.value),
        { enabled },
        { preserveScroll: true },
    );
};

const copyWebhook = async () => {
    if (props.provider.webhookUrl) {
        await copy(props.provider.webhookUrl);
        toast.success('Webhook URL copied');
    }
};

const credentialsError = computed(
    () => (form.errors as Record<string, string | undefined>).credentials,
);
</script>

<template>
    <section class="panel p-5" :data-test="`provider-${provider.value}`">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold">{{ provider.label }}</h2>
                    <span
                        v-if="provider.connected"
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                    >
                        <Check class="size-3" /> Connected
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">{{ blurb }}</p>
                <p v-if="provider.connected" class="mt-2 text-sm">
                    <span class="font-medium">{{ provider.accountLabel }}</span>
                    <span class="text-muted-foreground">
                        · key {{ provider.hint
                        }}<template v-if="provider.mode">
                            · {{ provider.mode }} mode</template
                        ></span
                    >
                </p>
            </div>

            <div v-if="canManage" class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="provider.connected ? 'outline' : 'default'"
                    @click="openForm"
                >
                    {{ provider.connected ? 'Update keys' : 'Connect' }}
                </Button>
                <Button
                    v-if="provider.connected"
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    @click="remove"
                >
                    Disconnect
                </Button>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Ask a team admin to connect {{ provider.label }}.
            </p>
        </div>

        <label
            v-if="provider.connected && canManage"
            class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border bg-muted/30 p-3"
            data-test="provider-enabled"
        >
            <Checkbox
                class="mt-0.5"
                :checked="provider.enabled"
                @update:checked="(v: boolean | 'indeterminate') => setEnabled(v === true)"
            />
            <span class="min-w-0">
                <span class="block text-sm font-medium">Offer on invoices</span>
                <span class="block text-xs text-muted-foreground">
                    When on, customers see this on the pay link. Connecting keys
                    alone does not show it.
                </span>
            </span>
        </label>
        <p
            v-else-if="provider.connected && !provider.enabled"
            class="mt-4 text-sm text-amber-700 dark:text-amber-300"
        >
            Connected, but hidden from invoices until an admin turns it on.
        </p>

        <div
            v-if="provider.connected && provider.webhookUrl"
            class="mt-4 rounded-xl border bg-muted/40 p-3 text-sm"
        >
            <p class="font-medium">
                Webhook
                <span
                    :class="
                        provider.hasWebhookSecret
                            ? 'text-emerald-600'
                            : 'text-amber-600'
                    "
                    >{{
                        provider.hasWebhookSecret
                            ? '· verified'
                            : '· not set up yet'
                    }}</span
                >
            </p>
            <p v-if="isStripe" class="mt-1 text-xs text-muted-foreground">
                In Stripe, go to Developers → Webhooks → Add endpoint. Use this
                URL and select
                <code>checkout.session.completed</code>, then paste the signing
                secret when you update keys.
            </p>
            <p
                v-else-if="isPayPal"
                class="mt-1 text-xs text-muted-foreground"
            >
                In your PayPal app, open Webhooks → Add webhook. Use this URL
                and select <code>Checkout order approved</code> and
                <code>Payment capture completed</code>, then paste the webhook
                ID when you update keys.
            </p>
            <p
                v-else-if="isPaystack"
                class="mt-1 text-xs text-muted-foreground"
            >
                In Paystack, add this URL as a webhook and paste the signing
                secret when you update keys.
            </p>
            <p v-else class="mt-1 text-xs text-muted-foreground">
                In Flutterwave, set this URL as the webhook and paste the secret
                hash when you update keys.
            </p>
            <div class="mt-2 flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 truncate rounded-md bg-background px-2 py-1.5 text-xs"
                    >{{ provider.webhookUrl }}</code
                >
                <Button
                    type="button"
                    size="icon-sm"
                    variant="outline"
                    aria-label="Copy webhook URL"
                    @click="copyWebhook"
                >
                    <Copy />
                </Button>
            </div>
        </div>

        <Dialog v-model:open="formOpen">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            provider.connected
                                ? `Update ${provider.label}`
                                : `Connect ${provider.label}`
                        }}
                    </DialogTitle>
                    <DialogDescription>{{ blurb }}</DialogDescription>
                </DialogHeader>

                <form class="grid gap-3" @submit.prevent="submit">
                    <template v-if="usesSecretKey">
                        <div class="grid gap-2">
                            <Label :for="`${provider.value}-secret`"
                                >Secret key</Label
                            >
                            <Input
                                :id="`${provider.value}-secret`"
                                v-model="form.secret_key"
                                type="password"
                                autocomplete="off"
                                placeholder="sk_live_… or rk_live_…"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    isStripe
                                        ? 'Stripe Dashboard → Developers → API keys. A restricted key works if it can write Checkout Sessions and read the account.'
                                        : isPaystack
                                          ? 'Paystack Dashboard → Settings → API Keys & Webhooks.'
                                          : 'Flutterwave Dashboard → Settings → API Keys.'
                                }}
                            </p>
                            <InputError :message="form.errors.secret_key" />
                        </div>
                        <div v-if="!isFlutterwave" class="grid gap-2">
                            <Label :for="`${provider.value}-webhook`"
                                >Webhook signing secret (optional)</Label
                            >
                            <Input
                                :id="`${provider.value}-webhook`"
                                v-model="form.webhook_secret"
                                type="password"
                                autocomplete="off"
                                :placeholder="
                                    isStripe
                                        ? 'whsec_…'
                                        : 'Paystack webhook secret'
                                "
                            />
                            <InputError
                                :message="form.errors.webhook_secret"
                            />
                        </div>
                        <div v-else class="grid gap-2">
                            <Label :for="`${provider.value}-hash`"
                                >Webhook hash (optional)</Label
                            >
                            <Input
                                :id="`${provider.value}-hash`"
                                v-model="form.webhook_hash"
                                type="password"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.webhook_hash" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="grid gap-2">
                            <Label :for="`${provider.value}-client`"
                                >Client ID</Label
                            >
                            <Input
                                :id="`${provider.value}-client`"
                                v-model="form.client_id"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.client_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`${provider.value}-secret`"
                                >Client secret</Label
                            >
                            <Input
                                :id="`${provider.value}-secret`"
                                v-model="form.client_secret"
                                type="password"
                                autocomplete="off"
                            />
                            <p class="text-xs text-muted-foreground">
                                developer.paypal.com → Apps &amp; Credentials →
                                create an app, then copy its client ID and
                                secret.
                            </p>
                            <InputError :message="form.errors.client_secret" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`${provider.value}-mode`">Mode</Label>
                            <Select v-model="form.mode">
                                <SelectTrigger
                                    :id="`${provider.value}-mode`"
                                    class="w-full"
                                    aria-label="Mode"
                                >
                                    <SelectValue placeholder="Mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="live"
                                        >Live (real payments)</SelectItem
                                    >
                                    <SelectItem value="sandbox"
                                        >Sandbox (testing)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.mode" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`${provider.value}-webhook`"
                                >Webhook ID (optional)</Label
                            >
                            <Input
                                :id="`${provider.value}-webhook`"
                                v-model="form.webhook_id"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.webhook_id" />
                        </div>
                    </template>

                    <InputError :message="credentialsError" />

                    <DialogFooter class="gap-2 sm:justify-start">
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="form.processing"
                        >
                            <LoaderCircle
                                v-if="form.processing"
                                class="animate-spin"
                            />
                            Verify &amp; connect
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="formOpen = false"
                        >
                            Cancel
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </section>
</template>
