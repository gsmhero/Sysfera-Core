<?php
/* English — the reference language. To add a language: copy this file to lang/xx.php (xx = language code), translate the
   values, keep the keys and {placeholders}. It then appears in every language switcher automatically. */
return [
    '_name' => 'English',

    'common.save' => 'Save', 'common.delete' => 'Delete', 'common.cancel' => 'Cancel',

    'time.now' => 'just now', 'time.min' => '{n} min ago', 'time.hour' => '{n} h ago', 'time.day' => '{n} d ago',

    'nav.dashboard' => 'Dashboard', 'nav.plugins' => 'Components', 'nav.users' => 'Users', 'nav.settings' => 'Settings',
    'nav.updates' => 'Updates', 'nav.profile' => 'Profile',

    'role.admin' => 'Administrator', 'role.user' => 'User',

    'err.403' => 'Access denied', 'err.403_text' => 'You do not have permission to open this page. Ask an administrator to grant access.',
    'err.404' => 'Page not found', 'err.404_text' => 'This page does not exist.',

    'auth.login' => 'Sign in', 'auth.logout' => 'Sign out', 'auth.register' => 'Create account',
    'auth.email' => 'Email', 'auth.password' => 'Password', 'auth.password_min' => 'Password (min. 8 characters)', 'auth.name' => 'Name',
    'auth.no_account' => 'No account yet?', 'auth.have_account' => 'Already have an account?',
    'auth.reg_closed' => 'Registration is closed. Ask the administrator to create an account for you.',
    'auth.welcome' => 'Welcome!',
    'auth.wrong' => 'Wrong email or password.', 'auth.blocked' => 'This account is blocked.', 'auth.too_many' => 'Too many attempts. Wait a minute and try again.',
    'auth.bad_email' => 'Enter a valid email address.', 'auth.need_name' => 'Enter a name.', 'auth.short_password' => 'The password must be at least 8 characters.',
    'auth.email_taken' => 'This email is already registered.',

    'dash.title' => 'Dashboard', 'dash.clients' => 'Clients', 'dash.orders' => 'Orders', 'dash.invoices' => 'Invoices', 'dash.components' => 'Components',
    'dash.your_components' => 'Your components',
    'dash.empty_title' => 'No components installed yet',
    'dash.empty_admin' => 'Sysfera is the core: clients, orders and invoices shared by every component. Install a component (Warehouse, CRM, Accounting…) to start working.',
    'dash.empty_user' => 'No components are available for you yet. Ask the administrator to install them or grant you access.',

    'profile.account' => 'Account', 'profile.language' => 'Language', 'profile.password' => 'Change password', 'profile.current' => 'Current password',
    'profile.new' => 'New password (min. 8 characters)', 'profile.saved' => 'Saved.', 'profile.wrong_password' => 'The current password is wrong.',

    'users.hint' => 'Administrators can do everything. For other users tick the components they may use.',
    'users.role' => 'Role', 'users.status' => 'Status', 'users.active' => 'Active', 'users.blocked' => 'Blocked', 'users.you' => 'you',
    'users.last_login' => 'Last login', 'users.caps' => 'Access:', 'users.add' => 'Add user',
    'users.created' => 'User created.', 'users.saved' => 'User saved.', 'users.deleted' => 'User deleted.',
    'users.not_self' => 'You cannot change or delete your own account here.',
    'users.confirm_delete' => 'Delete this user?',

    'settings.site_name' => 'Site name', 'settings.default_lang' => 'Default language', 'settings.timezone' => 'Time zone',
    'settings.registration' => 'Registration', 'settings.registration_open' => 'Allow visitors to register themselves',
    'settings.registration_hint' => 'Off by default: only administrators create accounts. If you open registration, choose what new users may use.',
    'settings.default_caps' => 'New users get access to:',
    'settings.updates' => 'Updates', 'settings.update_url' => 'Update server (manifest URL)',
    'settings.update_hint' => 'Leave empty to use the official server. Only HTTPS addresses are accepted for downloads.',
    'settings.saved' => 'Settings saved.',

    'plugins.hint' => 'Components add features to Sysfera: warehouse, CRM, accounting, vacancies… They all share the same clients, orders and invoices.',
    'plugins.installed' => 'Installed', 'plugins.none_installed' => 'Nothing installed yet.',
    'plugins.available' => 'Available', 'plugins.none_available' => 'No other components found.',
    'plugins.hint_available' => 'Components found in the plugins folder or on the update server. Installing creates the component\'s tables; your existing data is never touched.',
    'plugins.enabled' => 'enabled', 'plugins.disabled' => 'disabled', 'plugins.update_to' => 'update to v{v}', 'plugins.files_missing' => 'Files of this component are missing.',
    'plugins.install' => 'Install', 'plugins.enable' => 'Enable', 'plugins.disable' => 'Disable', 'plugins.uninstall' => 'Uninstall',
    'plugins.download' => 'Download', 'plugins.on_server' => 'on update server',
    'plugins.confirm_disable' => 'Disable this component? Its data is kept.',
    'plugins.confirm_uninstall' => 'Uninstall this component? Its data tables are kept, so reinstalling brings everything back.',
    'plugins.upload' => 'Upload a component', 'plugins.upload_btn' => 'Upload',
    'plugins.hint_upload' => 'Upload a component .zip (it must contain plugin.json). Then install it from the list above.',
    'plugins.installed_ok' => 'Component installed.', 'plugins.disabled_ok' => 'Component disabled.', 'plugins.uninstalled_ok' => 'Component uninstalled.',
    'plugins.uploaded_ok' => 'Component "{name}" uploaded. Now install it.',
    'plugins.need_core' => 'Needs Sysfera {v}.', 'plugins.need_plugin' => 'Needs the component "{name}" to be installed first.',
    'plugins.need_plugin_v' => 'Needs the component "{name}" {v}.', 'plugins.not_found' => 'Component not found.',
    'plugins.used_by' => 'Cannot disable: the component "{name}" depends on it.',
    'plugins.bad_zip' => 'This is not a valid component package.', 'plugins.write_failed' => 'Could not write to the plugins folder. Check its permissions.',

    'upd.hint' => 'Updates replace program files only. Your data, settings, uploads and component data are never touched, and a backup is made before every update.',
    'upd.core' => 'Sysfera core', 'upd.last_check' => 'last check', 'upd.check' => 'Check for updates',
    'upd.unreachable' => 'The update server could not be reached.', 'upd.checked' => 'Checked.',
    'upd.available' => 'Version {v} is available.', 'upd.update_to' => 'Update to {v}', 'upd.update' => 'Update', 'upd.up_to_date' => 'You are up to date.',
    'upd.confirm' => 'Make a backup and install this update?', 'upd.plugins' => 'Component updates',
    'upd.backups' => 'Backups', 'upd.no_backups' => 'No backups yet.',
    'upd.backups_hint' => 'Each folder holds the replaced files (files.zip) and a full database dump (database.sql). To roll back, restore them with FTP / phpMyAdmin.',
    'upd.done' => 'Updated to {v}.', 'upd.none' => 'No update available.',
    'upd.need_zip' => 'The PHP "zip" extension is required for updates. Ask your hosting to enable it.',
    'upd.https_only' => 'Update downloads must use HTTPS.', 'upd.download_failed' => 'Download failed.',
    'upd.bad_checksum' => 'The downloaded file is damaged (checksum mismatch). Nothing was changed.',
    'upd.bad_signature' => 'The update signature is invalid. Nothing was changed.',
    'upd.bad_package' => 'The update package is invalid. Nothing was changed.',
    'upd.not_writable' => 'Files are not writable. Check folder permissions.',
    'upd.failed' => 'Update failed: {why}. Previous files were restored; backup: storage/backups/{dir}.',
];
