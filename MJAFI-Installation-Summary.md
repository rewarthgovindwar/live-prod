# IMAGe WM Registry — Installation Summary
### Plain-language guide for mjafi.trecsa.in

**Date:** 17 August 2026  
**Website:** https://mjafi.trecsa.in  
**Server:** 103.217.253.53 (Trecsa hosting server)  
**Prepared for:** Non-technical readers and project stakeholders

---

## 1. What is this project?

This is a **medical data registry** for the IMAGe study group. It is meant to collect Waldenström macroglobulinemia (WM) patient information from multiple hospital centres across India, while making sure **each centre can only see its own patients**.

The package you uploaded (`mjafi.zip.zip`) contains two parts:

| Part | What it is | Status on server |
|------|------------|------------------|
| **Backend (server software)** | The real database and security system (FastAPI + PostgreSQL) | ✅ Installed and running |
| **Frontend (web page)** | The screen users see in the browser | ✅ Installed, but still a **demo version** |

**Important in simple terms:**  
The “engine” of the car is running. The “dashboard” (website screen) is installed, but it is still showing **sample/demo data** instead of talking to the real engine. This is the biggest remaining gap.

---

## 2. What was installed and where?

Think of the server like a building:

```
You (in a browser)
        ↓
   Front door (mjafi.trecsa.in — secure HTTPS)
        ↓
   Reception desk (LiteSpeed web server — already on your hosting)
        ├── Shows the web page (index.html)
        └── Sends API requests to the backend
                ↓
        Backend app (Docker container on port 8010 — internal only)
                ↓
        Database (PostgreSQL — internal only, not open to internet)
```

### Files on the server

| Location | Purpose |
|----------|---------|
| `/opt/mjafi/wm-registry-backend/` | Backend application and database (Docker) |
| `/home/mjafi.trecsa.in/public_html/index.html` | Website page users open |
| `/usr/local/lsws/conf/vhosts/mjafi.trecsa.in/` | Web server settings for this domain |

### What is running right now

- ✅ Website address: **https://mjafi.trecsa.in**
- ✅ Security certificate (HTTPS / padlock): Active until 15 November 2026
- ✅ Backend health check: Working (`/health` returns OK)
- ✅ Login API: Working (for technical testing)
- ✅ Database: Running with 6 centres and 5 test user accounts

---

## 3. How the installation was done (step by step, simple version)

### Step 1 — Open the package
The zip file `mjafi.zip.zip` was downloaded from the GitHub repo and unpacked. Inside were:
- The backend folder (`wm-registry-backend`)
- One large HTML file (the website interface)

### Step 2 — Connect to the server
We logged into your server at **103.217.253.53** using the **root** account.  
(The `admin` account did not work with the password provided.)

### Step 3 — Install Docker
Docker is a tool that runs the backend and database in isolated “containers” (like sealed boxes). It was not on the server before, so we installed it.

### Step 4 — Set up the backend
- Copied backend files to `/opt/mjafi/`
- Created secure passwords automatically
- Started the database
- Built and started the API (application)
- Ran database setup scripts (migrations)
- Created test centres and user accounts (seed data)

### Step 5 — Set up the website address
Using **CyberPanel** (your hosting control panel), we:
- Created the domain **mjafi.trecsa.in**
- Obtained a free SSL certificate (HTTPS)
- Placed the HTML file in the website folder

### Step 6 — Connect website to backend
Because your server already uses **LiteSpeed** for all websites (ports 80 and 443), we could **not** use the Caddy proxy that came in the zip. Instead:
- LiteSpeed shows the web page
- LiteSpeed forwards `/api/` and `/health` requests to the backend

### Step 7 — Verify
We tested:
- Website loads (HTTP 200)
- Health endpoint works
- Login API works with test credentials

---

## 4. Problems faced during installation (and how they were fixed)

| # | Problem | Why it happened | How it was fixed |
|---|---------|-----------------|------------------|
| 1 | Could not log in as `admin` | That user/password did not work on this server | Used `root` account instead |
| 2 | Docker was missing | Server had CyberPanel but not Docker | Installed Docker from official source |
| 3 | Port conflict (80/443) | LiteSpeed already uses these ports for all sites | Removed Caddy; used LiteSpeed as the front door |
| 4 | Backend config error | A setting needed special formatting | Fixed format in `.env` file |
| 5 | Database login failed for app user | Database was created before app password was configured | Recreated database with correct settings |
| 6 | “Site not visible” report | Server side is working; issue may be DNS, browser cache, or demo UI confusion | See Section 6 below |

---

## 5. How to install on a MAIN domain on shared hosting

This section explains what you would do if you wanted this on something like **`registry.yourhospital.in`** instead of a subdomain — using typical shared hosting (cPanel, CyberPanel, Plesk, etc.).

### Can it run on normal shared hosting?

**Partly.**

| Component | Shared hosting friendly? | Notes |
|-----------|-------------------------|-------|
| Frontend (HTML page) | ✅ Yes | Upload `index.html` like any website |
| Backend (Python API) | ⚠️ Often difficult | Needs Python 3.11+, long-running process, Docker ideally |
| Database (PostgreSQL) | ⚠️ Often not available | Many shared hosts only offer MySQL; this project needs **PostgreSQL** |
| Docker | ❌ Usually not allowed | Most shared plans do not allow Docker |

### Recommended approach on shared hosting

**Option A — Best for this project (what we did): VPS / cloud server**  
Use a server where you control everything (like your Trecsa server). This matches how the software was designed.

**Option B — Shared hosting for website only + API elsewhere**  
- Host the **web page** on shared hosting (main domain)
- Host the **backend** on a VPS or cloud service
- Point the website to the API address

**Option C — Fully managed cloud**  
Deploy the entire Docker package on AWS, DigitalOcean, Azure, etc. (needs technical help).

### Simple steps for main domain on a VPS (like yours)

1. **Point your domain** — Create an A record pointing to your server IP (e.g. `registry.hospital.in` → `103.217.253.53`)
2. **Create the website** in CyberPanel/cPanel for that domain
3. **Upload** the HTML file to `public_html`
4. **Install Docker** on the server (one-time)
5. **Upload backend** to `/opt/your-app/`
6. **Configure** passwords in `.env`
7. **Start** database and API containers
8. **Set reverse proxy** — forward `/api/` to the backend (like we did for mjafi.trecsa.in)
9. **Enable HTTPS** (Let’s Encrypt / AutoSSL)
10. **Test** health check and login
11. **Replace test users** with real hospital accounts
12. **Connect frontend to API** (currently missing — see Section 6)

### If you only have basic shared hosting (no Docker, no PostgreSQL)

You would need a developer to either:
- Host the backend on a separate server and connect the website to it, **or**
- Rebuild the backend for MySQL and shared hosting (significant work — not included in the zip)

**Bottom line for non-technical readers:**  
This is **not** a simple “upload zip and click install” WordPress-style app. It needs a server with Docker and PostgreSQL, or a split setup with the website on shared hosting and the backend on a VPS.

---

## 6. Current issues with the site (what still needs attention)

### Issue 1 — Frontend is a demo (most important)

**What users see:** A working-looking website with sample patients and data.  
**What is actually happening:** The page uses **fake data stored in the browser**, not the real database.

**What this means for you:**  
Opening https://mjafi.trecsa.in may look fine, but it is **not yet a live clinical system**. A developer must connect the web page to the backend API.

---

### Issue 2 — “Site not visible” — possible reasons

From our checks, the server **is responding correctly**. If you still cannot see it, try these:

| Possible cause | What to try |
|----------------|-------------|
| DNS not updated on your network | Wait up to 24–48 hours; try mobile data vs Wi‑Fi |
| Wrong address | Use exactly: `https://mjafi.trecsa.in` (with https) |
| Browser cache | Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac) |
| Blank or confusing screen | The demo page needs you to **click a user account** on the login screen before “Sign in” becomes active |
| Office firewall | Try from phone on mobile data |
| www vs non-www | Both should work; try without `www` first |

---

### Issue 3 — Test passwords still active

All seeded accounts use the same temporary password:  
**`ChangeMe#2026Registry`**

These must be changed before any real patient data is entered.

**Test accounts:**

| Email | Role |
|-------|------|
| deo.pgimer@example.org | Data entry (PGIMER) |
| clinician.pgimer@example.org | Clinician |
| hod.pgimer@example.org | Head of Department |
| deo.cmc@example.org | Data entry (CMC Vellore) |
| coordinator@imagesociety.co.in | National coordinator |

---

### Issue 4 — Server security not fully hardened

The app itself has good security design, but the server still needs:
- Firewall rules (only allow necessary ports)
- SSH key login instead of password
- Security headers (HSTS) on the web server
- Backup restore tested at least once

---

### Issue 5 — Shared server with many other sites

Your server also runs email, FTP, GitLab, WhatsApp hooks, and other websites. For real patient data long-term, a **dedicated server** is safer.

---

## 7. Security — explained simply

### What protects patient data (in the backend design)

| Protection | Plain English |
|------------|---------------|
| **Centre isolation** | Hospital A cannot see Hospital B’s records — enforced twice (app + database) |
| **Strong passwords** | System checks password strength |
| **Login lockout** | Too many wrong attempts → account locked for 30 minutes |
| **Two-factor auth** | Required for senior roles (HOD, national coordinator) |
| **Audit trail** | System records who changed what and when |
| **HTTPS** | Data encrypted in transit (padlock in browser) |
| **Database not public** | Database is not reachable from the internet |

### What is NOT protected yet

- The demo website does not use these protections (it is offline demo data)
- Test passwords are still in use
- Server firewall is not fully configured

---

## 8. Day-to-day operations (for your IT person)

### Check if everything is running

```bash
ssh root@103.217.253.53
cd /opt/mjafi/wm-registry-backend
docker compose -f docker-compose.prod.yml ps
curl -s http://127.0.0.1:8010/health
```

Expected health response:
```json
{"status":"ok","database":true,"environment":"production","version":"1.0.0"}
```

### Restart the backend (if needed)

```bash
cd /opt/mjafi/wm-registry-backend
docker compose -f docker-compose.prod.yml restart api
```

### Restart the web server (if needed)

```bash
/usr/local/lsws/bin/lswsctrl restart
```

### Backups

- Automatic backup scheduled daily at 2:00 AM server time
- Log file: `/var/log/mjafi-backup.log`
- Backup folder: `/opt/mjafi/wm-registry-backend/backups/`

---

## 9. What to do next (priority list)

1. **Confirm you can open** https://mjafi.trecsa.in from your location (try phone + computer)
2. **Hire/connect a developer** to wire the frontend HTML to the live API
3. **Replace test users** with real hospital email addresses
4. **Force everyone to change passwords** on first login
5. **Test backup restore** once
6. **Harden server security** (firewall, SSH keys)
7. **Get ethics approval and data-sharing agreements** in place before real data entry
8. **Plan dedicated hosting** if this moves to production with real patients

---

## 10. Quick reference card

| Item | Value |
|------|-------|
| **Live URL** | https://mjafi.trecsa.in |
| **Server IP** | 103.217.253.53 |
| **Hosting panel** | CyberPanel + LiteSpeed |
| **Backend location** | `/opt/mjafi/wm-registry-backend/` |
| **Website files** | `/home/mjafi.trecsa.in/public_html/` |
| **Test password** | `ChangeMe#2026Registry` |
| **SSL expires** | 15 November 2026 (auto-renew via CyberPanel) |
| **Status** | Backend live ✅ / Frontend demo only ⚠️ |

---

## 11. Glossary (simple terms)

| Term | Meaning |
|------|---------|
| **API** | The “waiter” that carries requests between the website and database |
| **Backend** | The server-side brain — database, security, business rules |
| **Frontend** | What you see and click in the browser |
| **Docker** | Tool to run apps in isolated containers on a server |
| **DNS** | Phone book of the internet — connects domain name to server IP |
| **HTTPS / SSL** | Secure encrypted connection (padlock icon) |
| **Reverse proxy** | Web server forwards certain requests to another program |
| **PostgreSQL** | The database software storing patient records |
| **Seed data** | Initial test data created during setup |
| **RLS** | Row Level Security — database rule that hides other centres’ rows |

---

## 12. Support checklist if site “does not show”

Ask your IT person to verify each item:

- [ ] Does `mjafi.trecsa.in` point to `103.217.253.53`? (DNS A record)
- [ ] Does https://mjafi.trecsa.in open from an external network?
- [ ] Is LiteSpeed running? (`lswsctrl status`)
- [ ] Are Docker containers running? (`docker compose ps`)
- [ ] Does health check return OK?
- [ ] Is SSL certificate valid?
- [ ] Is `index.html` present in `public_html`?
- [ ] Has the user selected an account on the login screen? (demo UI)

---

*This document describes the installation performed on 17 August 2026. For technical changes, always take a backup before modifying the server.*
