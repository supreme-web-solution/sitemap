/**
 * Messages between the editor page (host) and the website preview inside the iframe.
 * Both sides only accept messages from the app's own origin.
 */
export const FRAME_SOURCE = 'sitemap-editor';
export const HOST_SOURCE = 'sitemap-editor-host';

export type TextSelection = {
    kind: 'text';
    path: string;
    label: string;
    max: number;
    multiline: boolean;
    reload: boolean;
    value: string;
};

export type ImageSelection = {
    kind: 'image';
    slot: 'logo' | 'photo' | 'service' | 'home_hero' | 'about_hero' | 'about_portrait' | 'services_hero' | 'service_hero' | 'gallery_hero' | 'contact_hero' | 'blog_hero' | 'post_hero' | 'landing_hero' | 'location_hero' | 'cta_band';
    index: number | null;
    label: string;
};

export type SectionSelection = {
    kind: 'section';
    key: string;
    label: string;
};

/** Click-to-edit target for AI scoped CSS (Krikkit-style preview_edits). */
export type DesignTargetSelection = {
    kind: 'design';
    region: string;
    label: string;
    selector: string;
    tag: string;
    text: string;
    href?: string;
};

export type PreviewEdit = {
    region: string;
    selector: string;
    tag: string;
    text?: string;
    href?: string;
};

export type EditorSelection =
    | TextSelection
    | ImageSelection
    | SectionSelection
    | DesignTargetSelection
    | { kind: 'none' };

export type FrameSection = { key: string; label: string };

export type FrameMessage =
    | { type: 'ready'; path: string; title: string; sections: FrameSection[] }
    | ({ type: 'select' } & EditorSelection)
    | {
          type: 'edit';
          path: string;
          label: string;
          max: number;
          multiline: boolean;
          reload: boolean;
          value: string;
          final: boolean;
      }
    | { type: 'image'; slot: ImageSelection['slot']; index: number | null; label: string }
    | { type: 'section'; action: 'up' | 'down' | 'hide' | 'improve'; key: string }
    | { type: 'inview'; key: string; label: string }
    | { type: 'shortcut'; name: 'undo' | 'redo' | 'save' }
    | { type: 'panel'; name: 'posts' };

export type HostMessage =
    | { type: 'mode'; mode: 'edit' | 'preview' }
    | { type: 'apply'; path: string; value: string }
    | { type: 'select'; path: string }
    | { type: 'focus'; path: string }
    | { type: 'section'; key: string }
    | { type: 'reload' }
    | { type: 'deselect' };

export const isFrameMessage = (data: unknown): data is FrameMessage =>
    typeof data === 'object' &&
    data !== null &&
    (data as { source?: unknown }).source === FRAME_SOURCE &&
    typeof (data as { type?: unknown }).type === 'string';
