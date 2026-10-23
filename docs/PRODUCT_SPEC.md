# Sitemap — Product Specification

A client-acquisition platform for agencies, freelancers and web sales teams, modelled on the workflow of [@sitemap](https://atsitemap.com/):

> **Find** a local business on Google Maps → **Build** it a website in seconds → **Show** the owner a live preview link → **Close** the deal and export the site.

This is **not** a general-purpose AI website builder (like Krikkit, with code editors, file trees and consoles). The website generator exists to serve the sales workflow: every generated site is a pitch for a specific prospect.

---

## 1. Who uses it

| Role                                 | What they do                                                                   |
| ------------------------------------ | ------------------------------------------------------------------------------ |
| **Agency / freelancer (team owner)** | Owns a workspace, searches for leads, generates sites, closes deals.           |
| **Sales rep (team member)**          | Works leads inside the team workspace, shares previews, updates the pipeline.  |
| **Prospect (business owner)**        | Never logs in. Opens a public preview link and can click "Claim this website". |

Each agency is a **team** (already provided by the starter kit). Every lead, search and site belongs to a team, which gives us multi-tenant isolation from day one.

---

## 2. Feature list

Legend: **P1** = Phase 1 (built now), **P2** = next, **P3** = later.

### 2.1 Lead Finder (Google Maps prospecting)

| Feature                                                                   | Phase |
| ------------------------------------------------------------------------- | ----- |
| Search by keyword/industry + city/location                                | P1    |
| Filter: only businesses **without a website** / with / all                | P1    |
| Filter: minimum star rating                                               | P1    |
| Choose how many results to fetch (cost control)                           | P1    |
| Searches run in the background (queue), page updates when done            | P1    |
| Search history per team with status (pending, running, completed, failed) | P1    |
| De-duplicate leads per team by Google `place_id`                          | P1    |
| Sort leads by reviews / rating                                            | P1    |
| Re-run a previous search                                                  | P2    |
| Bulk "generate sites for all selected leads"                              | P2    |
| Verify website status (detect broken / outdated sites, not just missing)  | P3    |
| Lead scoring (reviews × rating × no website)                              | P3    |

Data source: **Mindcase Google Maps Places API** (`POST /agents/google/maps/places/run`, polled via `GET /jobs/{id}/results`). The integration sits behind a `LeadFinder` contract so the provider can be swapped (Google Places, Outscraper, etc.) without touching the rest of the app.

### 2.2 Business Profiles (leads)

| Feature                                                           | Phase |
| ----------------------------------------------------------------- | ----- |
| Name, category, phone, address, city, country, coordinates        | P1    |
| Rating, review count, opening hours, Google Maps URL, cover photo | P1    |
| Website status badge ("No website")                               | P1    |
| Internal notes per lead                                           | P1    |
| Owner-provided assets: logo, photos, brand colours                | P2    |
| Email discovery for outreach                                      | P2    |

### 2.3 Website Generator (our own engine)

Principle: **the AI never writes free-form HTML/CSS.** It produces a validated _site specification_ (JSON), and a deterministic renderer turns that spec into HTML using professionally designed templates. That is what keeps quality consistent.

| Feature                                                                                                     | Phase |
| ----------------------------------------------------------------------------------------------------------- | ----- |
| One-click generation from a lead                                                                            | P1    |
| Site content stored as a structured JSON spec (sections, copy, theme)                                       | P1    |
| Import one business by pasting a Google Maps link or place ID                                               | P1    |
| Owner review screen: confirm/correct details, upload logo + photos, pick a vibe (modern / warm / bold)      | P1    |
| Industry-aware copy (headline, tagline, services, about, CTAs, FAQ) without inventing facts                 | P1    |
| **Claude planner** (forced tool call → JSON), validated in code, one retry with errors, rule-based fallback | P1    |
| Generation runs on the queue; leads page and editor show "Building…" live                                   | P1    |
| Deterministic, self-contained HTML templates (no external CSS framework at runtime)                         | P1    |
| Theme tokens: approved accent palette per template, font pairing, computed text contrast                    | P1    |
| Templates: _Modern Clean Pro_, _Warm Studio_, _Editorial Dark_                                              | P1    |
| Section library (services, about, gallery, hours & map, FAQ, contact) in a planned, editable order          | P1    |
| More templates: Corporate Blue, Minimalist, Midnight                                                        | P2    |
| 2–3 variants per section, reviews section                                                                   | P2    |
| 6 languages                                                                                                 | P2    |
| Multi-page sites (services, about, contact pages)                                                           | P3    |
| Automated QA: Playwright screenshots + Lighthouse, one auto-fix loop                                        | P3    |

**Copy guardrails (enforced in code):** never invent awards, years in business, prices, licences, guarantees or "best/#1" claims unless the owner-confirmed description says so; ratings only when the business has them; accent must be in the template's palette; headline max 70 characters. If data is missing, the section is omitted (gallery needs 2+ photos, hours needs hours or an address). Owner-confirmed details are never overwritten by later searches.

### 2.4 Live Demo / Preview

| Feature                                                      | Phase |
| ------------------------------------------------------------ | ----- |
| Public, unguessable preview URL per site (`/p/{slug}`)       | P1    |
| "Claim this website" banner on the preview                   | P1    |
| Copy-link button in the dashboard                            | P1    |
| Preview view counter (know when the prospect opened it)      | P1    |
| Per-site subdomain (`austin-plumbing.demosites.example`)     | P2    |
| Claim form → notifies the agency, moves lead to _Interested_ | P2    |
| Checkout on claim (Stripe)                                   | P3    |

### 2.5 Website Editor

| Feature                                                                | Phase |
| ---------------------------------------------------------------------- | ----- |
| Edit headline, tagline, about, services, CTA, phone, colours, template | P1    |
| Side-by-side live preview                                              | P1    |
| Inline editing (click text on the preview to edit)                     | P2    |
| Replace images, reorder/add/remove sections                            | P2    |
| Regenerate a single section with AI                                    | P2    |
| Social media links                                                     | P2    |

### 2.6 Sales Pipeline

| Feature                                                                 | Phase |
| ----------------------------------------------------------------------- | ----- |
| Lead stages: New → Site generated → Contacted → Interested → Won / Lost | P1    |
| Change stage from the leads list                                        | P1    |
| Deal value on won leads                                                 | P1    |
| Dashboard metrics: leads, sites, live previews, deals won, revenue      | P1    |
| Kanban board view                                                       | P2    |
| Follow-up reminders / tasks                                             | P2    |

### 2.7 Outreach

| Feature                                                  | Phase |
| -------------------------------------------------------- | ----- |
| Email Studio: editable email templates with merge fields | P2    |
| Send email from the platform (team's own SMTP / Resend)  | P2    |
| Automated follow-up sequences                            | P3    |
| SMS pitch (Twilio)                                       | P3    |

### 2.8 Export

| Feature                                                      | Phase |
| ------------------------------------------------------------ | ----- |
| Download finished site as ZIP (`index.html`, self-contained) | P1    |
| Images bundled into the ZIP                                  | P2    |

### 2.9 Platform / Billing

| Feature                                         | Phase |
| ----------------------------------------------- | ----- |
| Accounts, teams, invitations, 2FA (starter kit) | Done  |
| Usage limits per plan (searches, generations)   | P2    |
| Lifetime / subscription billing                 | P3    |

---

## 3. Architecture

```
┌──────────────┐   queue    ┌──────────────────┐   HTTP   ┌──────────┐
│ Lead Finder  │──────────▶ │ RunLeadSearch job │────────▶ │ Mindcase │
│ (Vue page)   │            └──────────────────┘          └──────────┘
└──────┬───────┘                     │ upsert leads
       │                             ▼
       │                    ┌──────────────────┐
       │  generate          │ leads (per team) │
       └──────────────────▶ └────────┬─────────┘
                                     │ GenerateSite action
                                     ▼
              ┌───────────────────────────────────────────┐
              │ SiteCopywriter (rule-based now, Claude P2) │
              │  → validated SiteContent JSON spec         │
              └───────────────────────┬───────────────────┘
                                      ▼
              ┌───────────────────────────────────────────┐
              │ SiteRenderer (Blade templates, no AI)      │
              │  → self-contained HTML                     │
              └──────┬──────────────────────────┬─────────┘
                     ▼                          ▼
             Public preview /p/{slug}      ZIP export
```

### Key decisions

- **Laravel 13 + Inertia v3 + Vue 3 + Tailwind v4** for the app. No second framework (Astro/Next) until publishing requirements justify it.
- **Team-scoped everything.** All tables carry `team_id`; routes live under `/{current_team}/…`; nested route bindings are scoped to the team.
- **Contracts at the boundaries.** `LeadFinder` (Mindcase today) and `SiteCopywriter` (rule-based today, Claude next) are bound in a service provider.
- **Background work on queues.** Lead searches are queued jobs that poll the provider without blocking a web request. Generation will move to the queue when the AI copywriter lands.
- **Site = data, not files.** A site is a JSON spec in the database. Rendering is deterministic, so editing, re-theming and exporting are cheap and safe.
- **Rendered output is self-contained HTML** with inline CSS. It works on any static host, which is exactly what the export needs.

### Data model (Phase 1)

| Table           | Purpose               | Key columns                                                                                                                                                                                                 |
| --------------- | --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `lead_searches` | One search request    | team, user, keyword, location, website_filter, minimum_rating, max_results, status, provider_job_id, results_count, error                                                                                   |
| `leads`         | One business per team | team, lead_search, place_id (unique per team), name, category, phone, address, city, country, lat/lng, rating, reviews_count, website, opening_hours (json), photo_url, maps_url, status, deal_value, notes |
| `sites`         | Generated website     | team, lead, slug (public, unique), template, content (json spec), views_count, last_viewed_at                                                                                                               |

---

## 4. Built for scale

| Concern                | Approach                                                                                                      |
| ---------------------- | ------------------------------------------------------------------------------------------------------------- |
| Slow third-party calls | Queued jobs with `retryUntil`, release-and-poll instead of sleeping, explicit HTTP timeouts.                  |
| Duplicate leads        | Unique index `(team_id, place_id)` + bulk `upsert`.                                                           |
| Large lists            | Server-side pagination, indexes on `(team_id, status)` and `(team_id, created_at)`.                           |
| Abuse / cost control   | Rate limiting on search and generation endpoints; `max_results` capped.                                       |
| Preview traffic        | Previews are read-only renders; ready for response caching / CDN. View counting is a single atomic increment. |
| Queue throughput       | `QUEUE_CONNECTION=redis` + Horizon in production (database queue works locally).                              |
| Swappable providers    | `LeadFinder` and `SiteCopywriter` contracts.                                                                  |
| Database               | SQLite locally; MySQL/Postgres in production with the same migrations.                                        |

---

## 5. Legal / data-use notes

- Google Maps data via Mindcase is subject to Mindcase's terms and Google's Maps Platform policies (storage limits, attribution). **Confirm with your boss/legal before launch.**
- Treat details the business owner confirms or edits as their own content.
- The map section uses a real Google Maps embed.
- Do not publish Google reviews on generated sites without proper attribution; Phase 1 shows only the aggregate rating.

---

## 6. Build phases

1. **Phase 1 (now):** Lead finder via Mindcase (search or pasted Maps link), leads list + pipeline stages, owner review screen with logo/photo uploads and vibe, queued Claude planner with guardrails and rule-based fallback, three templates, section library, editor form, public preview with claim banner, ZIP export, dashboard metrics.
2. **Phase 2:** Section variants, more templates, inline editing, subdomains, claim form, Kanban, Email Studio, usage limits, bundling uploaded images into the ZIP export.
3. **Phase 3:** QA automation, multi-page sites, checkout, follow-up sequences, SMS, lead scoring.

---

## 7. Open questions for the boss

1. Pricing model: lifetime deal like @sitemap, or subscription with usage limits?
2. Preview domain to use for live demos (e.g. `demosites.yourbrand.com`)?
3. Which AI provider/budget for the copywriter (Claude recommended)?
4. Which industries to design templates for first?
5. Do we send outreach email ourselves, or through each agency's own mailbox?

---

## 8. Configuration

| Env variable        | Purpose                                                        |
| ------------------- | -------------------------------------------------------------- |
| `MINDCASE_API_KEY`  | Mindcase key (`mk_live_…`) from https://console.mindcase.co    |
| `MINDCASE_BASE_URL` | Defaults to `https://api.mindcase.co/v1`                       |
| `ANTHROPIC_API_KEY` | Enables the Claude planner; without it rule-based copy is used |
| `ANTHROPIC_MODEL`   | Defaults to `claude-sonnet-4-5`                                |
| `OPENAI_API_KEY`    | Enables the OpenAI planner (same rules and fallback)           |
| `OPENAI_MODEL`      | Defaults to `gpt-5.5`                                          |
| `SITE_PLANNER`      | `anthropic` or `openai`; only needed when both keys are set    |
| `QUEUE_CONNECTION`  | `database` locally, `redis` in production                      |

Run a queue worker locally: `php artisan queue:work` (or `composer run dev`).
