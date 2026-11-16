<?php
return [
    'menu' => 'Teams', 'title' => 'Teams', 'open' => 'Open', 'save' => 'Save', 'create' => 'Create', 'delete' => 'Delete', 'cancel' => 'Cancel', 'apply' => 'Apply', 'search' => 'Search…', 'remove' => 'Remove', 'upload' => 'Upload',
    'saved' => 'Saved.', 'confirm_delete' => 'Delete? This cannot be undone.', 'today' => 'Today',
    'chat' => 'Chat', 'board' => 'Tasks', 'calendar' => 'Calendar', 'files' => 'Files', 'members' => 'Members',
    /* errors */
    'e_denied' => 'Your role in this team does not allow this.', 'e_not_found' => 'Not found.', 'e_join_first' => 'Join the team first.', 'e_name' => 'Enter a name.', 'e_title' => 'Enter a title.',
    'e_empty' => 'Write a message or attach a file.', 'e_long' => 'The message is too long (max 4000 characters).', 'e_rate' => 'Too fast — wait a minute.', 'e_file' => 'This file is not allowed (type or size over 5 MB).', 'e_storage' => 'Cannot store the file.',
    'e_last_owner' => 'A team needs at least one owner.', 'e_last_channel' => 'The last channel cannot be archived.', 'e_col_used' => 'A column with tasks (or the last column) cannot be deleted.', 'e_event' => 'Enter a title and a valid date; the end cannot be before the start.',
    /* teams */
    'new_team' => 'New team', 'team_created' => 'Team created.', 'my_teams' => 'My teams', 'discover' => 'Open teams you can join', 'no_teams' => 'You are not in a team yet.', 'join' => 'Join', 'joined' => 'You joined the team.', 'join_hint' => 'Join this team to read and write.',
    'left' => 'You left the team.', 'leave' => 'Leave team', 'confirm_leave' => 'Leave this team?', 'n_members' => '{n} members', 'f_name' => 'Name', 'f_descr' => 'Description', 'f_privacy' => 'Access', 'f_color' => 'Colour',
    'private' => 'Private (by invitation)', 'open_team' => 'Open (anyone can join)', 'team_settings' => 'Team settings', 'archive_team' => 'Archive team', 'confirm_archive_team' => 'Archive the team? It disappears from the lists.', 'archived' => 'Team archived.',
    'role_owner' => 'Owner', 'role_admin' => 'Admin', 'role_member' => 'Member', 'roles_h' => 'Roles', 'role_owner_d' => 'everything, including roles and archiving the team', 'role_admin_d' => 'channels, people, moderation, team settings', 'role_member_d' => 'chat, tasks, events, files',
    'inv_h' => 'Add people', 'inv_existing' => 'A user of this site', 'inv_by_mail' => 'Invite by e-mail (up to 5 addresses)', 'inv_send' => 'Send invitation', 'inv_role' => 'Role', 'inv_link_hint' => 'Or make a link and send it yourself (valid 14 days, one person).', 'inv_make_link' => 'Create invite link', 'inv_pending' => 'Pending invitations', 'inv_a_link' => 'Invite link', 'inv_until' => 'until {d}', 'inv_revoke' => 'Revoke', 'inv_confirm_revoke' => 'Revoke this invitation?', 'inv_revoked' => 'Invitation revoked.', 'inv_link_made' => 'Invite link created: copy it below.', 'inv_sent' => 'Invitations sent: {n}.', 'inv_subject' => 'Invitation to the team {t}', 'inv_body' => "{n} invites you to the team \"{t}\".\n\nOpen the link, sign in (or register) and press Join:\n{l}\n\nThe link works for 14 days.", 'inv_e_to' => 'Enter 1–5 valid e-mail addresses.', 'inv_e_rate' => 'Too many invitations sent. Try again later.', 'inv_e_many' => 'Too many pending invitations. Revoke some first.', 'inv_e_mail_off' => 'E-mail is not set up on this site. The invitation was created: copy its link below and send it yourself.', 'inv_e_mail' => 'The e-mail was not sent ({e}). The invitation was created: copy its link below.', 'inv_title' => 'Team invitation', 'inv_text' => '{n} invites you to join as {r}.', 'inv_join' => 'Join the team', 'inv_joined' => 'You joined {t}.', 'inv_e_gone' => 'This invitation is not valid any more.', 'add_member' => 'Add', 'member_added' => 'Member added.', 'member_removed' => 'Member removed.', 'confirm_remove' => 'Remove this person from the team?',
    /* chat */
    'channels' => 'Channels', 'new_channel' => 'New channel', 'channel_name' => 'channel-name', 'topic' => 'Topic', 'private_channel' => 'Private channel', 'general' => 'general', 'general_topic' => 'Everything about the team', 'archive_channel' => 'Archive channel',
    'confirm_archive' => 'Archive this channel?', 'no_channels' => 'No channels yet.', 'write_ph' => 'Message #{c} — Enter sends, Shift+Enter adds a line, @ mentions', 'send' => 'Send', 'attach' => 'File', 'read_only' => 'You can only read here.',
    'reply' => 'Reply', 'edit' => 'Edit', 'pin' => 'Pin', 'unpin' => 'Unpin', 'edited' => 'edited', 'deleted' => 'message deleted', 'new_messages' => 'New messages', 'pinned' => 'Pinned', 'replying' => 'Replying to', 'too_big' => 'File is larger than 5 MB.',
    'sending_failed' => 'Could not send. Try again.', 'download' => 'Download',
    /* board */
    'add_task' => 'Add task', 'edit_task' => 'Edit task', 'f_title' => 'Title', 'f_column' => 'Column', 'f_assignee' => 'Assigned to', 'f_due' => 'Due date', 'f_prio' => 'Priority', 'prio_low' => 'Low', 'prio_mid' => 'Normal', 'prio_high' => 'High',
    'only_mine' => 'only mine', 'manage_columns' => 'Manage columns', 'new_column' => 'New column', 'col_done' => 'counts as done', 'st_order' => 'Order',
    'col_todo' => 'To do', 'col_doing' => 'In progress', 'col_done_name' => 'Done',
    /* calendar */
    'add_event' => 'Add event', 'edit_event' => 'Edit event', 'f_start' => 'Starts', 'f_end' => 'Ends', 'f_time' => 'Time', 'upcoming' => 'Upcoming', 'no_events' => 'No upcoming events.',
    'd_mon' => 'Mon', 'd_tue' => 'Tue', 'd_wed' => 'Wed', 'd_thu' => 'Thu', 'd_fri' => 'Fri', 'd_sat' => 'Sat', 'd_sun' => 'Sun',
    'month_1' => 'January', 'month_2' => 'February', 'month_3' => 'March', 'month_4' => 'April', 'month_5' => 'May', 'month_6' => 'June', 'month_7' => 'July', 'month_8' => 'August', 'month_9' => 'September', 'month_10' => 'October', 'month_11' => 'November', 'month_12' => 'December',
    /* files */
    'uploaded' => 'File uploaded.', 'no_files' => 'No files yet.', 'f_file' => 'File', 'f_from' => 'From', 'f_where' => 'Where', 'f_size' => 'Size', 'file_rules' => 'Up to {n} MB; images, PDF, Office, text, zip.',
    /* widget */
    'w_unread' => 'Unread messages: {n}', 'w_tasks' => 'my open tasks: {n}',
];
