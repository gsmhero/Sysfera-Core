# Sysfera Messenger and Sysfera Activity

Two components that connect **all** Sysfera installations through one central **hub** (core.sysfera.ru).

* **Messenger** — chat between any two users, on any sites, with file attachments.
* **Activity** — a public feed: every user posts a short note (title ≤ 20 words, text ≤ 50 words); readers press *Write to author* to open a Messenger chat. Needs Messenger.

## How it works
Every user gets a **Sysfera ID** `<user id>@<site domain>`, e.g. `12@shop.example.ru`. An installation connects to the hub once; after that it relays its users' requests to the hub. Messages, files and posts are stored **on the hub**, not on the sites.

### Connecting an installation (administrator)
1. Install **Messenger** (Components), open *Messenger → Connection*, press **Connect to Sysfera hub**.
2. The hub opens `https://<your site>/api/messenger/verify` once to check the site is yours (a random one-time challenge), then returns a private key that is stored in the site's settings.
3. The site must open over **https://** and have a public domain name (no IP addresses, no ports, no private networks).
4. Every later request is signed (HMAC-SHA256 over time, method, path, user id and body hash; ±5 minutes). A site is trusted only for the users of **its own domain**.

### Setting up the hub (core.sysfera.ru only)
Install Messenger (and Activity), open *Messenger → Connection → Advanced*, tick **This site is the hub**. The hub site talks to its own database directly. Files are kept in `storage/messenger/` (not reachable from the web). Grant `activity.admin` to moderators.

## Messenger rules
* Find people by exact Sysfera ID (always works), exact e-mail or name prefix (3+ letters) — the last two only for people who ticked *Let others find me* (off by default; the e-mail is stored only as a SHA-256 hash).
* Text ≤ 2000 characters, 30 messages/minute, 30 uploads/hour. Anyone can be blocked.
* Attachments ≤ **5 MB**: jpg, png, gif, webp, pdf, txt, csv, doc(x), xls(x), ppt(x), odt, ods, zip. The type comes from the extension, never from the browser; scripts, HTML, SVG and executables are refused; files download as attachments (images may show inline) with `nosniff` and a sandbox CSP. Only the sender and the recipient can fetch a file.
* The chat page checks for new messages every 8 seconds.

## Activity rules
Title ≤ 20 words, text ≤ 50 words (counted on the hub as well as in the browser), 3 posts per day per user. *Report* hides a post automatically after 3 reports from different users; moderators (hub site) can hide, show or delete in *Activity → Moderation*. Authors can delete their own posts. The feed has 5/20/50 per page, pagination and a from–to date filter.

## Privacy — please read
Using these components sends the user's **display name** (and, if allowed, a hash of the e-mail) and everything they write or upload to core.sysfera.ru, which stores it. Messages are **not end-to-end encrypted**; the hub operator can read them. Say so in your own privacy policy. Demo mode switches both components off.

## Limits of this first version
No e-mail notification of new messages, no unread badge in the menu, no automatic clean-up of old files, one Sysfera site per domain (two installs on one host name would share IDs). The operations are open to extension through `HubServer::register()` (see `core/plugins/activity/plugin.php`).

## Activity widget and embed code (Activity 0.2)
* **Dashboard ticker** — with Activity installed and connected, every signed-in user sees the latest posts scrolling on the dashboard (pauses on hover; no animation if the visitor prefers reduced motion). Administrators can switch it off in *Activity → Widget and embed code*. The list is cached for 5 minutes (`storage/activity-feed.json`), so the dashboard never waits for the hub.
* **Embed on any page** — an administrator (capability `activity.embed`) ticks *Allow embedding*, picks the number of posts, height and theme, and pastes the generated code into any page of the site:

      <div data-sysfera-activity data-count="5" data-height="160" data-theme="light"></div>
      <script async src="https://your-site/assets/p/activity/embed.js"></script>

  The script reads a public, read-only JSON feed at `/api/activity/feed` (title, text, author name, date — nothing else; sent with `Access-Control-Allow-Origin: *`). Texts are inserted as plain text, never as HTML. Untick *Allow embedding* and the feed stops at once (404). Embedding is off by default and unavailable in demo mode.
* The site reads the hub's feed as "user 0" (the installation itself); this works only for operations registered as site-readable.
