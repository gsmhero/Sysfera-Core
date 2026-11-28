# My Blog (`core/plugins/myblog`)

Posts of every signed-in user, public on their own site and, with "All Sysfera sites", shared with every installation.

## Setup
1. Install **Sysfera Messenger** and connect the site to the hub (Messenger → Settings → Connect).
2. Install **My Blog** (Components). On the hub site (core.sysfera.ru) it must be installed too: it answers `blog.publish`, `blog.remove`, `blog.feed`.
3. Basebase features need **WMS → Basebase** 0.6.3+: a user with a shop and published products can attach them and copy their embed codes.

## Network
* Posts live on the author's site. The hub stores only title, excerpt, author/shop name, cover address, product count and date (`myblog_hub`).
* The hub builds every link from the installation's registered address; a site cannot publish foreign links. A foreign cover is accepted only from basebase.ru.
* Limits: 20 new shared posts per user per day. Administrators of the hub site hide posts at My Blog → Moderate.
* Unsent changes stay marked and are retried when somebody opens or saves in My Blog.

## Public profile
`/p/myblog/u/{id}` is laid out like a shop page on Basebase: tabs Posts, Products (filter sidebar: name or SKU, price, category) and Sets. It exists once the user has a published post or product (otherwise 404, except for the user and administrators) and never shows the e-mail. "About me" is edited on the core Profile page. Sets come from Basebase and are cached for 10 minutes.

## Start page
For guests `/` shows post cards (hook `landing.feed`) with **Local only posts / Global** and a search over this site's products, people and shop names (posts follow the chosen scope). Without My Blog `/` shows the Sysfera introduction, which otherwise lives at `/components`; `/how` explains how it works. Public blocks of other components (`landing.blocks`) stay on `/`. Settings → start page off: guests are sent to sign in.

## Permissions
Any signed-in user writes posts; the author and site administrators edit and delete them. No platform capability is used (new users get all capabilities).

## Basebase
Product embed code is the one the product page of basebase.ru copies (`Text::embedCode`). Blog synchronisation: see [BASEBASE-BLOG-API.md](BASEBASE-BLOG-API.md).

## Gallery and videos (0.4.0)

* Up to 12 gallery pictures per post (stored like covers, in `storage/myblog/`); the first one stands in for a missing cover, also in the shared feed.
* Up to 8 video links. `lib/Media.php` builds the player address itself for YouTube, VK Video, Rutube, Vimeo, Dailymotion and OK; .mp4/.webm/.ogv play directly; any other link is a button.
* Gallery and videos are shown on this site only: they are not sent to Basebase.
