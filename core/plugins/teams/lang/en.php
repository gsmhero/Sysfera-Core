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
    'add_member' => 'Add', 'member_added' => 'Member added.', 'member_removed' => 'Member removed.', 'confirm_remove' => 'Remove this person from the team?',
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
