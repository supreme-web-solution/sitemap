import {
    destroy as destroyMedia,
    store as storeMedia,
    undo as undoMedia,
} from '@/routes/sites/media';
import type { SiteMedia } from '@/types';

export const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

export const requestHeaders = (): Record<string, string> => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
});

export type MediaSlot = { slot: 'logo' | 'photo'; index: number | null };

type MediaResponse = Pick<SiteMedia, 'logo' | 'photos'>;

const failure = async (response: Response): Promise<Error> => {
    const body = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: Record<string, string[]>;
    } | null;
    const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;

    return new Error(first ?? body?.message ?? 'The image could not be saved.');
};

export const uploadSiteImage = async (
    teamSlug: string,
    siteId: number,
    target: MediaSlot,
    file: File,
): Promise<MediaResponse> => {
    const data = new FormData();
    data.append('slot', target.slot);
    data.append('image', file);

    if (target.index !== null) {
        data.append('index', String(target.index));
    }

    const response = await fetch(storeMedia.url({ current_team: teamSlug, site: siteId }), {
        method: 'POST',
        credentials: 'same-origin',
        headers: requestHeaders(),
        body: data,
    });

    if (!response.ok) {
        throw await failure(response);
    }

    return (await response.json()) as MediaResponse;
};

export const generateSiteImage = async (
    teamSlug: string,
    siteId: number,
    target: MediaSlot,
    prompt: string,
): Promise<MediaResponse> => {
    const data = new FormData();
    data.append('generate', '1');
    data.append('slot', target.slot);
    data.append('prompt', prompt);

    if (target.index !== null) {
        data.append('index', String(target.index));
    }

    const response = await fetch(storeMedia.url({ current_team: teamSlug, site: siteId }), {
        method: 'POST',
        credentials: 'same-origin',
        headers: requestHeaders(),
        body: data,
    });

    if (!response.ok) {
        throw await failure(response);
    }

    return (await response.json()) as MediaResponse;
};

export const undoSiteImage = async (
    teamSlug: string,
    siteId: number,
): Promise<MediaResponse> => {
    const response = await fetch(undoMedia.url({ current_team: teamSlug, site: siteId }), {
        method: 'POST',
        credentials: 'same-origin',
        headers: requestHeaders(),
    });

    if (!response.ok) {
        throw await failure(response);
    }

    return (await response.json()) as MediaResponse;
};

export const removeSiteImage = async (
    teamSlug: string,
    siteId: number,
    target: MediaSlot,
): Promise<MediaResponse> => {
    const response = await fetch(destroyMedia.url({ current_team: teamSlug, site: siteId }), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { ...requestHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ slot: target.slot, index: target.index }),
    });

    if (!response.ok) {
        throw await failure(response);
    }

    return (await response.json()) as MediaResponse;
};
