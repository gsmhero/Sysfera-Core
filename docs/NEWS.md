# Core News

A feed on every installation's dashboard (administrators only) for news, update notes and warnings from the Sysfera team.

## How to publish a post
Edit `news/news.json` in the repository, commit and push. The build copies it to `updates/news.json` and the deploy publishes it at `https://core.sysfera.ru/updates/news.json`. Installations fetch it at most once an hour (cached in `storage/news.json`; the last copy is shown while the server is unreachable).

```json
{
  "id": "2026-12-01-core-0-2",
  "date": "2026-12-01",
  "type": "warning",
  "pinned": false,
  "expires": "2026-12-31",
  "title": { "en": "Update core before 31 Dec", "ru": "Обновите ядро до 31 декабря" },
  "body":  { "en": "Details…", "ru": "Подробности…" },
  "url": "https://sysfera.ru/news/core-0-2"
}
```
- `type`: `news`, `update` or `warning` (warnings are red and always on top; `pinned: true` pins any post).
- `expires` (optional) hides the post after that day; `url` must be https; texts are plain (line breaks kept, HTML is shown as text).
- `title` / `body` may be a plain string or per-language `{en, ru}`; English is the fallback. At most 6 posts are shown — remove old ones or let them expire.
- The file must stay valid JSON — the build fails otherwise.

## Privacy and switching off
The request exposes only the installation's IP address and `User-Agent: Sysfera/<version>`. Administrators can turn the feed off in Settings → Updates. Installations with their own update server read `news.json` next to their `manifest.json`.
