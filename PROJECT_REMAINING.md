# Remaining work: AI Agency Operating System

Tailored to the "How to Build a Better Version of @sitemap" strategy (Discover → Build → Sell → Deliver → Grow → Automate).

**Core MVP and Phase 2 are largely shipped** (prospecting, AI site generation, visual editor, previews, pipeline, invoices, recurring billing with Stripe card-on-file, client portal, white-label, and most delivery tooling). **This file is the honest gap list** — only what is not done, partly done, or still needs live verification.

Last updated: **24 Oct 2026** (Work today dashboard, lead redesign workflow, custom pages, IMAP inbox)

Stack: Laravel 13, PHP 8.3, Inertia v3 + Vue 3 (TypeScript), Tailwind v4, Pest 4.

Legend: `[ ]` not built · `[~]` partly built (still needed below)

---

## Suggested priority and build order

Aligned with the main checklist’s recommended sequence:

1. ~~**Grow — PayPal card-on-file**~~ **Done** (vault on first PayPal pay link; off-session order + token for recurring auto-charge; Pest coverage)
2. ~~**Build — searchable template library (foundation)**~~ **Done** (catalog table, industry/style/palette filters, generation picker on Leads). ~~**Multilingual sites**~~ and ~~**AI image generation**~~ **Done** (locale prefixes + translation job; editor AI photo generate when OpenAI key set)
3. ~~**Deliver — staging host**~~ **Done** (`{slug}.staging.{base}` hostname, promote to live, UI + Pest). ~~Deepen delivery checks~~ **Partly done** (HTML size, script count, font hints, hero/LCP proxy heuristics; no Lighthouse)
4. ~~**Sell — email inbox connection and reply tracking**~~ **Done (MVP)** — team IMAP inbox settings, manual log reply, Message-ID threading, scheduled IMAP poll when `INBOX_IMAP_SYNC_ENABLED=true` (Zernio stays SMS/social only).
5. **Operations — live verification** on Forge/production — **`php artisan ops:verify` / `--live`** and **Admin → Launch readiness** (automated env/schedule/queue/mail/IMAP/TLS/webhook checks); manual proof still needed for payment webhooks, Zernio, and a real customer domain

~~**Dashboard “work today”**~~ and ~~**lead redesign workflow**~~ **Done** (hot leads, preview-not-sent, due follow-ups/tasks; step card on lead profile).

Visual editor polish and report-only pipeline stages can run in parallel once the above unblock revenue and differentiation.

---

## 1. Discover

Report: business discovery, audit, scoring, ideal customer, enrichment.

- [x] **Lead enrichment** — SSRF-safe website profile fetch (mailto/meta/tel) queued from the lead profile; merges email, phone and description when blank

---

## 2. Build

Report: AI sites, templates, editor, concepts, quality, brand pull, custom pages, multilingual, images.

- [x] **Industry-specific designs** — catalog `layout_variant` (split hero, card services, editorial hero) applied via body classes on top of the three Blade themes
- [x] **Custom landing and location pages** — `/landing/{slug}` and `/locations/{slug}`; add/remove/edit in Site Edit → **Pages** tab (Structure)
- [~] **Visual editor** — click-to-edit, sections, autosave, AI improve, **one-step image undo** (Ctrl+Z / toolbar), conflict and offline retry UI; **still needed (explicit limits today):**
  - No free-form drag-and-drop layout
  - No **custom colour per element** (only section-level background and global design tokens)
  - ~~No **AI image generation** in the editor~~ **Done** (photo slot; requires `OPENAI_API_KEY`)
  - Editing remains bound to **existing templates and sections** (no arbitrary new sections from the canvas)
- [x] **Searchable template library** — catalog metadata maps to the three Blade themes; filters on Leads before generation (more industry rows can be added without new layouts)
- [x] **AI image generation** for editor photo slots (OpenAI images API; not auto-generated during site build)
- [x] **Multilingual sites** — locale URL prefixes, hreflang, dashboard translation queue, editor translation paths

---

## 3. Sell

Report: previews, outreach, pipeline, proposals, sequences, inbox.

- [~] **Pipeline stages** — six fixed statuses including Site generated; **still needed if matching the report literally:** up to ~10 **custom CRM stages** per agency (currently **not planned**; revisit only if product strategy changes)
- [x] **Email integration:** team **Email inbox** (IMAP credentials encrypted), **Log reply** on leads, automatic IMAP sync when enabled, outreach **Message-ID** / In-Reply-To matching → timeline + Interested stage
Deferred live checks (feature code shipped, production proof pending — see Operations):

- Branded preview domain: **DNS/TLS verification on a real hostname**
- Zernio SMS/WhatsApp: **live API send/receive** (10DLC remains in Zernio)

---

## 4. Deliver

Report: publish, domains, export, quality, staging, monitoring, CWV.

- [x] **Backups and staging** — scheduled JSON backups (`sites:backup`) plus per-site staging host `{slug}.staging.{SITES_STAGING_BASE_HOST or staging.{APP_URL}}`, publish/remove staging, promote to production
- [~] **Delivery checks beyond SEO** — broken-link crawl plus heuristics (alt, image weight, language, viewport, HTML size, script count, font loading hints, hero/LCP proxy); **still needed:** full **Lighthouse / Core Web Vitals** lab run (not planned without Lighthouse integration)
- [ ] **Live end-to-end delivery verification** on Forge/DigitalOcean + Caddy with a **real customer domain** (TLS, redirects, caching, publish gate)

Host-level observability:

- [~] Published-site JSON backups and uptime jobs exist; **still needed:** **host-level error tracking** (e.g. aggregated 5xx / application errors), not only uptime pings

---

## 5. Grow (payments and recurring revenue)

Report: agency payment connectors, invoices, recurring, card-on-file.

- [x] **Automatic card-on-file charging** — Stripe saved-card off-session charges and PayPal vaulted payment tokens for recurring auto-charge (live PayPal vault still to verify in sandbox/production)

Deferred live checks (see Operations):

- **Real Stripe and PayPal test-mode** checkout and webhooks on production URLs

---

## 6. Automate (platform, templates, integrations)

Report Phase 2–3: agency design systems, API/CRM, workflow automation.

- [ ] **Reusable agency templates and design systems** (team-level libraries reused across many client sites)
- [ ] **API access** and **third-party CRM integrations**
- [ ] **Workflow automation** across prospecting, proposals, delivery, and reporting (beyond sequences, scheduled reports, and the AI next-step assistant)

Out of scope (explicit in checklist — not a build ticket unless strategy changes):

- **Subscription billing for the platform itself** (agencies billed outside the app)

---

## Operations and launch verification

Automated (run on the server after deploy):

- [x] **`php artisan ops:verify`** — env, queue, scheduler, domains/TLS config, mail/IMAP, payment webhook URLs, integrations, failed jobs, pending branded-preview DNS
- [x] **`php artisan ops:verify --live`** — HTTP probe of APP_URL and `/internal/domains/allowed`; Mindcase reachability when a key is set
- [x] **Admin → Launch readiness checks** — same static report for platform admins

Still **manual on production** (one-time proof per environment):

- [ ] **Forge/server cutover:** cron (`schedule:run`), queue worker (`messaging`, `default`, `ai`, `monitoring`), mail driver delivering
- [ ] **Custom domains and on-demand TLS** with Caddy — publish a site on a **real** hostname
- [ ] **Mindcase** — run a lead search and review fetch against live API (ops:verify --live is a smoke test only)
- [ ] **Branded preview hostname** — agency completes DNS on Branding; verify in app
- [ ] **Zernio** — outbound SMS/WhatsApp and signed webhook on production
- [ ] **Stripe / PayPal / Paystack / Flutterwave** — webhooks and a full pay flow on production URLs

Reference: server settings table in `PROJECT_CHECKLIST.md` (hosts, CNAME target, queue command, per-agency webhook setup).

---

## Manual QA still recommended

Full **visual editor save flow** on the authenticated site **Edit** page: HTTP regression in `VisualEditorTest` (includes 409 conflict and image undo) plus **browser smoke on `127.0.0.1:8000`** (login → Edit → iframe `editor=1` → headline edit → autosave → undo). **Optional browser pass:** offline retry banner and conflict “Reload latest” after simulating a stale tab.

---

## Quick map to report phases

| Report phase | Remaining theme |
| --- | --- |
| **Phase 1 (MVP)** | Feature-complete in code; **live ops verification** remains |
| **Phase 2 (differentiation)** | Template library, multilingual, AI images, industry layouts, landing/location pages, staging, CWV depth, PayPal card-on-file |
| **Phase 3 (platform scale)** | Agency template libraries, API/CRM, workflow automation |

For the full shipped inventory, see `PROJECT_CHECKLIST.md`.
