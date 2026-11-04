import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    document.addEventListener('inertia:flash', (event) => {
        const flash = (event as CustomEvent<{ flash?: Record<string, unknown> }>)
            .detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });
}
