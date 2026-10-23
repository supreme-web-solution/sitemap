export type Option = {
    value: string;
    label: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type LeadStatus =
    | 'new'
    | 'site_generated'
    | 'contacted'
    | 'interested'
    | 'won'
    | 'lost';

export type Lead = {
    id: number;
    name: string;
    category: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    address: string | null;
    city: string | null;
    rating: number | null;
    reviewsCount: number;
    mapsUrl: string | null;
    status: LeadStatus;
    dealValue: number | null;
    opportunityScore: number | null;
    opportunityTier: OpportunityTier | null;
    websiteAuditScore: number | null;
    nextFollowUpAt: string | null;
    lastContactedAt: string | null;
    detailsConfirmed: boolean;
    siteGenerationStatus: SiteGenerationStatus;
    siteGenerationError: string | null;
    site: { id: number; previewUrl: string } | null;
};

export type OpportunityTier = 'hot' | 'warm' | 'cold';

export type SiteGenerationStatus = 'idle' | 'queued' | 'failed';

export type CheckStatus = 'pass' | 'warn' | 'fail';

export type CheckItem = {
    key: string;
    label: string;
    status: CheckStatus;
    detail: string;
};

export type ScoreComponent = {
    key: string;
    label: string;
    points: number;
    max: number;
    reason: string;
};

export type WebsiteAudit = {
    url: string;
    final_url: string | null;
    fetched_at: string;
    status_code: number | null;
    score: number;
    error: string | null;
    checks: CheckItem[];
};

export type VerificationItem = {
    key: string;
    label: string;
    status: 'verified' | 'inferred' | 'missing';
    detail: string;
};

export type VerificationReport = {
    items: VerificationItem[];
    readiness: number;
};

export type LeadActivityType =
    | 'created'
    | 'status_changed'
    | 'note'
    | 'site_generated'
    | 'website_audited'
    | 'email_sent'
    | 'marked_contacted'
    | 'follow_up_scheduled'
    | 'preview_interest'
    | 'invoice_sent'
    | 'invoice_paid'
    | 'invoice_reminder'
    | 'proposal_sent'
    | 'proposal_accepted'
    | 'proposal_declined'
    | 'site_approved'
    | 'client_feedback'
    | 'message_sent'
    | 'message_received'
    | 'site_edited';

export type LeadActivity = {
    id: number;
    type: LeadActivityType;
    label: string;
    summary: string;
    data: Record<string, string | null> | null;
    author: string | null;
    createdAt: string;
};

export type EmailTemplate = {
    id: number;
    name: string;
    subject: string;
    body: string;
};

export type MergeField = { key: string; description: string };

export type BoardColumn = {
    status: LeadStatus;
    label: string;
    count: number;
    leads: Lead[];
};

export type FollowUp = {
    id: number;
    name: string;
    category: string | null;
    city: string | null;
    status: LeadStatus;
    dueOn: string;
    isOverdue: boolean;
    isDueToday: boolean;
};

export type DashboardFollowUps = {
    dueCount: number;
    items: FollowUp[];
};

export type SiteVersion = {
    id: number;
    label: string | null;
    template: string;
    headline: string;
    author: string | null;
    createdAt: string;
};

export type QualityReport = {
    checks: CheckItem[];
    passed: number;
    total: number;
};

export type LeadProfile = {
    id: number;
    name: string;
    category: string | null;
    description: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    rating: number | null;
    reviewsCount: number;
    mapsUrl: string | null;
    openingHours: Record<string, string>;
    vibe: string | null;
    logoUrl: string | null;
    photoUrls: string[];
    hasUploadedPhotos: boolean;
    detailsConfirmedAt: string | null;
    siteGenerationStatus: SiteGenerationStatus;
    siteGenerationError: string | null;
    site: { id: number; previewUrl: string; approvedAt: string | null } | null;
    status: LeadStatus;
    lostReason: string | null;
    assignedUserId: number | null;
    reviewsSummary: {
        reviewCount: number;
        averageRating: number | null;
        praise: string[];
        complaints: string[];
    } | null;
    competitorAudit: WebsiteAudit | null;
    competitorAuditedAt: string | null;
    website: string | null;
    dealValue: number | null;
    nextFollowUpAt: string | null;
    lastContactedAt: string | null;
    opportunityScore: number | null;
    opportunityTier: OpportunityTier | null;
    opportunityBreakdown: ScoreComponent[];
    websiteAudit: WebsiteAudit | null;
    websiteAuditedAt: string | null;
    websiteAuditStatus: SiteGenerationStatus;
    portalUrl: string | null;
    portalAvailable: boolean;
    conceptsStatus: SiteGenerationStatus;
};

export type SiteConceptOption = {
    id: number;
    template: string;
    label: string;
    vibe: string;
    headline: string;
    accent: string;
    previewUrl: string;
};

export type LeadConcepts = {
    available: boolean;
    status: SiteGenerationStatus;
    items: SiteConceptOption[];
};

export type LeadSearch = {
    id: number;
    keyword: string | null;
    location: string | null;
    placeUrl: string | null;
    status: 'pending' | 'running' | 'completed' | 'failed';
    resultsCount: number;
    error: string | null;
    createdAt: string;
};

export type SiteService = {
    title: string;
    description: string;
    slug?: string;
    body?: string;
};

export type SiteFaq = {
    question: string;
    answer: string;
};

export type SiteContent = {
    accent: string;
    headline: string;
    tagline: string;
    cta_label: string;
    about: string;
    sections: string[];
    reviews_style?: string;
    pages?: string[];
    services: SiteService[];
    faqs: SiteFaq[];
};

export type DnsRecord = {
    type: string;
    name: string;
    value: string;
    purpose: string;
};

export type SiteDomain = {
    id: number;
    hostname: string;
    isVerified: boolean;
    isPrimary: boolean;
    error: string | null;
    lastCheckedAt: string | null;
    records: DnsRecord[];
};

export type SitePublishing = {
    isPublished: boolean;
    publishedAt: string | null;
    liveUrl: string | null;
    canPublish: boolean;
    dnsConfigured: boolean;
    domains: SiteDomain[];
};

export type SitePost = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    isPublished: boolean;
    publishedAt: string | null;
};

export type SiteReview = {
    id: number;
    author: string;
    rating: number;
    body: string;
    isVisible: boolean;
};

export type SiteReviewsPanel = {
    items: SiteReview[];
    status: SiteGenerationStatus;
    error: string | null;
    fetchedAt: string | null;
    totalOnGoogle: number;
    minimum: number;
    canFetch: boolean;
};

export type SiteSummary = {
    id: number;
    name: string;
    category: string | null;
    city: string | null;
    leadStatus: LeadStatus;
    template: string;
    accent: string;
    headline: string;
    previewUrl: string;
    viewsCount: number;
    lastViewedAt: string | null;
};

export type EditableSite = {
    id: number;
    name: string;
    leadId: number;
    template: string;
    content: SiteContent;
    previewUrl: string;
    viewsCount: number;
    updatedAt: string | null;
    generationStatus: SiteGenerationStatus;
    previewExpiresAt: string | null;
    hasPreviewPassword: boolean;
    transferredAt: string | null;
    transferredToEmail: string | null;
};

export type SiteHealth = {
    uptimeOk: boolean | null;
    lastUptimeAt: string | null;
    lastCrawledAt: string | null;
    brokenLinks: { id: number; url: string; status: number | null; error: string | null }[];
    backupCount: number;
    comments: {
        id: number;
        author: string;
        section: string | null;
        body: string;
        createdAt: string;
    }[];
};

export type SiteChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    body: string;
    status: 'pending' | 'done' | 'failed';
    at: string;
};

export type SiteChat = {
    available: boolean;
    busy: boolean;
    messages: SiteChatMessage[];
};

export type DashboardStats = {
    leads: number;
    sites: number;
    previewViews: number;
    dealsWon: number;
    revenue: number;
};

export type DashboardSite = {
    id: number;
    name: string;
    category: string | null;
    city: string | null;
    leadStatus: LeadStatus;
    template: string;
    viewsCount: number;
    previewUrl: string;
    createdAt: string;
};

export type DashboardPipeline = Record<LeadStatus, number>;

export type DashboardChart = {
    days: { date: string; label: string; weekday: string }[];
    leads: number[];
    sites: number[];
};

export type DashboardActivityType =
    | 'site_created'
    | 'preview_viewed'
    | 'lead_found'
    | 'deal_won';

export type DashboardActivity = {
    type: DashboardActivityType;
    title: string;
    detail: string | null;
    at: string;
};

export type InvoiceStatus = 'draft' | 'open' | 'paid' | 'void' | 'refunded';

export type InvoiceSummary = {
    id: number;
    number: string;
    leadName: string;
    status: InvoiceStatus;
    statusLabel: string;
    isOverdue: boolean;
    total: string;
    dueOn: string | null;
    createdAt: string;
};

export type InvoiceDetail = {
    id: number;
    number: string;
    status: InvoiceStatus;
    statusLabel: string;
    isOverdue: boolean;
    leadId: number;
    leadName: string;
    leadEmail: string | null;
    dueOn: string | null;
    notes: string | null;
    items: { description: string; quantity: number; unitPrice: number }[];
    total: string;
    payUrl: string | null;
    sentAt: string | null;
    paidAt: string | null;
    paidVia: string | null;
    kind: string;
    kindLabel: string;
};

export type PaymentProviderSummary = {
    value: 'stripe' | 'paypal' | 'paystack' | 'flutterwave';
    label: string;
    connected: boolean;
    accountLabel: string | null;
    hint: string | null;
    mode: string | null;
    hasWebhookSecret: boolean;
    webhookUrl: string | null;
};

export type RecurringSchedule = {
    id: number;
    leadId: number;
    leadName: string;
    leadEmail: string | null;
    interval: string;
    intervalLabel: string;
    startsOn: string;
    nextRunOn: string | null;
    endsOn: string | null;
    dueInDays: number;
    emailClient: boolean;
    notes: string | null;
    status: 'active' | 'paused' | 'ended';
    items: { description: string; quantity: number; unitPrice: number }[];
    total: string;
    cyclesRun: number;
    lastRunAt: string | null;
};

export type LeadMessage = {
    id: number;
    channel: 'sms' | 'whatsapp';
    direction: 'in' | 'out';
    status: 'queued' | 'sent' | 'delivered' | 'failed' | 'received';
    body: string;
    error: string | null;
    at: string;
};

export type LeadMessaging = {
    available: boolean;
    connected: boolean;
    smsReady: boolean;
    whatsappReady: boolean;
    phone: string | null;
    consentAt: string | null;
    optedOutAt: string | null;
    hasWhatsAppChat: boolean;
    messages: LeadMessage[];
};
