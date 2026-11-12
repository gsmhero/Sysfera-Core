<?php
/* Installer texts. To add a language: copy this file to xx.php (same folder) and also add lang/xx.php for the program. */
return [
    'title' => 'Install Sysfera',
    'step' => 'Step {n} of {total}',
    'lead' => 'Welcome! This wizard installs Sysfera on this domain in about two minutes. You only need an empty MySQL database.',
    'next' => 'Continue', 'back' => 'Back', 'retry' => 'Check again',

    's1' => 'Server check',
    's1_hint' => 'We check that your hosting can run Sysfera. Green is fine. Red must be fixed first — write to your hosting support with the text shown, they usually fix it in minutes.',
    'r_php' => 'PHP {v} or newer', 'r_pdo' => 'PHP extension pdo_mysql (database)', 'r_mb' => 'PHP extension mbstring (text)', 'r_json' => 'PHP extension json',
    'r_zip' => 'PHP extension zip (needed for automatic updates; installation works without it)',
    'r_w_storage' => 'Folder core/storage is writable', 'r_w_plugins' => 'Folder core/plugins is writable', 'r_w_root' => 'Folder core is writable (config file)',
    'r_fix_w' => 'Set permissions 755 (or 775) for this folder in your FTP client or hosting file manager.',
    'ok' => 'OK', 'bad' => 'Missing', 'warn' => 'Recommended',

    's2' => 'Database',
    's2_hint' => 'In your hosting control panel (cPanel, ISPmanager, Plesk…) create an empty MySQL database and a user for it, then enter the details here. "Host" is almost always localhost. If you install several Sysfera copies into one database, give each a different table prefix.',
    'db_host' => 'Database host', 'db_port' => 'Port (empty = default)', 'db_name' => 'Database name', 'db_user' => 'Database user', 'db_pass' => 'Database password', 'db_prefix' => 'Table prefix',
    'db_fail' => 'Could not connect: {err}',
    'db_unknown' => 'Unknown database. Check the name — it often has your hosting login as a prefix, e.g. "login_sysfera".',
    'db_denied' => 'Access denied. Check the user name and password, and that the user is assigned to this database.',
    'db_prefix_bad' => 'The prefix may contain only letters, digits and underscore, and must end with "_".',
    'db_has_tables' => 'This database already has Sysfera tables with this prefix. Choose another prefix, or the existing install would be damaged.',

    's3' => 'Your site and administrator',
    's3_hint' => 'This is the first account. It has full access. Use a real email (you will sign in with it) and a strong password — at least 8 characters.',
    'site_name' => 'Site / company name', 'language' => 'Language', 'adm_name' => 'Your name', 'adm_email' => 'Email (login)', 'adm_pass' => 'Password (min. 8 characters)',
    'install_now' => 'Install Sysfera',
    'err_name' => 'Enter your name.', 'err_email' => 'Enter a valid email.', 'err_pass' => 'The password must be at least 8 characters.',
    'err_install' => 'Installation failed: {err}',

    's4' => 'Done!',
    's4_hint' => 'Sysfera is installed. For safety delete install.php from the site folder (the button below tries to do it for you).',
    'open' => 'Open Sysfera', 'delete_installer' => 'Delete install.php', 'deleted' => 'install.php deleted.', 'not_deleted' => 'Could not delete it automatically — please delete install.php with your FTP client.',
    'next_steps' => 'What next?',
    'next1' => 'Sign in with the email and password you just set.',
    'next2' => 'Open "Components" and install what you need (Warehouse, CRM, Accounting…).',
    'next3' => 'Under "Settings" you can change the language, time zone and who may register.',
    'already' => 'Sysfera is already installed here.',
    'cfg_help' => 'Could not write core/storage/config.php. Make the folder writable, or create the file with this content and press "Check again":',
];
