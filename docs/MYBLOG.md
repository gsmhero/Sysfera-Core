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

## Permissions
Any signed-in user writes posts; the author and site administrators edit and delete them. No platform capability is used (new users get all capabilities).

## Basebase
Product embed code is the one the product page of basebase.ru copies (`Text::embedCode`). Blog synchronisation: see [BASEBASE-BLOG-API.md](BASEBASE-BLOG-API.md).
