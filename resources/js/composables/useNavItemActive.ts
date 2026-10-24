import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import type { NavItem } from '@/types';
import { toUrl } from '@/lib/utils';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

export function useNavItemActive() {
    const page = usePage();
    const { isCurrentUrl } = useCurrentUrl();

    function isNavItemActive(item: NavItem): boolean {
        const href = toUrl(item.href);

        if (item.dot) {
            const origin =
                typeof window !== 'undefined'
                    ? window.location.origin
                    : 'http://localhost';
            const target = new URL(href, origin);
            const current = new URL(page.url, origin);

            return (
                target.pathname === current.pathname &&
                target.search === current.search
            );
        }

        return isCurrentUrl(
            href as NonNullable<InertiaLinkProps['href']>,
            undefined,
            !item.exact,
        );
    }

    function isAnyNavItemActive(items: NavItem[]): boolean {
        return items.some((item) => isNavItemActive(item));
    }

    return { isNavItemActive, isAnyNavItemActive };
}
