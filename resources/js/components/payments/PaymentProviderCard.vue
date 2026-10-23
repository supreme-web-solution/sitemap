<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, Copy, LoaderCircle } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { connect, disconnect } from '@/routes/payments';
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
const editing = ref(!props.provider.connected);
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
            editing.value = false;
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

const copyWebhook = async () => {
    if (props.provider.webhookUrl) {
        await copy(props.provider.webhookUrl);
        toast.success('Webhook URL copied');
    }
};

const credentialsError = computed(
    () => (form.errors as Record<string, string | undefined>).credentials,
);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <section class="panel p-5" :data-test="`provider-${provider.value}`">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">{{ provider.label }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        isStripe
                            ? 'Let clients pay invoices by card.'
                            : isPayPal
                              ? 'Let clients pay with their PayPal balance or card.'
                              : isPaystack
                                ? 'Card payments for Nigeria and other African markets.'
                                : 'Card payments via Flutterwave.'
                    }}
                </p>
            </div>
            <span
                v-if="provider.connected"
                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
            >
                <Check class="size-3" /> Connected
            </span>
        </div>

        <div
            v-if="provider.connected && !editing"
            class="mt-4 flex flex-col gap-3"
        >
            <p class="text-sm">
                <span class="font-medium">{{ provider.accountLabel }}</span>
                <span class="text-muted-foreground">
                    · key {{ provider.hint
                    }}<template v-if="provider.mode">
                        · {{ provider.mode }} mode</template
                    ></span
                >
            </p>

            <div
                v-if="provider.webhookUrl"
                class="rounded-xl border bg-muted/40 p-3 text-sm"
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
                    In Stripe, go to Developers → Webhooks → Add endpoint. Use
                    this URL and select
                    <code>checkout.session.completed</code>, then paste the
                    signing secret below. Without it, payments are still
                    confirmed when the customer returns to the invoice, but a
                    closed browser tab could delay it.
                </p>
                <p v-else-if="isPayPal" class="mt-1 text-xs text-muted-foreground">
                    In your PayPal app, open Webhooks → Add webhook. Use this
                    URL and select <code>Checkout order approved</code> and
                    <code>Payment capture completed</code>, then paste the
                    webhook ID. This finishes the payment even if the customer
                    closes the tab after approving it.
                </p>
                <p v-else-if="isPaystack" class="mt-1 text-xs text-muted-foreground">
                    In Paystack, add this URL as a webhook and paste the
                    signing secret. Events use HMAC SHA-512.
                </p>
                <p v-else class="mt-1 text-xs text-muted-foreground">
                    In Flutterwave, set this URL as the webhook and paste the
                    secret hash you configured there.
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

            <div v-if="canManage" class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="editing = true"
                >
                    Update keys
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    @click="remove"
                >
                    Disconnect
                </Button>
            </div>
        </div>

        <form
            v-else-if="canManage"
            class="mt-4 grid gap-3"
            @submit.prevent="submit"
        >
            <template v-if="usesSecretKey">
                <div class="grid gap-2">
                    <Label :for="`${provider.value}-secret`">Secret key</Label>
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
                        :placeholder="isStripe ? 'whsec_…' : 'Paystack webhook secret'"
                    />
                    <InputError :message="form.errors.webhook_secret" />
                </div>
                <div v-else class="grid gap-2">
                    <Label :for="`${provider.value}-hash`">Webhook hash (optional)</Label>
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
                    <Label :for="`${provider.value}-client`">Client ID</Label>
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
                        developer.paypal.com → Apps &amp; Credentials → create
                        an app, then copy its client ID and secret.
                    </p>
                    <InputError :message="form.errors.client_secret" />
                </div>
                <div class="grid gap-2">
                    <Label :for="`${provider.value}-mode`">Mode</Label>
                    <select
                        :id="`${provider.value}-mode`"
                        v-model="form.mode"
                        :class="selectClass"
                    >
                        <option value="live">Live (real payments)</option>
                        <option value="sandbox">Sandbox (testing)</option>
                    </select>
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

            <div class="flex gap-2">
                <Button type="submit" size="sm" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Verify &amp; connect
                </Button>
                <Button
                    v-if="provider.connected"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="editing = false"
                >
                    Cancel
                </Button>
            </div>
        </form>

        <p v-else class="mt-4 text-sm text-muted-foreground">
            Ask a team admin to connect {{ provider.label }}.
        </p>
    </section>
</template>
