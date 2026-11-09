# Cloudflare for SaaS — MapMatic custom domain HTTPS

Reference for how MapMatic issues HTTPS for **branded preview hostnames** and **published client domains**, using Cloudflare Custom Hostnames (SSL for SaaS) in front of the Laravel Forge origin.

Last validated: November 2026 (mapmaticapp.com Free plan).

---

## 1. What this solves

Customers (and agencies) need their own hostnames to open MapMatic sites over HTTPS, for example:

| Use case | Example hostname | Where it is managed in the app |
| --- | --- | --- |
| Branded preview links | `test.mapmaticapp.com` | Team → Branding → Preview domain |
| Published client site | `test.socifusion.com` | Site → Settings → Publish & domains |

Without Cloudflare for SaaS, Forge only has a certificate for `app.mapmaticapp.com`. Any other `Host` header either fails TLS (`ERR_SSL_UNRECOGNIZED_NAME_ALERT` / wrong cert CN) or never reaches Laravel.

**Flow:**

```
Browser
  → Cloudflare (cert for customer hostname)
    → Fallback origin origin.mapmaticapp.com (proxied)
      → Forge / nginx (MapMatic as default_server)
        → Laravel ServePublishedSite / preview routes
```

Customers **CNAME** their hostname to `sites.mapmaticapp.com` (the SaaS CNAME target). Cloudflare terminates SSL and forwards to the fallback origin with the original host.

---

## 2. Cloudflare dashboard setup

Zone: **mapmaticapp.com**

### 2.1 Enable Cloudflare for SaaS / Custom Hostnames

1. Cloudflare Dashboard → **mapmaticapp.com**
2. **SSL/TLS** → **Custom Hostnames** (sometimes under “SSL for SaaS” / for Platforms)
3. Confirm the product is available (Free includes a limited hostname quota; confirm current Cloudflare limits)

### 2.2 Fallback Origin (required)

Custom Hostnames will not finish verification until the Fallback Origin is **Active**.

| Setting | Value used |
| --- | --- |
| Fallback Origin | `origin.mapmaticapp.com` |
| Status | **Active** |

**DNS for the fallback origin** (same zone):

| Type | Name | Target / content | Proxy |
| --- | --- | --- | --- |
| A (or CNAME as preferred) | `origin` | Forge server public IP | **Proxied** (orange cloud) |

Forge must serve a valid HTTPS certificate for `origin.mapmaticapp.com` (add the domain/alias on the Forge site and let Forge issue SSL).

### 2.3 SaaS CNAME target

Customers point domains at this hostname (not directly at the server IP):

| Hostname | Role |
| --- | --- |
| `sites.mapmaticapp.com` | CNAME target for client + branded hostnames |

Create DNS so `sites.mapmaticapp.com` is on Cloudflare (typically proxied and routed like other SaaS target records for the zone). It should resolve to Cloudflare anycast IPs (same family as other orange-cloud records), **not** a bare Forge IP when looked up publicly.

### 2.4 API token

Create an API token with at least:

- **Zone → SSL and Certificates → Edit** on `mapmaticapp.com`

Optional related permissions if Cloudflare’s UI suggests them for Custom Hostnames (zone read, etc.).

Save:

- Token → `CLOUDFLARE_API_TOKEN`
- Zone ID (Overview → API section on the right) → `CLOUDFLARE_ZONE_ID`

### 2.5 SSL/TLS mode for the zone

Use **Full** (or **Full (strict)** once the origin cert is trusted for the fallback hostname).

Avoid **Flexible** for this setup (browser→CF HTTPS, CF→origin HTTP causes confusing failures).

---

## 3. Application / Forge environment

On the Forge site for `app.mapmaticapp.com`, set:

```env
# Dashboard hosts (never treated as published client sites)
SITES_APP_HOSTS=app.mapmaticapp.com,origin.mapmaticapp.com

# What customers CNAME to
SITES_CNAME_TARGET=sites.mapmaticapp.com

# Cloudflare for SaaS
CLOUDFLARE_API_TOKEN=...
CLOUDFLARE_ZONE_ID=...

# Documents the Fallback Origin hostname (dashboard). Do NOT enable custom origin on Free.
CLOUDFLARE_ORIGIN_HOSTNAME=origin.mapmaticapp.com
# CLOUDFLARE_CUSTOM_ORIGIN=false   # default — leave unset/false on Free plan
```

Also ensure:

```env
APP_URL=https://app.mapmaticapp.com
```

After changing env on Forge:

```bash
cd ~/app.mapmaticapp.com/current
php artisan config:clear
php artisan config:cache
```

Confirm the cached log path points at the **current** site, not an old Forge site name:

```bash
php artisan config:show logging.channels.single.path
# Expect: /home/forge/app.mapmaticapp.com/.../storage/logs/laravel.log
# NOT:    /home/forge/sitemap-….on-forge.com/...
```

---

## 4. Nginx: MapMatic must be the only `default_server`

Cloudflare (and unknown customer hosts) connect to the origin with a `Host` that is **not** `app.mapmaticapp.com`. Nginx must still accept the connection and hand it to PHP.

### 4.1 Goal

- **`app.mapmaticapp.com` site file:** `listen … default_server` on 443 (and ideally 80 if you terminate HTTP there).
- **`server_name`:** include `app.mapmaticapp.com`, `origin.mapmaticapp.com`, and `_` (catch-all) as needed so MapMatic handles unknown hosts.
- **Forge `000-catch-all`:** must **not** use `default_server` (otherwise duplicate default server / wrong vhost).

### 4.2 Strip `default_server` from Forge catch-all

```bash
# Backup OUTSIDE sites-enabled (nginx loads every file in that folder, including .bak)
sudo cp /etc/nginx/sites-enabled/000-catch-all /home/forge/000-catch-all.bak

sudo sed -i 's/ default_server//g' /etc/nginx/sites-enabled/000-catch-all

# Confirm
sudo grep -n "listen" /etc/nginx/sites-enabled/000-catch-all
# Expect:
#   listen 80;
#   listen [::]:80;
#   listen 443 ssl;
#   listen [::]:443 ssl;

sudo grep -Rn "default_server" /etc/nginx/sites-enabled/
# Expect ONLY app.mapmaticapp.com (443 lines)

sudo nginx -t && sudo systemctl reload nginx
```

**Gotcha:** Never leave `000-catch-all.bak` inside `/etc/nginx/sites-enabled/` — nginx will load it and you get `duplicate default server`.

### 4.3 Confirm unknown Host reaches Laravel

```bash
curl -Ik https://127.0.0.1/ -H "Host: test.mapmaticapp.com"
# Want a normal HTTP status (e.g. 404/302/200 from Laravel), NOT:
#   OpenSSL … tlsv1 unrecognized name
```

---

## 5. How the app registers hostnames

### 5.1 Code map

| Piece | Role |
| --- | --- |
| `App\Services\Cloudflare\CloudflareCustomHostnames` | API client (create / get / find / delete) |
| `App\Services\Cloudflare\SyncCloudflareHostname` | Sync site domains + team preview hostnames |
| `App\Services\Domains\DomainVerifier` | DNS ownership check; calls Cloudflare after verify |
| `App\Services\Preview\TeamPreviewHostnameVerifier` | Same for branding preview host |
| `App\Jobs\RefreshCloudflareHostnameStatuses` | Scheduled poll until SSL is active |
| `App\Http\Middleware\ServePublishedSite` | Serves published domains; passes `/p/…` on verified preview hosts |

### 5.2 Create payload (important)

On **Free**, create requests must **not** send:

- `custom_origin_server`
- `custom_origin_sni`

Those require paid SSL for SaaS provisioning. Sending them returns:

**Cloudflare error 1456** — *Access to setting a custom origin SNI has not been granted…*

Traffic uses the **Fallback Origin** instead. Opt-in only if Cloudflare has provisioned custom origin for the account:

```env
CLOUDFLARE_CUSTOM_ORIGIN=true
```

### 5.3 Published domain flow (Publish & domains)

1. User adds hostname (e.g. `test.socifusion.com`).
2. MapMatic immediately registers a Cloudflare Custom Hostname (API) so the UI can show Cloudflare’s ownership TXT without anyone opening the Cloudflare dashboard.
3. UI shows DNS instructions from `DomainVerifier::instructions()` until HTTPS is live:
   - TXT `_sitemap-verify.<hostname>` = MapMatic verification token
   - CNAME `<hostname>` → `sites.mapmaticapp.com` (**required** for customer domains — “does not CNAME to this zone” if missing)
   - TXT `_cf-custom-hostname.<hostname>` = Cloudflare ownership token (from API), when returned
4. User clicks **Check DNS** / **Refresh HTTPS**.
5. On MapMatic DNS success, status refreshes from Cloudflare until hostname + SSL are **Active**.
6. Status badges: Waiting for DNS → DNS verified → Live HTTPS.
7. Removing the domain deletes the Custom Hostname via the API.

Same-zone hostnames (e.g. `test.mapmaticapp.com`) often activate without a separate customer-zone CNAME because they already live on the SaaS zone. External domains always need the CNAME (and usually the Cloudflare ownership TXT).

### 5.4 Branded preview flow (Branding)

Same Cloudflare sync path via `syncTeamPreview()` / `forgetTeamPreview()`. Preview UI also polls every few seconds while HTTPS is activating.

---

## 6. Customer DNS checklist

For a **subdomain** client hostname:

| Type | Name | Value |
| --- | --- | --- |
| TXT | `_sitemap-verify.test` (or full name shown in UI) | Token from MapMatic |
| CNAME | `test` (or full hostname) | `sites.mapmaticapp.com` |

Proxy status on the **customer’s** DNS:

- If their DNS is also Cloudflare, CNAME to `sites.mapmaticapp.com` should be set as Cloudflare documents for SaaS (often DNS-only gray cloud on the customer side so the SaaS product owns the proxy — follow current Cloudflare for SaaS docs for the customer zone).

After DNS propagates, in MapMatic click **Check DNS**, then wait for **Live HTTPS**. Confirm in Cloudflare → Custom Hostnames that the hostname appears with Active certificate.

---

## 7. Verification commands

### 7.1 Public DNS / certificate (should be Cloudflare)

```bash
dig +short test.example.com
dig +short sites.mapmaticapp.com
# Expect Cloudflare anycast IPs (e.g. 104.x / 172.x), not the Forge IP alone

echo | openssl s_client -servername test.example.com -connect test.example.com:443 2>/dev/null \
  | openssl x509 -noout -subject -issuer -ext subjectAltName
# Expect: CN/SAN = test.example.com, issuer = Cloudflare …
```

If the browser shows a cert for **`app.mapmaticapp.com`**, traffic is hitting Forge **directly** (wrong DNS / not proxied through SaaS).

### 7.2 Origin behavior

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://127.0.0.1/up -H "Host: app.mapmaticapp.com"
# 200

curl -sk -o /dev/null -w "%{http_code}\n" \
  "https://127.0.0.1/p/<site-slug>" \
  -H "Host: <preview-or-branded-host>"
```

### 7.3 Logs

```bash
cd ~/app.mapmaticapp.com/current
ls -la storage/logs/
tail -n 80 storage/logs/laravel.log
```

Look for: `Cloudflare custom hostname sync failed` with the API message.

---

## 8. Problems we hit and fixes

### 8.1 `ERR_SSL_UNRECOGNIZED_NAME_ALERT` / empty reply on unknown Host

**Cause:** nginx had no `default_server` (or catch-all owned it) for MapMatic.  
**Fix:** MapMatic site = sole `default_server`; remove `default_server` from `000-catch-all`; move `.bak` out of `sites-enabled`.

### 8.2 Browser: cert is for `app.mapmaticapp.com` (`ERR_CERT_COMMON_NAME_INVALID`)

**Cause:** hostname resolved straight to origin, not through Cloudflare Custom Hostname SSL.  
**Fix:** CNAME to `sites.mapmaticapp.com`, Custom Hostname Active, zone SSL Full. Confirm with `openssl s_client` (issuer must be Cloudflare).

### 8.3 Cloudflare Custom Hostname missing + UI “HTTPS still being set up”

**Cause (Nov 2026):** API 403 / code **1456** — create payload included `custom_origin_sni` while account is Free.  
**Fix:** ship code that only sends custom origin when `CLOUDFLARE_CUSTOM_ORIGIN=true`. Deploy, then click **Refresh HTTPS** on the domain.

### 8.4 Whole app 500s, empty `storage/logs`

**Cause:** `php artisan config:cache` baked an old absolute path from a previous Forge site name (`sitemap-….on-forge.com/.../laravel.log`). Logging and other cached paths broke.  
**Fix:**

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan config:show logging.channels.single.path   # must be app.mapmaticapp.com
```

### 8.5 Conflicting server name warnings for `_` / `origin.mapmaticapp.com`

**Cause:** same `server_name` in MapMatic vhost and catch-all.  
**Effect:** warning only; first matching server wins. Clean duplicates when convenient; not fatal if MapMatic is `default_server`.

### 8.6 Forge may rewrite nginx

Forge site SSL / “Files” edits can restore `000-catch-all` `default_server`. After Forge nginx regenerations, re-check:

```bash
sudo grep -Rn "default_server" /etc/nginx/sites-enabled/
```

---

## 9. Operational checklist (new environment)

1. [ ] Zone on Cloudflare; SSL mode Full  
2. [ ] DNS: `origin` → Forge IP, proxied; Forge SSL for `origin.mapmaticapp.com`  
3. [ ] Fallback Origin = `origin.mapmaticapp.com` → **Active**  
4. [ ] `sites.mapmaticapp.com` CNAME target ready  
5. [ ] API token + zone ID in Forge env; `SITES_CNAME_TARGET=sites.mapmaticapp.com`  
6. [ ] `CLOUDFLARE_CUSTOM_ORIGIN` left false on Free  
7. [ ] `php artisan config:cache` with correct site path  
8. [ ] nginx: only MapMatic has `default_server`; catch-all cleaned  
9. [ ] Local curl with foreign `Host` returns Laravel response  
10. [ ] Add test hostname in Branding or Publish → verify DNS → appears under Custom Hostnames → Active SSL  
11. [ ] Browser loads `https://<hostname>/…` with Cloudflare cert (incognito)

---

## 10. Quick re-sync after a failed HTTPS setup

If DNS already verified but hostname never appeared in Cloudflare:

1. Deploy the build that omits custom origin on Free.  
2. In MapMatic → Publish & domains → **Refresh HTTPS**.  
3. Cloudflare → SSL/TLS → Custom Hostnames → confirm the hostname.  
4. Wait for certificate + hostname **Active**, then hard-refresh the live URL.

---

## 11. Admin: list & delete Cloudflare hostnames

Platform admins (`ADMIN_EMAILS`) can open:

**Admin → Cloudflare custom hostnames** (`/admin/cloudflare-hostnames`)

- Live list from the Cloudflare API (status, certificate, verification errors)
- Shows whether each hostname is linked to a MapMatic site domain or preview hostname
- **Delete** removes it from Cloudflare via API and clears local Cloudflare columns (does not remove the MapMatic domain row)

Use this to clean orphans such as hostnames created by accidental live API calls during tests.

### Customer domains that are *also* on Cloudflare

If Cloudflare says: *“The hostname is using Cloudflare and cannot be activated with a TXT or HTTP validation token… DNS target needs to point to the SaaS zone.”*

The customer’s DNS for that hostname must be a **CNAME to `sites.mapmaticapp.com`** (SaaS zone), not an A record and not a CNAME to `app.mapmaticapp.com`. TXT alone is not enough when the customer zone is Cloudflare.

## 12. Related files

- `config/services.php` — `cloudflare.*`  
- `config/sites.php` — `cname_target`, `app_hosts`, …  
- `.env.example` — documented env keys  
- `app/Services/Cloudflare/*`  
- `app/Services/Domains/DomainVerifier.php`  
- `app/Http/Controllers/Sites/SiteDomainController.php`  
- `app/Http/Controllers/Teams/BrandingPreviewHostnameController.php`  
- `resources/js/components/SitePublishPanel.vue`  
- `resources/js/pages/branding/Edit.vue`  
- `tests/Feature/Sites/CloudflareCustomHostnameTest.php`  
