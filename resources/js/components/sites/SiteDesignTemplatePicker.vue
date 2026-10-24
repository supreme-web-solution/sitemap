<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LoaderCircle, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as designTemplatesIndex } from '@/routes/site-design-templates';

export type DesignTemplateOption = {
    id: number;
    slug: string;
    name: string;
    baseTemplate: string;
    baseTemplateLabel: string;
    style: string;
    styleLabel: string;
    industries: string[];
    accentColor: string;
    description: string | null;
};

const props = defineProps<{
    teamSlug: string;
    leadId: number;
    leadName: string;
    leadCategory: string | null;
    generateUrl: string;
    open: boolean;
}>();

const emit = defineEmits<{ close: [] }>();

const industry = ref('');
const style = ref('');
const palette = ref('');
const templates = ref<DesignTemplateOption[]>([]);
const styles = ref<{ value: string; label: string }[]>([]);
const loading = ref(false);
const selectedId = ref<number | null>(null);
const generating = ref(false);

const prefillIndustry = computed(() => props.leadCategory?.trim() ?? '');

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        industry.value = prefillIndustry.value;
        style.value = '';
        palette.value = '';
        selectedId.value = null;
        void loadTemplates();
    },
);

const loadTemplates = async () => {
    loading.value = true;

    try {
        const params = new URLSearchParams();
        if (industry.value) {
            params.set('industry', industry.value);
        }
        if (style.value) {
            params.set('style', style.value);
        }
        if (palette.value) {
            params.set('palette', palette.value);
        }

        const url = `${designTemplatesIndex.url({ current_team: props.teamSlug })}?${params.toString()}`;
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            templates.value = [];

            return;
        }

        const data = (await response.json()) as {
            templates: DesignTemplateOption[];
            styles: { value: string; label: string }[];
        };
        templates.value = data.templates;
        styles.value = data.styles;
    } finally {
        loading.value = false;
    }
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const generate = () => {
    generating.value = true;

    router.post(
        props.generateUrl,
        { design_template_id: selectedId.value },
        {
            preserveScroll: true,
            only: ['leads'],
            onFinish: () => {
                generating.value = false;
                emit('close');
            },
        },
    );
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        :aria-label="`Choose a design for ${leadName}`"
        @click.self="emit('close')"
    >
        <div
            class="panel flex max-h-[90vh] w-full max-w-2xl flex-col gap-4 overflow-hidden p-5"
        >
            <div>
                <h2 class="text-lg font-semibold">Choose a design</h2>
                <p class="text-sm text-muted-foreground">
                    Pick a catalog template for {{ leadName }}. All options use
                    the same three layouts with industry-specific accents and
                    copy tone.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="grid gap-1 sm:col-span-2">
                    <Label for="design-industry">Industry</Label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                        />
                        <Input
                            id="design-industry"
                            v-model="industry"
                            class="pl-8"
                            placeholder="e.g. plumbing"
                            @keydown.enter.prevent="loadTemplates"
                        />
                    </div>
                </div>
                <div class="grid gap-1">
                    <Label for="design-style">Style</Label>
                    <select
                        id="design-style"
                        v-model="style"
                        :class="selectClass"
                        @change="loadTemplates"
                    >
                        <option value="">Any</option>
                        <option
                            v-for="option in styles"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1 sm:col-span-3">
                    <Label for="design-palette">Accent (hex)</Label>
                    <Input
                        id="design-palette"
                        v-model="palette"
                        placeholder="#2563eb"
                        @keydown.enter.prevent="loadTemplates"
                    />
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="self-start"
                :disabled="loading"
                @click="loadTemplates"
            >
                <LoaderCircle v-if="loading" class="animate-spin" />
                Search templates
            </Button>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <p
                    v-if="!loading && templates.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No templates match. Clear filters or try a broader industry
                    keyword.
                </p>
                <ul class="grid gap-2">
                    <li v-for="template in templates" :key="template.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-lg border p-3 text-left transition-colors"
                            :class="
                                selectedId === template.id
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:bg-muted/40'
                            "
                            @click="selectedId = template.id"
                        >
                            <span
                                class="mt-0.5 size-8 shrink-0 rounded-full border"
                                :style="{ backgroundColor: template.accentColor }"
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="font-medium">{{
                                    template.name
                                }}</span>
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    {{ template.styleLabel }} ·
                                    {{ template.baseTemplateLabel }}
                                </span>
                                <span
                                    v-if="template.description"
                                    class="mt-1 block text-sm text-muted-foreground"
                                >
                                    {{ template.description }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t pt-3">
                <Button type="button" variant="ghost" @click="emit('close')">
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="generating"
                    @click="generate"
                >
                    <LoaderCircle v-if="generating" class="animate-spin" />
                    {{
                        selectedId
                            ? 'Build with selected design'
                            : 'Build with auto pick'
                    }}
                </Button>
            </div>
        </div>
    </div>
</template>
