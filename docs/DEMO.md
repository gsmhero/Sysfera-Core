# Demo mode (core 0.1.3)

For a public showcase install such as core.sysfera.ru. Settings → **Demo mode**.

While on:
- registration is open and every new visitor gets all capabilities (components can withhold some with the `demo.caps` filter — the Basebase connector hides itself);
- a banner on every page says that data is sample data and when the next reset happens;
- every N hours (1/3/6/12/24) the first request after the deadline wipes visitor data (lazy scheduler, no cron needed): clients, orders, invoices, non-admin users, the activity log, then fires the hook `demo.reset`, where each component deletes its own data and may load examples. The administrator account, site settings and installed components stay;
- **Reset now** in Settings does it on demand (type `RESET`).

For component authors:
```php
$sf->on('demo.reset', function () use ($db) { /* delete your tables' rows, insert sample data */ });
\Sysfera\Hooks::on('demo.caps', fn(array $caps) => array_diff($caps, ['mycomp.admin']));
```
Never enable demo mode on a real working site — it deletes data on a schedule.
