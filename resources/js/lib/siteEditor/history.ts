import type { EditOp } from './document';

export type HistoryEntry = {
    /** Operations that redo the change. */
    forward: EditOp[];
    /** Operations that undo the change, already in undo order. */
    inverse: EditOp[];
    /** Entries with the same key recorded close together are merged into one undo step. */
    key: string | null;
    at: number;
};

const COALESCE_WINDOW_MS = 1500;
const MAX_ENTRIES = 200;

/**
 * Undo and redo stacks. Typing in the same field produces one undo step, not one per keystroke.
 */
export class EditHistory {
    protected undoStack: HistoryEntry[] = [];

    protected redoStack: HistoryEntry[] = [];

    get canUndo(): boolean {
        return this.undoStack.length > 0;
    }

    get canRedo(): boolean {
        return this.redoStack.length > 0;
    }

    record(entry: HistoryEntry): void {
        this.redoStack = [];

        const last = this.undoStack[this.undoStack.length - 1];

        if (
            last &&
            entry.key !== null &&
            last.key === entry.key &&
            entry.at - last.at < COALESCE_WINDOW_MS
        ) {
            last.forward = entry.forward;
            last.at = entry.at;

            return;
        }

        this.undoStack.push(entry);

        if (this.undoStack.length > MAX_ENTRIES) {
            this.undoStack.shift();
        }
    }

    /** Take the entry to undo and move it onto the redo stack. */
    undo(): HistoryEntry | null {
        const entry = this.undoStack.pop() ?? null;

        if (entry) {
            this.redoStack.push(entry);
        }

        return entry;
    }

    /** Take the entry to redo and move it back onto the undo stack. */
    redo(): HistoryEntry | null {
        const entry = this.redoStack.pop() ?? null;

        if (entry) {
            this.undoStack.push({ ...entry, key: null });
        }

        return entry;
    }

    clear(): void {
        this.undoStack = [];
        this.redoStack = [];
    }
}
