import type { SiteDocument } from '@/types';

/**
 * A single named change to the website document. These are exactly the operations the
 * server accepts (see SiteEditApplier), so what the editor does locally is what gets saved.
 */
export type EditOp =
    | { op: 'set'; path: string; value: unknown }
    | { op: 'insert'; path: string; index: number; value: unknown }
    | { op: 'remove'; path: string; index: number }
    | { op: 'move'; path: string; from: number; to: number };

/** Paths where an empty value means "back to the template default". */
const RESETTABLE = /^(copy|design|section_styles)\./;

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

const segments = (path: string): string[] => path.split('.');

type Container = Record<string, unknown> | unknown[];

const isContainer = (value: unknown): value is Container =>
    typeof value === 'object' && value !== null;

export const getPath = (document: unknown, path: string): unknown => {
    let current: unknown = document;

    for (const key of segments(path)) {
        if (!isContainer(current)) {
            return undefined;
        }

        current = (current as Record<string, unknown>)[key];
    }

    return current;
};

const setPath = (document: Record<string, unknown>, path: string, value: unknown): void => {
    const keys = segments(path);
    let current: Container = document;

    keys.slice(0, -1).forEach((key, position) => {
        const container = current as Record<string, unknown>;
        const next = container[key];

        if (!isContainer(next)) {
            container[key] = /^\d+$/.test(keys[position + 1]) ? [] : {};
        }

        current = container[key] as Container;
    });

    (current as Record<string, unknown>)[keys[keys.length - 1]] = value;
};

const forgetPath = (document: Record<string, unknown>, path: string): void => {
    const keys = segments(path);
    const parent = getPath(document, keys.slice(0, -1).join('.'));

    if (isContainer(parent)) {
        delete (parent as Record<string, unknown>)[keys[keys.length - 1]];
    }
};

const listAt = (document: unknown, path: string): unknown[] => {
    const value = getPath(document, path);

    return Array.isArray(value) ? value : [];
};

/**
 * Apply one operation and return a new document. The input is never mutated.
 */
export const applyOp = (document: SiteDocument, operation: EditOp): SiteDocument => {
    const next = clone(document) as unknown as Record<string, unknown>;

    switch (operation.op) {
        case 'set':
            if (
                RESETTABLE.test(operation.path) &&
                (operation.value === null || operation.value === '')
            ) {
                forgetPath(next, operation.path);
            } else {
                setPath(next, operation.path, clone(operation.value));
            }

            break;
        case 'insert': {
            const items = [...listAt(next, operation.path)];
            items.splice(operation.index, 0, clone(operation.value));
            setPath(next, operation.path, items);
            break;
        }
        case 'remove': {
            const items = [...listAt(next, operation.path)];
            items.splice(operation.index, 1);
            setPath(next, operation.path, items);
            break;
        }
        case 'move': {
            const items = [...listAt(next, operation.path)];
            const [item] = items.splice(operation.from, 1);
            items.splice(operation.to, 0, item);
            setPath(next, operation.path, items);
            break;
        }
    }

    return next as unknown as SiteDocument;
};

export const applyOps = (document: SiteDocument, operations: EditOp[]): SiteDocument =>
    operations.reduce(applyOp, document);

/**
 * Work out the operation that undoes `operation`, given the document it is about to be applied to.
 */
export const inverseOf = (document: SiteDocument, operation: EditOp): EditOp => {
    switch (operation.op) {
        case 'set': {
            const previous = getPath(document, operation.path);

            return {
                op: 'set',
                path: operation.path,
                value: previous === undefined ? null : clone(previous),
            };
        }
        case 'insert':
            return { op: 'remove', path: operation.path, index: operation.index };
        case 'remove':
            return {
                op: 'insert',
                path: operation.path,
                index: operation.index,
                value: clone(listAt(document, operation.path)[operation.index]),
            };
        case 'move':
            return { op: 'move', path: operation.path, from: operation.to, to: operation.from };
    }
};

/**
 * Apply operations in order and collect the operations that undo them (in undo order).
 */
export const applyWithInverse = (
    document: SiteDocument,
    operations: EditOp[],
): { document: SiteDocument; inverse: EditOp[] } => {
    const inverse: EditOp[] = [];
    let current = document;

    for (const operation of operations) {
        inverse.unshift(inverseOf(current, operation));
        current = applyOp(current, operation);
    }

    return { document: current, inverse };
};

/**
 * Whether the operations change the page structure (so the preview must be re-rendered)
 * rather than just a piece of text the preview already shows.
 */
export const isStructural = (operations: EditOp[]): boolean =>
    operations.some((operation) => {
        if (operation.op !== 'set') {
            return true;
        }

        return !/^(headline|tagline|cta_label|services\.\d+\.(title|description)|faqs\.\d+\.(question|answer)|copy\.[a-z_]+)$/.test(
            operation.path,
        );
    });

/**
 * Whether the operations only type into text fields, so saving can wait for a pause in typing.
 */
export const isTextEdit = (operations: EditOp[]): boolean =>
    operations.every(
        (operation) =>
            operation.op === 'set' &&
            typeof operation.value === 'string' &&
            /^(headline|tagline|cta_label|about|services\.\d+\.(title|description|body|slug)|faqs\.\d+\.(question|answer)|copy\.[a-z_]+)$/.test(
                operation.path,
            ),
    );