<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SELECT_ONE_TIME } from '@/lib/selectSentinels';

const props = defineProps<{
    modelValue: string;
    intervals: { value: string; label: string }[];
    disabled?: boolean;
    placeholder?: string;
    triggerClass?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [string] }>();

const selected = computed({
    get: () => (props.modelValue === '' ? SELECT_ONE_TIME : props.modelValue),
    set: (value: string) => {
        emit('update:modelValue', value === SELECT_ONE_TIME ? '' : value);
    },
});
</script>

<template>
    <Select v-model="selected" :disabled="disabled">
        <SelectTrigger :class="triggerClass ?? 'w-full'" aria-label="Billing">
            <SelectValue :placeholder="placeholder ?? 'One time'" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem :value="SELECT_ONE_TIME">One time</SelectItem>
            <SelectItem
                v-for="interval in intervals"
                :key="interval.value"
                :value="interval.value"
            >
                {{ interval.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
