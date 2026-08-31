# ArtNest WhatsApp Bot — Customer Experience

Live admin: https://whatsapp.artnestindia.com  
WhatsApp orders: [9175278533](https://wa.me/919175278533)

## Catalogue & order flow

**Catalogue:** https://drive.google.com/file/d/18h7qJ5tl4lCu6ugViuvNdIK7-8KX-hCx/view

**How customers order:**

1. Select products from the catalogue
2. Take a screenshot of the selected products
3. Send the screenshot with name, phone, and delivery address on WhatsApp

## What was configured (Aug 2026)

The bot at `whatsapp.artnestindia.com` was updated for a warm, personal first impression:

- **Welcome message** — Namaste greeting, catalogue link, and simple 3-step order instructions
- **Welcome buttons** — View Catalogue · How to Order · Talk to Us
- **First-message bot flow** — Interactive menu with catalogue, order steps, and human handoff
- **AI context** — Org notes include catalogue URL and order steps; intent auto-routing disabled to avoid confusing redirects
- **After-hours & inactivity** — Friendly, reassuring copy (not robotic closings)
- **Canned reply** (`/welcome`) — Quick agent reply with catalogue + order steps

## Admin access

| Panel | URL | Notes |
|-------|-----|-------|
| WhatsApp CRM | https://whatsapp.artnestindia.com/login | Username: `admin` |
| Serverbyt | https://cp.serverbyt.in | Hosting panel (credentials may differ) |

**Settings → Bot & AI** — welcome message, office hours, AI toggles  
**Automations → Bot flows** — `ArtNest Welcome` (first message, published & enabled)  
**Settings → Quick replies** — canned `/welcome` shortcut

## Suggested test

Message the business WhatsApp number from a personal phone. You should see:

1. Warm welcome with catalogue link and order steps
2. Three buttons: View Catalogue, How to Order, Talk to Us
3. Button taps return helpful follow-up messages; “Talk to Us” hands off to the team

## Tomorrow (nice-to-haves)

- Rich product cards or Meta catalog integration
- Hindi/Marathi language picker if needed
- Order confirmation templates
- Re-enable AI intent routing once flows are stable
