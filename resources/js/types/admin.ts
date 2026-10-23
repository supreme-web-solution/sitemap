export type PlanOption = {
    id: number;
    name: string;
};

export type Plan = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    is_default: boolean;
    is_active: boolean;
    limits: Record<string, number | null>;
    features: string[];
    teams_count?: number;
};

export type PlanMetric = {
    key: string;
    label: string;
    period: string;
};

export type PlanFeatureOption = {
    key: string;
    label: string;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    suspended_at: string | null;
    created_at: string;
    is_admin: boolean;
    teams_count?: number;
};

export type AdminTeam = {
    id: number;
    name: string;
    slug: string;
    is_personal: boolean;
    plan_id: number | null;
    owner: string | null;
    members_count: number;
    leads_count: number;
    sites_count: number;
};

export type UsageRow = {
    key: string;
    label: string;
    period: 'month' | 'total';
    used: number;
    limit: number | null;
};