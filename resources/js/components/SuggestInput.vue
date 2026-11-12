<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: string[];
        id?: string;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
        /** When true, picking an option adds it to a comma-separated list. */
        append?: boolean;
    }>(),
    {
        options: () => [],
        placeholder: '',
        required: false,
        disabled: false,
        append: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const root = ref<HTMLElement | null>(null);
const open = ref(false);
const activeIndex = ref(-1);

const query = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});

const filterNeedle = computed(() => {
    if (!props.append) {
        return query.value.trim().toLowerCase();
    }

    const parts = query.value.split(',');
    return (parts[parts.length - 1] ?? '').trim().toLowerCase();
});

const filtered = computed(() => {
    const needle = filterNeedle.value;
    const selected = props.append
        ? query.value
              .split(',')
              .map((part) => part.trim())
              .filter(Boolean)
        : [];

    const available = props.options.filter(
        (option) => !selected.includes(option),
    );

    if (needle === '') {
        return available.slice(0, 12);
    }

    return available
        .filter((option) => option.toLowerCase().includes(needle))
        .slice(0, 12);
});

const showList = computed(() => open.value && filtered.value.length > 0);

watch(filtered, () => {
    activeIndex.value = -1;
});

onClickOutside(root, () => {
    open.value = false;
});

const openList = () => {
    if (!props.disabled) {
        open.value = true;
    }
};

const selectOption = (option: string) => {
    if (props.append) {
        const parts = query.value
            .split(',')
            .map((part) => part.trim())
            .filter(Boolean);

        if (filterNeedle.value !== '' && parts.length > 0) {
            parts[parts.length - 1] = option;
        } else if (!parts.includes(option)) {
            parts.push(option);
        }

        emit(
            'update:modelValue',
            parts.filter((part, index, all) => all.indexOf(part) === index).join(', '),
        );
    } else {
        emit('update:modelValue', option);
    }

    open.value = false;
    activeIndex.value = -1;
};

const onKeydown = (event: KeyboardEvent) => {
    if (!showList.value) {
        if (event.key === 'ArrowDown' && filtered.value.length > 0) {
            open.value = true;
            activeIndex.value = 0;
            event.preventDefault();
        }

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value =
            activeIndex.value < filtered.value.length - 1
                ? activeIndex.value + 1
                : 0;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            activeIndex.value > 0
                ? activeIndex.value - 1
                : filtered.value.length - 1;
        return;
    }

    if (event.key === 'Enter' && activeIndex.value >= 0) {
        event.preventDefault();
        selectOption(filtered.value[activeIndex.value]!);
        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
        activeIndex.value = -1;
    }
};
</script>

<template>
    <div ref="root" class="relative">
        <div class="relative">
            <Input
                :id="id"
                v-model="query"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                role="combobox"
                :aria-expanded="showList"
                :aria-controls="id ? `${id}-listbox` : undefined"
                autocomplete="off"
                class="pr-9"
                @focus="openList"
                @input="openList"
                @keydown="onKeydown"
            />
            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground opacity-50"
                aria-hidden="true"
            />
        </div>

        <div
            v-if="showList"
            :id="id ? `${id}-listbox` : undefined"
            role="listbox"
            class="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border p-1 shadow-md"
        >
            <button
                v-for="(option, index) in filtered"
                :key="option"
                type="button"
                role="option"
                :aria-selected="!append && option === modelValue"
                :class="
                    cn(
                        'relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-left text-sm outline-hidden select-none',
                        index === activeIndex ||
                            (!append && option === modelValue)
                            ? 'bg-accent text-accent-foreground'
                            : 'hover:bg-accent hover:text-accent-foreground',
                    )
                "
                @mousedown.prevent="selectOption(option)"
                @mouseenter="activeIndex = index"
            >
                {{ option }}
                <span
                    v-if="!append && option === modelValue"
                    class="absolute right-2 flex size-3.5 items-center justify-center"
                >
                    <Check class="size-4" />
                </span>
            </button>
        </div>
    </div>
</template>
