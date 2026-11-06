<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { destroy as destroyLead } from '@/routes/leads';

const props = withDefaults(
    defineProps<{
        leadId: number;
        leadName: string;
        teamSlug: string;
        appearance?: 'icon' | 'button';
    }>(),
    {
        appearance: 'icon',
    },
);

const open = ref(false);
const processing = ref(false);

const confirmDelete = () => {
    processing.value = true;

    router.delete(
        destroyLead.url({
            current_team: props.teamSlug,
            lead: props.leadId,
        }),
        {
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <Tooltip v-if="appearance === 'icon'">
            <TooltipTrigger as-child>
                <DialogTrigger as-child>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 text-muted-foreground hover:text-destructive"
                        data-test="delete-lead-button"
                        @click.stop
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Delete lead</span>
                    </Button>
                </DialogTrigger>
            </TooltipTrigger>
            <TooltipContent>Delete lead</TooltipContent>
        </Tooltip>
        <DialogTrigger v-else as-child>
            <Button
                variant="outline"
                size="sm"
                class="w-full text-destructive hover:text-destructive sm:w-auto"
                data-test="delete-lead-button"
            >
                <Trash2 /> Delete lead
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Delete {{ leadName }}?</DialogTitle>
                <DialogDescription>
                    This permanently removes the lead and everything saved for
                    it: the website, photos, proposals, invoices, messages, and
                    follow-ups. This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="processing"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    data-test="confirm-delete-lead"
                    :disabled="processing"
                    @click="confirmDelete"
                >
                    Delete everything
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
