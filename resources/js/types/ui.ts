export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type BrandPullResult = {
    accent: string | null;
    logoUrl: string | null;
    logoSaved: boolean;
    siteAccentUpdated: boolean;
};
