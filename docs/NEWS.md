# Core News

A box on every installation's dashboard (administrators only) with news, update notes and warnings from the Sysfera team.

## How posts are published
The posts are written in the **Core News publisher** component (`news-publisher`), which you install **only on core.sysfera.ru** (Components → Install). It adds a *Core News* entry to the menu: create, edit, publish/hide and delete posts, with Russian and English text, a type (news / update / warning), an optional expiry date, a "pin" switch and an optional https link. Warnings are red and always on top. Changes are live immediately in the public feed:

    GET https://core.sysfera.ru/api/news      → JSON list of live posts

Every installation fetches that address at most once an hour (cached in `storage/news.json`; the last copy is shown while the server is unreachable, and a failing server is not retried for 10 minutes). core.sysfera.ru itself reads its own posts straight from its database.

Post format in the feed: `{"id","date","type":"news|update|warning","pinned","expires","title":{"en","ru"},"body":{"en","ru"},"url"}`. Texts are plain (line breaks kept, HTML shows as text); links must be https; the feed carries up to 200 live posts.

## Dashboard box
Shows 5 posts by default; the selector offers 5, 20 or 50 per page, with page arrows and a from–to date filter (by post date, inclusive). Warnings stay on top.

## Automatic update posts
On core.sysfera.ru the publisher writes an *Update* post by itself the first time it runs on a new core version: "Sysfera Core X.Y.Z released", with the release notes from `updates/manifest.json` (the `SYSFERA_NOTES_EN/RU` of the build), or a default text. One post per version (slug `core-X.Y.Z`); edit, hide or delete it in the publisher like any other.

The publisher list has the same per-page, pagination and date filters.

## Privacy and switching off
A fetch exposes only the installation's IP address and `User-Agent: Sysfera/<version>`. Administrators can turn the feed off in Settings → Updates. The setting `news_url` (advanced) points an installation to another feed.

The feed is public — never write anything confidential in a post. Demo visitors never get the `news.admin` capability.
