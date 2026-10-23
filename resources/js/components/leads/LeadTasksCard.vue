<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyTask,
    store as storeTask,
    update as updateTask,
} from '@/routes/leads/tasks';

type Task = {
    id: number;
    title: string;
    dueOn: string | null;
    completedAt: string | null;
    assignee: string | null;
};

const props = defineProps<{
    leadId: number;
    tasks: Task[];
    members: { id: number; name: string }[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const args = () => ({ current_team: teamSlug.value, lead: props.leadId });

const form = useForm({
    title: '',
    due_on: '',
    user_id: '' as number | '',
});

const submit = () =>
    form.post(storeTask.url(args()), {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => form.reset(),
    });

const toggle = (task: Task) =>
    router.patch(
        updateTask.url({ ...args(), task: task.id }),
        {},
        { preserveScroll: true, only: ['tasks'] },
    );

const remove = (task: Task) =>
    router.delete(destroyTask.url({ ...args(), task: task.id }), {
        preserveScroll: true,
        only: ['tasks'],
    });
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Tasks</h2>
        <form class="mt-4 grid gap-2" @submit.prevent="submit">
            <Label for="task-title" class="sr-only">Task</Label>
            <Input id="task-title" v-model="form.title" placeholder="Call the owner" required />
            <div class="grid grid-cols-2 gap-2">
                <Input v-model="form.due_on" type="date" />
                <select
                    v-model="form.user_id"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="">Anyone</option>
                    <option v-for="member in members" :key="member.id" :value="member.id">
                        {{ member.name }}
                    </option>
                </select>
            </div>
            <InputError :message="form.errors.title" />
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                Add task
            </Button>
        </form>
        <ul class="mt-4 grid gap-2 text-sm">
            <li
                v-for="task in tasks"
                :key="task.id"
                class="flex items-start justify-between gap-2 rounded-xl border px-3 py-2"
            >
                <label class="flex min-w-0 items-start gap-2">
                    <input
                        type="checkbox"
                        class="mt-1 size-4"
                        :checked="Boolean(task.completedAt)"
                        @change="toggle(task)"
                    />
                    <span :class="task.completedAt ? 'text-muted-foreground line-through' : ''">
                        {{ task.title }}
                        <span class="block text-xs text-muted-foreground">
                            <template v-if="task.dueOn">Due {{ task.dueOn }}</template>
                            <template v-if="task.assignee"> · {{ task.assignee }}</template>
                        </span>
                    </span>
                </label>
                <button type="button" class="text-xs text-muted-foreground" @click="remove(task)">
                    Remove
                </button>
            </li>
        </ul>
    </section>
</template>
