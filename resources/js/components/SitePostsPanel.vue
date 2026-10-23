<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRelativeTime, slugify } from '@/lib/format';
import { destroy, store, update } from '@/routes/sites/posts';
import type { SitePost } from '@/types';

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    posts: SitePost[];
}>();

const emit = defineEmits<{ saved: [] }>();

const editing = ref<SitePost | null>(null);

const form = useForm({
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    is_published: true,
});

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const routeArgs = () => ({ current_team: props.teamSlug, site: props.siteId });

const reset = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
};

const edit = (post: SitePost) => {
    editing.value = post;
    form.defaults({
        title: post.title,
        slug: post.slug,
        excerpt: post.excerpt ?? '',
        body: post.body,
        is_published: post.isPublished,
    });
    form.reset();
    form.clearErrors();
};

const fillSlug = () => {
    if (!editing.value && !form.slug) {
        form.slug = slugify(form.title);
    }
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults({
                title: '',
                slug: '',
                excerpt: '',
                body: '',
                is_published: true,
            });
            reset();
            emit('saved');
        },
    };

    if (editing.value) {
        form.put(
            update.url({ ...routeArgs(), post: editing.value.id }),
            options,
        );

        return;
    }

    form.post(store.url(routeArgs()), options);
};

const remove = (post: SitePost) => {
    if (!confirm(`Delete "${post.title}"?`)) {
        return;
    }

    router.delete(destroy.url({ ...routeArgs(), post: post.id }), {
        preserveScroll: true,
        onSuccess: () => {
            if (editing.value?.id === post.id) {
                reset();
            }

            emit('saved');
        },
    });
};
</script>

<template>
    <section class="panel p-5" data-test="posts-panel">
        <h2 class="text-base font-semibold">Blog</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Publish helpful articles to give the site fresh pages for Google.
            The Blog page appears in the menu once a post is published.
        </p>

        <ul v-if="posts.length" class="mt-4 grid gap-2">
            <li
                v-for="post in posts"
                :key="post.id"
                class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ post.title }}</p>
                    <p class="text-xs text-muted-foreground">
                        /blog/{{ post.slug }} ·
                        {{
                            post.isPublished && post.publishedAt
                                ? `Published ${formatRelativeTime(post.publishedAt)}`
                                : 'Draft'
                        }}
                    </p>
                </div>
                <Button
                    type="button"
                    size="icon-sm"
                    variant="ghost"
                    aria-label="Edit post"
                    @click="edit(post)"
                >
                    <Pencil />
                </Button>
                <Button
                    type="button"
                    size="icon-sm"
                    variant="ghost"
                    aria-label="Delete post"
                    @click="remove(post)"
                >
                    <Trash2 />
                </Button>
            </li>
        </ul>

        <form class="mt-4 grid gap-3" @submit.prevent="submit">
            <h3 class="text-sm font-medium">
                {{ editing ? 'Edit post' : 'New post' }}
            </h3>
            <div class="grid gap-2">
                <Label for="post-title">Title</Label>
                <Input
                    id="post-title"
                    v-model="form.title"
                    placeholder="e.g. How to stop pipes freezing this winter"
                    @blur="fillSlug"
                />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="post-slug">Page address</Label>
                <Input
                    id="post-slug"
                    v-model="form.slug"
                    placeholder="how-to-stop-pipes-freezing"
                />
                <InputError :message="form.errors.slug" />
            </div>
            <div class="grid gap-2">
                <Label for="post-excerpt">Summary (shown in Google)</Label>
                <textarea
                    id="post-excerpt"
                    v-model="form.excerpt"
                    rows="2"
                    maxlength="300"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.excerpt" />
            </div>
            <div class="grid gap-2">
                <Label for="post-body">Article</Label>
                <textarea
                    id="post-body"
                    v-model="form.body"
                    rows="8"
                    :class="textareaClass"
                />
                <p class="text-xs text-muted-foreground">
                    Leave a blank line between paragraphs.
                </p>
                <InputError :message="form.errors.body" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    :model-value="form.is_published"
                    @update:model-value="form.is_published = $event === true"
                />
                Publish on the website
            </label>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Plus v-else-if="!editing" />
                    {{ editing ? 'Save post' : 'Add post' }}
                </Button>
                <Button
                    v-if="editing"
                    type="button"
                    variant="ghost"
                    @click="reset"
                >
                    Cancel
                </Button>
            </div>
        </form>
    </section>
</template>
