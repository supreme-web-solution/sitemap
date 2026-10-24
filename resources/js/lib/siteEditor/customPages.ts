import type { SiteCustomPage, SiteDocument } from '@/types';

export const MAX_CUSTOM_PAGES = 12;

export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export function customPages(document: SiteDocument): SiteCustomPage[] {
    return document.custom_pages ?? [];
}

export function customPagePath(
    index: number,
    field: keyof SiteCustomPage,
): string {
    return `custom_pages.${index}.${field}`;
}

export function pagePublicPath(page: SiteCustomPage): string {
    return page.kind === 'landing'
        ? `/landing/${page.slug}`
        : `/locations/${page.slug}`;
}

export function slugify(text: string): string {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);
}

export function uniqueCustomSlug(
    kind: SiteCustomPage['kind'],
    base: string,
    pages: SiteCustomPage[],
): string {
    const taken = new Set(
        pages.filter((page) => page.kind === kind).map((page) => page.slug),
    );
    const slug = slugify(base) || 'page';
    let attempt = slug;
    let counter = 2;

    while (taken.has(attempt)) {
        attempt = `${slug}-${counter}`;
        counter++;
    }

    return attempt;
}

export function newLandingPage(
    businessName: string,
    city: string | null,
    category: string | null,
    existing: SiteCustomPage[],
): SiteCustomPage {
    const categoryLabel = category || 'local services';

    return {
        kind: 'landing',
        slug: uniqueCustomSlug(
            'landing',
            `campaign-${existing.length + 1}`,
            existing,
        ),
        title: 'New landing page',
        headline: `Work with ${businessName}`,
        intro: city
            ? `Local ${categoryLabel} for ${city}.`
            : `Local ${categoryLabel} you can trust.`,
        body: `Tell us what you need and we will follow up with clear next steps. ${businessName} keeps things straightforward from the first conversation.`,
        cta_label: 'Contact us',
    };
}

export function newLocationPage(
    businessName: string,
    city: string | null,
    category: string | null,
    existing: SiteCustomPage[],
): SiteCustomPage {
    const categoryLabel = category || 'local services';
    const place = city || 'your area';

    return {
        kind: 'location',
        slug: uniqueCustomSlug('location', place, existing),
        title: `${businessName} in ${place}`,
        headline: `${categoryLabel} in ${place}`,
        intro: `Serving ${place} and nearby neighbourhoods.`,
        body: `${businessName} helps customers in ${place} with dependable ${categoryLabel}. Call or message us to talk through what you need.`,
        city: city ?? '',
        cta_label: 'Call now',
    };
}
