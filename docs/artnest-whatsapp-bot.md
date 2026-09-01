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

## Blue ticks (read receipts) — Sep 2026 fix

Customers were seeing **blue ticks** (read) when the bot processed messages — often with **“typing…”** in the header — even though no human opened the chat.

**Root cause:** North Star was sending WhatsApp **typing indicators** while the bot processed inbound messages. Meta’s API marks the message as read when typing is sent. The CRM UI did not expose these toggles; they had to be set via API.

**Settings now applied (live):**

| Setting | Value | Purpose |
|---------|-------|---------|
| `bot_typing_enabled` | 0 | Stop bot “typing…” (also stops auto read) |
| `bot_send_typing` | 0 | Same — do not send typing on bot path |
| `ai_typing_enabled` | 0 | Stop AI typing indicator |
| `bot_read_receipt` | 0 | Do not send read receipt on bot path |
| `bot_auto_mark_read` | 0 | Do not mark read when bot auto-replies |
| `bot_mark_read` | 0 | Legacy bot read-receipt flag |
| `ai_read_receipt_on_reply` | 0 | Do not mark read when AI replies |
| `ai_skip_read_receipt` | 1 | Skip read receipts on AI path |

**Also required:** Close the CRM inbox tab when not actively replying. An open chat tab polls the server every few seconds and can still mark messages read.

**Do not** send manual replies from the CRM unless a human is replying — that assigns the chat to an agent and disables the bot (`HUMAN_HANDOVER`).

## Suggested test

Message the business WhatsApp number from a personal phone. You should see:

1. Warm welcome with catalogue link and order steps
2. Three buttons: View Catalogue, How to Order, Talk to Us
3. Button taps return helpful follow-up messages; “Talk to Us” hands off to the team
4. **Grey ticks** on your message until a human opens the chat in CRM (not blue from bot typing)

## Tomorrow (nice-to-haves)

- Rich product cards or Meta catalog integration
- Hindi/Marathi language picker if needed
- Order confirmation templates
- Re-enable AI intent routing once flows are stable

## Main website (artnestindia.com)

Updated site source is in [`artnest-site/`](../artnest-site/). Changes mirror the WhatsApp bot and Instagram @artnest_handcraft tone:

- “ArtNest family” hero + Made in India / 10K+ Instagram badge
- **How to Order** section (catalogue → screenshot → WhatsApp)
- Warmer contact copy linking Instagram

**Deploy:** upload `index.html`, `styles.css`, `script.js` via StackCP File Manager or FTP (`ftp.artnestindia.com`). Hosting panel credentials were locked during this session — use https://3735053a.stackcp.com/login or reset via Serverbyt.

## Instagram (@artnest_handcraft)

Brand voice observed (public posts):

- Warm, grateful, community-focused (“10K FAM”, “one order at a time”)
- Products: silicone moulds, resin supplies, handmade craft materials
- Customers often ask “how to order” in comments — website + WhatsApp bot now answer this clearly

WhatsApp Business profile updated live with Instagram as second website link and order steps in the description.
