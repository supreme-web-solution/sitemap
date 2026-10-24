import { computed, onBeforeUnmount, ref, shallowRef } from 'vue';
import { applyOps, applyWithInverse, isStructural, isTextEdit } from '@/lib/siteEditor/document';
import type { EditOp } from '@/lib/siteEditor/document';
import { EditHistory } from '@/lib/siteEditor/history';
import { requestHeaders } from '@/lib/siteEditor/http';
import { store as storeEdits } from '@/routes/sites/edits';
import type { QualityReport, SiteDocument } from '@/types';

export type SaveStatus = 'saved' | 'dirty' | 'saving' | 'offline' | 'conflict' | 'error';

export type CommitOptions = {
    /** Merge consecutive edits with the same key into one undo step. */
    coalesceKey?: string;
    /** Do not add the change to the undo history (used by undo and redo themselves). */
    record?: boolean;
    /** Save straight away instead of waiting for a pause in typing. */
    immediate?: boolean;
    /** Where the change came from, so the preview can decide how to show it. */
    source?: 'user' | 'undo' | 'redo' | 'frame';
    /** Force the preview to re-render even for plain text. */
    reload?: boolean;
};

export type AppliedChange = {
    operations: EditOp[];
    structural: boolean;
    source: 'user' | 'undo' | 'redo' | 'frame';
};

type SaveResponse = {
    revision: number;
    document: SiteDocument;
    quality: QualityReport;
};

const SAVE_DELAY_MS = 800;
const MAX_BATCH = 60;
const RETRY_DELAYS_MS = [2000, 5000, 10000, 30000];

/**
 * The editing session for one website: the draft document, the undo history and a save queue
 * that sends small batches of operations to the server and recovers from conflicts and
 * dropped connections.
 */
export function useSiteEditor(options: {
    teamSlug: () => string;
    siteId: number;
    document: SiteDocument;
    revision: number;
    quality: QualityReport;
    onApplied: (change: AppliedChange) => void;
    onReset: () => void;
    /** Called when every queued change has been saved. */
    onSettled?: () => void;
}) {
    const draft = ref<SiteDocument>(JSON.parse(JSON.stringify(options.document)));
    const status = ref<SaveStatus>('saved');
    const message = ref<string | null>(null);
    const quality = ref<QualityReport>(options.quality);
    const revision = ref(options.revision);
    const savedAt = ref<Date | null>(null);
    const canUndo = ref(false);
    const canRedo = ref(false);

    const history = new EditHistory();
    const pending = shallowRef<EditOp[]>([]);
    let serverDocument: SiteDocument = JSON.parse(JSON.stringify(options.document));
    let saving = false;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let retryAttempt = 0;

    const syncHistoryFlags = () => {
        canUndo.value = history.canUndo;
        canRedo.value = history.canRedo;
    };

    const hasUnsaved = computed(
        () => status.value === 'dirty' || status.value === 'saving' || status.value === 'offline' || status.value === 'error',
    );

    const schedule = (immediate: boolean) => {
        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => void flush(), immediate ? 0 : SAVE_DELAY_MS);
    };

    /**
     * Apply operations to the draft, remember how to undo them, and queue them for saving.
     */
    const commit = (operations: EditOp[], commitOptions: CommitOptions = {}): void => {
        if (operations.length === 0 || status.value === 'conflict') {
            return;
        }

        const { document: next, inverse } = applyWithInverse(draft.value, operations);

        if (JSON.stringify(next) === JSON.stringify(draft.value)) {
            return;
        }

        draft.value = next;
        pending.value = [...pending.value, ...operations];
        status.value = 'dirty';
        message.value = null;

        if (commitOptions.record !== false) {
            history.record({
                forward: operations,
                inverse,
                key: commitOptions.coalesceKey ?? null,
                at: Date.now(),
            });
            syncHistoryFlags();
        }

        const structural = commitOptions.reload === true || isStructural(operations);

        options.onApplied({
            operations,
            structural,
            source: commitOptions.source ?? 'user',
        });

        schedule(commitOptions.immediate === true || (structural && !isTextEdit(operations)));
    };

    const undo = (): void => {
        const entry = history.undo();
        syncHistoryFlags();

        if (entry) {
            commit(entry.inverse, { record: false, source: 'undo', immediate: true });
        }
    };

    const redo = (): void => {
        const entry = history.redo();
        syncHistoryFlags();

        if (entry) {
            commit(entry.forward, { record: false, source: 'redo', immediate: true });
        }
    };

    const failMessage = (body: unknown, fallback: string): string => {
        const data = body as { message?: string; errors?: Record<string, string[]> } | null;
        const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;

        return first ?? data?.message ?? fallback;
    };

    async function flush(): Promise<void> {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        if (saving || pending.value.length === 0 || status.value === 'conflict') {
            return;
        }

        saving = true;
        status.value = 'saving';
        const batch = pending.value.slice(0, MAX_BATCH);

        try {
            const response = await fetch(
                storeEdits.url({ current_team: options.teamSlug(), site: options.siteId }),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: JSON.stringify(batch).length < 30000,
                    headers: {
                        ...requestHeaders(),
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ revision: revision.value, ops: batch }),
                },
            );

            const body: unknown = await response.json().catch(() => null);

            if (response.ok) {
                const saved = body as SaveResponse;
                revision.value = saved.revision;
                quality.value = saved.quality;
                serverDocument = applyOps(serverDocument, batch);
                pending.value = pending.value.slice(batch.length);
                savedAt.value = new Date();
                retryAttempt = 0;
                status.value = pending.value.length > 0 ? 'dirty' : 'saved';

                if (pending.value.length === 0) {
                    options.onSettled?.();
                }
            } else if (response.status === 409) {
                const conflictBody = body as { revision?: number } | null;

                if (typeof conflictBody?.revision === 'number') {
                    revision.value = conflictBody.revision;
                }

                status.value = 'conflict';
                message.value =
                    failMessage(body, 'This website changed somewhere else.') +
                    ' Reload to load the latest version. Your unsaved text edits stay on screen until you reload.';
            } else if (response.status === 419) {
                status.value = 'conflict';
                message.value = 'Your session expired. Reload the page to keep editing.';
            } else if (response.status === 422) {
                draft.value = JSON.parse(JSON.stringify(serverDocument));
                pending.value = [];
                history.clear();
                syncHistoryFlags();
                status.value = 'error';
                message.value = failMessage(body, 'That change was not accepted.') + ' It was undone.';
                options.onReset();
            } else if (response.status === 429 || response.status >= 500) {
                throw new Error(failMessage(body, 'The server is busy.'));
            } else {
                status.value = 'error';
                message.value = failMessage(body, 'The change could not be saved.');
            }
        } catch {
            status.value = 'offline';
            message.value = 'Offline. Your changes will be saved when the connection is back.';
            const delay = RETRY_DELAYS_MS[Math.min(retryAttempt, RETRY_DELAYS_MS.length - 1)];
            retryAttempt++;
            timer = setTimeout(() => void flush(), delay);
        } finally {
            saving = false;

            if (pending.value.length > 0 && status.value === 'dirty') {
                schedule(true);
            }
        }
    }

    /**
     * Put a freshly loaded document in place (after a restore, an AI edit or a conflict).
     */
    const reset = (document: SiteDocument, newRevision: number, newQuality?: QualityReport): void => {
        draft.value = JSON.parse(JSON.stringify(document));
        serverDocument = JSON.parse(JSON.stringify(document));
        revision.value = newRevision;
        pending.value = [];
        history.clear();
        syncHistoryFlags();
        status.value = 'saved';
        message.value = null;
        retryAttempt = 0;

        if (newQuality) {
            quality.value = newQuality;
        }
    };

    /**
     * Stop saving because the website changed somewhere else (an AI edit, a restore or a teammate).
     */
    const markConflict = (reason: string): void => {
        status.value = 'conflict';
        message.value = reason;

        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    };

    const onBeforeUnload = (event: BeforeUnloadEvent) => {
        if (hasUnsaved.value) {
            event.preventDefault();
        }
    };

    const onVisibility = () => {
        if (document.visibilityState === 'hidden' && pending.value.length > 0) {
            void flush();
        }
    };

    const onOnline = () => {
        if (status.value === 'offline' && pending.value.length > 0) {
            message.value = 'Back online. Saving your changes…';
            void flush();
        }
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('online', onOnline);

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', onBeforeUnload);
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('online', onOnline);

        if (timer) {
            clearTimeout(timer);
        }
    });

    return {
        draft,
        status,
        message,
        quality,
        revision,
        savedAt,
        canUndo,
        canRedo,
        hasUnsaved,
        commit,
        undo,
        redo,
        flush,
        reset,
        markConflict,
    };
}
