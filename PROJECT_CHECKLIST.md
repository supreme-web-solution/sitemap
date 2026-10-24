# Project checklist: AI Agency Operating System

Measured against the "How to Build a Better Version of @sitemap" report.
Last updated: 24 Oct 2026. Operating-loop slice tested with `php artisan test --compact tests/Feature/OperatingLoopTest.php`. Please run the full suite with `php artisan test --compact`.

Legend: `[x]` built and tested, `[~]` partly built (note says what is missing), `[ ]` not built.

Stack as built: Laravel 13, PHP 8.3, Inertia v3 + Vue 3 (TypeScript), Tailwind v4, Pest 4. Lead data from Mindcase. AI planning via Claude or OpenAI, with a rule-based fallback.

---

## 1. Discover (report section 2, feature 1; section 3 A and B)

- [x] Search businesses by location and industry (Mindcase), with search history chips to re-run a search
- [x] Business details: rating, review count, phone, address, opening hours, Maps link, website status
- [x] Filter: without website, with website, all places
- [x] Duplicate protection (one lead per business per team)
- [x] Save leads to the team; CSV import; CSV export
- [x] Website audit of an existing site (SSRF-safe fetcher): HTTPS, mobile viewport, page title, meta description, tap-to-call or email link
- [x] Opportunity score with five weighted components and Hot/Warm/Cold tiers, shown on the lead and used for sorting
- [x] Score weights are configurable per team on Ideal customer (must sum to 100); OpportunityScorer falls back to `config/prospecting.php`; saving queues a rescore so Hot/Warm/Cold stay honest
- [x] Contact completeness shown through the contactability score
- [x] Google reviews pulled per lead, filtered to real 4-5 star text reviews, kept for the website
- [x] Review summarisation (common praise and complaints extracted from stored reviews after a fetch)
- [x] Lead enrichment from the lead's public website (SSRF-safe fetch for email, phone, meta description)
- [x] Bulk collection across several locations in one go (same keyword, extra cities)
- [x] "Ideal customer" definition that filters the lead list
- [x] Competitor website analysis (same SSRF-safe auditor, counts against Audits)
- [x] Niche research brief (queued, rule-based; no extra AI keys)

## 2. Build (feature 2, 3, 4; section 3 C)

- [x] One-click AI website generation from a lead (queued, with status and retry)
- [x] Multi-page sites: Home, About, Services, one page per service, Gallery, Blog (optional), Contact
- [x] Optional pages are switchable per site; Contact is always on
- [x] Structured generation: business profile, then validated plan, then rendered site (not one big prompt)
- [x] Content verification before sharing: verified, inferred and missing information, so invented claims are avoided
- [x] Three templates (Modern Clean, Warm Studio, Editorial Dark) with three vibes and approved palettes
- [x] Section-based pages: enable, disable and reorder sections
- [x] Form-based editor for text, services, FAQ, hours, logo, photos, colours
- [x] Desktop, tablet and mobile preview inside the editor
- [x] Blog posts (create, edit, publish, delete) per site
- [x] Version history with restore
- [x] Quality report: SEO, content completeness and contact checks, plus a "fix before publishing" gate
- [x] Reviews/testimonials section and review structured data
- [x] Industry-specific designs: catalog layout variants (split hero, card services, editorial hero) on the three templates
- [x] Old vs new on the lead audit card: their site (sandboxed iframe) beside the generated preview when both exist
- [x] Custom pages: landing and location URLs; add/remove/edit in visual editor Pages tab (Structure)
- [x] Generate three concepts for one business and compare them (queued on `ai`, one job per vibe, pick to publish)
- [x] AI chat editing (queued on `ai`, snapshots a version first, plan-gated, monthly `ai_edits` cap)
- [x] "Improve" on each enabled editor section; posts through the existing site chat job
- [x] Visual editor: click-to-edit on the live preview (headline, tagline, button, about, services, FAQs, headings and labels), click a photo or the logo to replace it, hover a section to move, hide or improve it with AI, per-section background (tint / brand / dark), site fonts, corner style and spacing, undo and redo with typing merged into one step, autosave in small operation batches with a revision check (conflict banner, offline retry, rollback on rejected edits), edit/preview toggle and desktop/tablet/mobile. One editing session is one version in history. Operations are allowlisted and the whole document is validated server-side; editor markup only renders for signed-in team members and never in published sites or exports
- [~] Visual editor limits: images save immediately but last image change is undoable (Ctrl+Z); no free-form drag-and-drop layout, no custom colour per element; AI photo generation when OpenAI is configured; editing is limited to the existing templates and sections
- [x] Searchable template library by industry, style and palette (catalog entries; same three layouts underneath)
- [x] Brand pull from the lead's website (theme-color / CSS variables / logo image) via the same SSRF-safe fetcher; counts as an Audit; applies accent to the generated site
- [x] AI image generation (editor photo slot, OpenAI images API)
- [x] Multilingual sites (locale prefixes, hreflang, translation job, editor paths)
- [x] "Fix with AI" on quality-check (and alt-text health) findings; same chat job and `ai_edits` cap

## 3. Sell (feature 5, 6, 7; section 3 E)

- [x] Shareable preview link per site (noindex, `p/{slug}`), with a "claim" banner
- [x] Preview interest button: the prospect replies, the lead moves stage and the agency is notified
- [x] Preview view counter
- [x] Email Studio: reusable templates with merge fields
- [x] Outreach composer on the lead (sends through the app mail setup, rate limited)
- [x] Follow-up dates and a dashboard follow-up panel
- [x] Dashboard **Work today** panel (hot leads, previews to send, due follow-ups and tasks; one row per lead, copy preview link)
- [x] Lead profile **Redesign workflow** step card (audit → build → compare → share; scroll to audit and outreach)
- [x] Pipeline: New, Site generated, Contacted, Interested, Won, Lost, with a drag-and-drop board and matching sidebar filters
- [x] Activity timeline per lead (notes, emails, status changes, site, audit, invoices)
- [x] Deal value and revenue on the dashboard; dashboard stats, chart and recent activity
- [x] Teams with owner, admin and member roles, and invitations
- [~] Pipeline uses the six real statuses (including Site generated). The report suggests 10 custom stages; not planned
- [x] Proposal generator with packages and an approval button (public accept/decline, invoices created on accept)
- [x] Client chooses a package from the preview page (creates a sent proposal)
- [x] Preview upgrades: comments on sections, expiry and password protection, and a branded preview domain per agency (Branding page; TXT/CNAME verification, white-label plan feature, Caddy TLS allowed, share links and outreach use it). Live DNS/TLS test deferred
- [x] Per-section preview heat on Preview access (no-cookie beacon, skips `internal=1` editor loads)
- [x] Automated follow-up sequences (Day 1, 3, 6, 10) with stop and edit controls
- [x] Lost-deal reasons; conversion metrics on Reports
- [x] Assign leads to team members; tasks with due dates on the lead and dashboard
- [x] SMS and WhatsApp outreach (Zernio): per-team API key, consent, STOP/START, quiet hours, signed webhook, queued sends with idempotency. 10DLC/toll-free registration is done in Zernio, not in this app. Live API calls to be verified later.
- [x] Email integration: team Email inbox (encrypted IMAP), manual Log reply on leads, IMAP sync when `INBOX_IMAP_SYNC_ENABLED`, Message-ID threading for outreach/sequences → timeline + Interested

## 4. Deliver (section 3 D)

- [x] HTML/ZIP export, one folder per page, with sitemap and robots
- [x] Publish and manage: publish a verified site on a custom domain
- [x] Custom domains (up to 5 per site): TXT or CNAME/A verification, primary domain, 301 redirects to primary and HTTPS
- [x] Fast, SEO-focused public sites: edge caching with ETag/304, stale-while-revalidate, non-blocking fonts, titles, descriptions, canonical, JSON-LD, breadcrumbs, XML sitemap, robots
- [x] On-demand TLS endpoint for Caddy (`internal/domains/allowed`)
- [x] Quality gate: publishing is blocked while a check fails
- [x] Live verification on a real server (Forge/DigitalOcean + Caddy) and a real domain — deferred; test on the live server later
- [x] Client ownership transfer: unpublish, JSON backup, email the client (not a separate client-user account)
- [x] Scheduled JSON backups of published versions (`sites:backup`) and per-site staging host (`sites.staging_*`, promote to live)
- [x] Uptime monitoring for published sites (`sites:monitor`, queued on `monitoring`)
- [~] Automated checks beyond SEO: queued broken-link crawl plus heuristics (alt, inline image weight, language, viewport, HTML size, scripts, fonts, hero/LCP proxy) on Delivery checks. Full Lighthouse / CWV lab suite not planned this pass

## 5. Grow: payments and recurring revenue (feature F)

- [x] Agencies connect their own Stripe key or PayPal app (no platform approval); credentials encrypted; USD only
- [x] Credentials verified against the provider before saving; owner/admin only
- [x] Invoices: itemised, numbered per team, draft, issue, void, mark paid outside the app
- [x] Public pay page (noindex) with Stripe Checkout and PayPal buttons
- [x] Payment is confirmed only when invoice, amount and currency match; paid invoices move the lead to Won
- [x] Stripe webhook (signed, replay window) as a backup to the return-from-checkout check
- [x] PayPal webhook (verified through PayPal's signature API): captures approved orders even if the customer closes the tab
- [x] Invoice emails (queued) with reply-to the agency; invoice activity on the lead timeline
- [x] Recurring billing: monthly, quarterly or yearly schedules; each cycle issues a new invoice with its own pay link and emails it
- [x] Pause, resume (skips missed dates), edit future invoices, stop date, delete; no duplicate invoices on rerun; no month-end drift
- [x] Overdue reminders: emailed invoices only, 1 day after due, then every 7 days, maximum 3, logged on the lead
- [x] Daily scheduled commands: `invoices:run-recurring` (06:00) and `invoices:send-reminders` (09:00)
- [x] Recurring billing sends a pay link each cycle, or charges the client's saved Stripe card when the schedule is set to "Charge saved card" (falls back to emailing the pay link if no card or the charge fails)
- [x] Automatic card-on-file charging: Stripe (saved card off-session) and PayPal (vault token on first pay, token charge each cycle)
- [x] Deposits and milestone payments (invoice kind)
- [x] Revenue reports (paid, outstanding, refunded, recurring forecast, pipeline, lost reasons)
- [x] Refunds on paid invoices (provider refund when a capture exists; manual otherwise)
- [x] Paystack and Flutterwave (connect with the team's own keys; signed webhooks)

## 6. Platform: plans, white-label, portal, automation (feature section 3, 10, Phase 2 and 3)

- [x] Plans and usage limits (leads, searches, generations, review fetches, audits, emails, SMS, AI edits, sites, domains, members). Admin assigns plans; no self-serve platform billing. Seed with `php artisan db:seed --class=PlanSeeder` (Starter as default will cap teams that have no plan of their own)
- [ ] Subscription billing for the platform itself (not planned: agencies are billed outside the app)
- [x] Client portal (proposals, approvals, invoices, site updates)
- [x] Agency white-labelling (remove platform branding, branded invoices and previews)
- [ ] Reusable agency templates and design systems
- [ ] API access and third-party CRM integrations
- [ ] Workflow automation across prospecting, proposals, delivery and reporting
- [x] AI sales assistant (next-step suggestions by pipeline stage on the lead)
- [x] Scheduled weekly snapshots (`reports:send-weekly`, emailed to the agency)

## 7. Security, quality and operations

- [x] SSRF protection for user-supplied URLs (private and reserved addresses blocked)
- [x] Tenant isolation: every team query and route binding is scoped to the team
- [x] Rate limits: lead searches, audits, review fetches, outreach, preview interest, site generation, invoice payments
- [x] Secrets encrypted at rest; credentials hidden from model output and from the browser
- [x] Webhooks outside CSRF, but verified by signature
- [x] Automated tests across leads, sites, publishing, domains, invoices, payments, recurring billing
- [x] Server setup on Forge: cron for `php artisan schedule:run`, queue worker, mail driver, env values below — documented; validate with `php artisan ops:verify` / `--live` on the server
- [x] Mindcase reviews request format confirmed against the live API — deferred; test with a real call later
- [x] Real Stripe and PayPal test-mode payments end to end — deferred; faked in tests, live payment test later
- [~] Published-site JSON backups and uptime checks. No host-level error tracking product yet

---

## Launch verification (run on the Forge server)

After env is set, SSH in and run:

```bash
php artisan ops:verify
php artisan ops:verify --live
```

`ops:verify` checks APP_URL, queue driver, scheduler entries, `SITES_*` / TLS token, mail + IMAP sync, webhook routes, Mindcase/AI keys, failed jobs, and branded-preview DNS backlog. `--live` adds HTTP probes (dashboard, `/internal/domains/allowed`, Mindcase reachability).

Platform admins can also open **Admin → Launch readiness checks** (same static report; run `--live` on the server for HTTP probes).

Manual checks still required once per provider: Stripe/PayPal/Paystack/Flutterwave test checkout + webhooks, Zernio SMS/WhatsApp send/receive, and a **real customer domain** publish + TLS on Caddy.

## Server settings needed before going live

| Setting | Purpose |
| --- | --- |
| `SITES_APP_HOSTS` | Hostnames that serve the app itself (everything else is treated as a customer site) |
| `SITES_STAGING_BASE_HOST` | Optional suffix for staging sites (`{slug}.{value}`; defaults to `staging.{APP_URL host}`) |
| `SITES_CNAME_TARGET`, `SITES_SERVER_IP` | What customers point their domain at |
| `SITES_TLS_ASK_TOKEN` | Protects the Caddy on-demand TLS check |
| `SITES_FORCE_HTTPS` | Redirect customer sites to HTTPS |
| Cron: `* * * * * php artisan schedule:run` | Runs recurring invoices, reminders, sequences, site monitors, backups, weekly reports and clean-up |
| Queue worker | `php artisan queue:work --queue=messaging,default,ai,monitoring --tries=3 --timeout=90 --max-time=3600` (Forge daemon; redis recommended in production). `DB_QUEUE_RETRY_AFTER` must stay above the worker timeout (120s default). |
| Mail driver | Outreach, invoices, reminders, preview notifications, proposals |
| Mindcase, Anthropic or OpenAI keys | Lead search, AI planning, locale translation, and editor **Generate with AI** images |
| `OPENAI_IMAGE_MODEL` | OpenAI images API model (default `gpt-image-1` in `config/sites.php`) |
| `SITES_MAX_EXTRA_LOCALES` | Cap on extra locales per site beyond the default (default `3`) |
| `ADMIN_EMAILS` | Comma-separated platform admin emails (Admin area, impersonation, plans) |
| `ZERNIO_BASE_URL` | Defaults to `https://zernio.com/api/v1`. Each team pastes its own API key on Messaging. |
| `INBOX_IMAP_SYNC_ENABLED` | Set `true` on the server to poll team IMAP mailboxes (`mail:sync-inboxes` every 5 minutes). Manual reply logging works without it. |
| `INBOX_SYNCS_PER_MINUTE` | Rate limit for mailbox sync jobs (default 6). |
| `INBOX_IMAP_TIMEOUT_SECONDS`, `INBOX_IMAP_MAX_MESSAGES_PER_SYNC` | IMAP client tuning when sync is on. |

## Setup for each agency using payments

1. Payments page: connect Stripe, PayPal, Paystack and/or Flutterwave with the team's own keys.
2. Stripe: add the webhook URL shown, select `checkout.session.completed`, paste the signing secret.
3. PayPal: add the webhook URL shown, select "Checkout order approved" and "Payment capture completed", paste the webhook ID.
4. Paystack: add the webhook URL shown, paste the signing secret (HMAC SHA-512).
5. Flutterwave: add the webhook URL shown, paste the secret hash configured in Flutterwave.

## Suggested order for what is left

1. ~~PayPal card-on-file~~ · ~~template library~~ · ~~multilingual~~ · ~~AI images~~ · ~~staging~~ · ~~IMAP inbox~~ · ~~Work today / redesign workflow~~
2. **Live verification** on Forge/production (cron, queues, mail, `SITES_*`, Mindcase, branded preview DNS, Zernio, payment webhooks)
3. Optional later: full Lighthouse/CWV lab, custom CRM stages, agency template libraries, API/CRM, workflow automation
