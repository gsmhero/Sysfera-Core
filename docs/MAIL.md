# Email (core 0.1.4)

Core sends mail itself over SMTP (no libraries; SSL on 465, STARTTLS on 587, or plain). Configure it in **Settings → Email (SMTP)**: server, port, encryption, login, password, sender address and name, and where notifications go (empty = every administrator). *Save and send a test email* sends a message to your own address and shows the exact SMTP error if it fails.

The settings live in the database (table `settings`, keys `smtp_*`, `notify_email`). GitHub Actions secrets such as `SMTP_HOST` are visible only to the deploy workflow, not to the running site, so the values are entered once in Settings (the password field is write-only afterwards).

## For components
```php
$sf->mail('buyer@example.com', 'Subject', 'Plain text', '<p>optional HTML</p>');   // null = sent, otherwise an error text
$sf->notify('Subject', 'Text');                                                    // to the administrators; silent no-op if SMTP is not set up
\Sysfera\Hooks::on('mail.sent', fn(array $to, string $subject, ?string $error) => …);
```
The WMS → Basebase connector mails the administrators when a Basebase order is accepted and when one needs attention (unknown SKU, not enough stock) — once per problem, not on every retry.

Tests: `php tests/mail.php` (header encoding, injection safety, multipart, dot-stuffing).
