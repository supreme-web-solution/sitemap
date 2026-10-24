const STROKE_SELECTOR =
    'path, line, polyline, circle, ellipse, rect';

function strokeElements(root: HTMLElement): SVGGeometryElement[] {
    return Array.from(
        root.querySelectorAll<SVGGeometryElement>(STROKE_SELECTOR),
    );
}

export function initSidebarIconStrokes(root: HTMLElement): void {
    strokeElements(root).forEach((element) => {
        if (typeof element.getTotalLength !== 'function') {
            return;
        }

        const length = element.getTotalLength();

        if (!Number.isFinite(length) || length <= 0) {
            return;
        }

        element.dataset.sidebarIconLength = String(length);
        element.style.strokeDasharray = `${length}`;
        element.style.strokeDashoffset = '0';
    });
}

export function playSidebarIconDraw(root: HTMLElement): void {
    strokeElements(root).forEach((element) => {
        const stored = element.dataset.sidebarIconLength;
        const length =
            stored !== undefined
                ? Number(stored)
                : typeof element.getTotalLength === 'function'
                  ? element.getTotalLength()
                  : 0;

        if (!Number.isFinite(length) || length <= 0) {
            return;
        }

        element.style.transition = 'none';
        element.style.strokeDasharray = `${length}`;
        element.style.strokeDashoffset = `${length}`;
        void element.getBoundingClientRect();
        element.style.transition = 'stroke-dashoffset 0.55s ease';
        element.style.strokeDashoffset = '0';
    });
}

export function playSidebarIconDrawWithin(container: HTMLElement): void {
    container
        .querySelectorAll<HTMLElement>('[data-sidebar-nav-icon]')
        .forEach((iconRoot) => {
            playSidebarIconDraw(iconRoot);
        });
}
