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
    slot: 'logo' | 'photo';
    index: number | null;
    label: string;
};

export type SectionSelection = {
    kind: 'section';
    key: string;
    label: string;
};

export type EditorSelection =
    | TextSelection
    | ImageSelection
    | SectionSelection
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
    | { type: 'image'; slot: 'logo' | 'photo'; index: number | null; label: string }
    | { type: 'section'; action: 'up' | 'down' | 'hide' | 'improve'; key: string }
    | { type: 'shortcut'; name: 'undo' | 'redo' | 'save' };

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
