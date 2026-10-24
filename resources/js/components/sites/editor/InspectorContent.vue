<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CommitOptions } from '@/composables/useSiteEditor';
import type { EditOp } from '@/lib/siteEditor/document';
import { slugify } from '@/lib/format';
import type { SiteCopyField, SiteDocument } from '@/types';

const props = defineProps<{
    draft: SiteDocument;
    copyFields: SiteCopyField[];
}>();

const emit = defineEmits<{
    commit: [operations: EditOp[], options?: CommitOptions];
    locate: [path: string];
}>();

const MAX_SERVICES = 8;
const MAX_FAQS = 10;

const text = (path: string, value: string | number) =>
    emit('commit', [{ op: 'set', path, value: String(value) }], {
        coalesceKey: `text:${path}`,
    });

const copyValue = (key: string) => props.draft.copy?.[key] ?? '';

const addService = () =>
    emit(
        'commit',
        [
            {
                op: 'insert',
                path: 'services',
                index: props.draft.services.length,
                value: { title: 'New service', description: '', slug: '', body: '' },
            },
        ],
        { immediate: true },
    );

const removeService = (index: number) =>
    emit('commit', [{ op: 'remove', path: 'services', index }], { immediate: true });

const moveService = (from: number, to: number) =>
    emit('commit', [{ op: 'move', path: 'services', from, to }], { immediate: true });

const fillSlug = (index: number) => {
    const service = props.draft.services[index];

    if (service && !service.slug) {
        emit('commit', [{ op: 'set', path: `services.${index}.slug`, value: slugify(service.title) }]);
    }
};

const addFaq = () =>
    emit(
        'commit',
        [
            {
                op: 'insert',
                path: 'faqs',
                index: props.draft.faqs.length,
                value: { question: 'New question', answer: 'Answer' },
            },
        ],
        { immediate: true },
    );

const removeFaq = (index: number) =>
    emit('commit', [{ op: 'remove', path: 'faqs', index }], { immediate: true });

const moveFaq = (from: number, to: number) =>
    emit('commit', [{ op: 'move', path: 'faqs', from, to }], { immediate: true });

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <div class="grid gap-5" data-test="inspector-content">
        <div class="grid gap-2">
            <Label for="headline">Headline</Label>
            <Input
                id="headline"
                :model-value="draft.headline"
                maxlength="70"
                @focus="$emit('locate', 'headline')"
                @update:model-value="text('headline', $event)"
            />
        </div>

        <div class="grid gap-2">
            <Label for="tagline">Tagline</Label>
            <textarea
                id="tagline"
                :value="draft.tagline"
                rows="2"
                maxlength="200"
                :class="textareaClass"
                @focus="$emit('locate', 'tagline')"
                @input="text('tagline', ($event.target as HTMLTextAreaElement).value)"
            />
        </div>

        <div class="grid gap-2">
            <Label for="cta_label">Button text</Label>
            <Input
                id="cta_label"
                :model-value="draft.cta_label"
                maxlength="40"
                @focus="$emit('locate', 'cta_label')"
                @update:model-value="text('cta_label', $event)"
            />
        </div>

        <div class="grid gap-2">
            <Label for="about">About</Label>
            <textarea
                id="about"
                :value="draft.about"
                rows="6"
                maxlength="2000"
                :class="textareaClass"
                @input="text('about', ($event.target as HTMLTextAreaElement).value)"
            />
        </div>

        <div class="grid gap-3">
            <div class="flex items-center justify-between">
                <Label>Services</Label>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    :disabled="draft.services.length >= MAX_SERVICES"
                    @click="addService"
                >
                    <Plus /> Add
                </Button>
            </div>
            <div
                v-for="(service, index) in draft.services"
                :key="index"
                class="grid gap-2 rounded-lg border p-3"
                :data-test="`service-${index}`"
            >
                <div class="flex gap-1">
                    <Input
                        :model-value="service.title"
                        placeholder="Service name"
                        maxlength="60"
                        :aria-label="`Service ${index + 1} name`"
                        @focus="$emit('locate', `services.${index}.title`)"
                        @update:model-value="text(`services.${index}.title`, $event)"
                        @blur="fillSlug(index)"
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Move service up"
                        :disabled="index === 0"
                        @click="moveService(index, index - 1)"
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Move service down"
                        :disabled="index === draft.services.length - 1"
                        @click="moveService(index, index + 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Remove service"
                        :disabled="draft.services.length <= 1"
                        @click="removeService(index)"
                    >
                        <Trash2 />
                    </Button>
                </div>
                <textarea
                    :value="service.description"
                    rows="2"
                    maxlength="250"
                    placeholder="Short description"
                    :class="textareaClass"
                    @focus="$emit('locate', `services.${index}.description`)"
                    @input="text(`services.${index}.description`, ($event.target as HTMLTextAreaElement).value)"
                />
                <div class="flex items-center gap-1 text-xs">
                    <span class="shrink-0 text-muted-foreground">/services/</span>
                    <Input
                        :model-value="service.slug ?? ''"
                        class="h-8 text-xs"
                        placeholder="page-address"
                        :aria-label="`Page address for ${service.title}`"
                        @update:model-value="text(`services.${index}.slug`, $event)"
                    />
                </div>
                <textarea
                    :value="service.body ?? ''"
                    rows="4"
                    maxlength="5000"
                    placeholder="Details for this service's own page (what is included, who it is for, how it works). Unique text helps it rank on Google."
                    :class="textareaClass"
                    @input="text(`services.${index}.body`, ($event.target as HTMLTextAreaElement).value)"
                />
            </div>
        </div>

        <div class="grid gap-3">
            <div class="flex items-center justify-between">
                <Label>FAQs</Label>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    :disabled="draft.faqs.length >= MAX_FAQS"
                    @click="addFaq"
                >
                    <Plus /> Add
                </Button>
            </div>
            <div
                v-for="(faq, index) in draft.faqs"
                :key="index"
                class="grid gap-2 rounded-lg border p-3"
            >
                <div class="flex gap-1">
                    <Input
                        :model-value="faq.question"
                        placeholder="Question"
                        :aria-label="`FAQ ${index + 1} question`"
                        @focus="$emit('locate', `faqs.${index}.question`)"
                        @update:model-value="text(`faqs.${index}.question`, $event)"
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Move FAQ up"
                        :disabled="index === 0"
                        @click="moveFaq(index, index - 1)"
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Move FAQ down"
                        :disabled="index === draft.faqs.length - 1"
                        @click="moveFaq(index, index + 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Remove FAQ"
                        @click="removeFaq(index)"
                    >
                        <Trash2 />
                    </Button>
                </div>
                <textarea
                    :value="faq.answer"
                    rows="2"
                    placeholder="Answer"
                    :class="textareaClass"
                    @focus="$emit('locate', `faqs.${index}.answer`)"
                    @input="text(`faqs.${index}.answer`, ($event.target as HTMLTextAreaElement).value)"
                />
            </div>
        </div>

        <Collapsible class="grid gap-2">
            <CollapsibleTrigger as-child>
                <Button type="button" variant="outline" size="sm" class="w-fit">
                    Headings and labels
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent class="grid gap-3 pt-2">
                <p class="text-xs text-muted-foreground">
                    Reword the small titles on the page. Leave a field empty to
                    use the template's own wording.
                </p>
                <div v-for="field in copyFields" :key="field.key" class="grid gap-1.5">
                    <Label :for="`copy-${field.key}`" class="text-xs">{{ field.label }}</Label>
                    <textarea
                        v-if="field.multiline"
                        :id="`copy-${field.key}`"
                        :value="copyValue(field.key)"
                        rows="2"
                        :maxlength="field.max"
                        placeholder="Template default"
                        :class="textareaClass"
                        @input="
                            emit('commit', [{ op: 'set', path: `copy.${field.key}`, value: ($event.target as HTMLTextAreaElement).value }], { coalesceKey: `text:copy.${field.key}`, reload: true })
                        "
                    />
                    <Input
                        v-else
                        :id="`copy-${field.key}`"
                        :model-value="copyValue(field.key)"
                        :maxlength="field.max"
                        placeholder="Template default"
                        @update:model-value="
                            emit('commit', [{ op: 'set', path: `copy.${field.key}`, value: String($event) }], { coalesceKey: `text:copy.${field.key}`, reload: true })
                        "
                    />
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
