<?php
return [
    'menu' => 'CRM', 'deals' => 'Deals', 'contacts' => 'Clients', 'tasks' => 'Tasks', 'reports' => 'Reports', 'import' => 'Import', 'settings' => 'CRM settings',
    'save' => 'Save', 'delete' => 'Delete', 'apply' => 'Apply', 'search' => 'Search…', 'none' => 'Nothing here yet.', 'yes' => 'Yes', 'system' => 'System', 'from' => 'From', 'to' => 'To',
    'saved' => 'Saved.', 'deleted' => 'Deleted.', 'confirm_delete' => 'Delete? This cannot be undone.', 'created' => 'Created', 'closed' => 'Closed',
    /* errors */
    'e_denied' => 'Your CRM profile does not allow this.', 'e_not_found' => 'Not found.', 'e_title' => 'Enter a title.', 'e_name' => 'Enter a name.', 'e_email' => 'Enter a valid e-mail.',
    'e_field' => 'Check the fields:', 'e_dupe' => 'A client with this e-mail already exists — saved anyway.', 'e_no_pipeline' => 'There is no pipeline yet — add one in CRM settings.',
    'e_file' => 'Choose a UTF-8 CSV file (max 2 MB) with a header row.', 'e_no_name_col' => 'The first row must have a column called name (or title).',
    'e_pipe_used' => 'A pipeline with deals, or the last pipeline, cannot be deleted.', 'e_stage_used' => 'A stage that has deals cannot be deleted.',
    'e_field_def' => 'Enter a name; list and multi-select fields need options.', 'e_profile_used' => 'This profile is the default or assigned to users.',
    /* deals */
    'new_deal' => 'New deal', 'deal_created' => 'Deal created.', 'details' => 'Details', 'history' => 'History', 'note_ph' => 'Write a note…', 'add_note' => 'Add note',
    'f_title' => 'Title', 'f_amount' => 'Amount', 'f_currency' => 'Currency', 'f_stage' => 'Stage', 'f_close' => 'Expected close', 'f_contact' => 'Client', 'f_contact_hint' => 'Type to find a client or enter a new name',
    'f_owner' => 'Owner', 'f_tags' => 'Tags', 'f_pipeline' => 'Pipeline', 'all_owners' => 'All owners', 'all_status' => 'All statuses', 'all_types' => 'All types',
    'v_board' => 'Board', 'v_list' => 'List', 'no_task' => 'no task planned', 'next_task' => 'Next task', 'lost_prompt' => 'Why was the deal lost?', 'lost_reason' => 'Lost reason',
    's_open' => 'Open', 's_won' => 'Won', 's_lost' => 'Lost',
    'k_open' => 'Open deals', 'k_forecast' => 'Weighted forecast', 'k_won' => 'Won', 'k_winrate' => 'Win rate', 'k_won_month' => 'Won this month', 'k_won_period' => 'Won in period',
    /* contacts */
    'new_contact' => 'New client', 'contact_created' => 'Client created.', 'f_name' => 'Name', 'f_kind' => 'Type', 'person' => 'Person', 'company' => 'Company', 'f_phone' => 'Phone', 'f_company' => 'Company', 'f_address' => 'Address', 'f_notes' => 'Notes',
    /* tasks */
    'add_task' => 'Add task', 'task_created' => 'Task added.', 'f_type' => 'Type', 'f_due' => 'Due date', 'f_time' => 'Time', 'f_assignee' => 'Assigned to', 'no_tasks' => 'No tasks.', 'complete' => 'Mark done', 'reopen' => 'Reopen',
    'tt_todo' => 'To-do', 'tt_call' => 'Call', 'tt_meeting' => 'Meeting', 'tt_email' => 'E-mail', 'tt_deadline' => 'Deadline',
    'tf_mine' => 'My tasks', 'tf_all' => 'Everyone\'s tasks', 'tf_done' => 'Done', 'g_overdue' => 'Overdue', 'g_today' => 'Today', 'g_upcoming' => 'Upcoming', 'g_none' => 'No date', 'g_done' => 'Done',
    'my_tasks' => 'My tasks', 'all_tasks' => 'All tasks', 'recent_activity' => 'Recent activity', 'recent_deals' => 'Recent deals', 'no_history' => 'No history yet.',
    'tl_note' => 'note', 'tl_stage' => 'stage', 'tl_created' => 'created', 'tl_edit' => 'changed', 'tl_task' => 'task', 'tl_task_done' => 'task done',
    'w_today' => 'Tasks today: {n}', 'w_overdue' => 'overdue: {n}',
    /* reports */
    'r_funnel' => 'Funnel', 'r_months' => 'Won by month', 'r_owners' => 'By manager (period)', 'r_reasons' => 'Lost reasons',
    /* import */
    'imp_hint' => 'First row = column names. Recognised: name (or title), e-mail, phone, company, notes; for deals also amount and contact. Existing e-mails are skipped.', 'imp_what' => 'Import', 'imp_done' => 'Imported: {n}. Skipped: {m}.',
    /* settings */
    's_pipelines' => 'Pipelines (project types)', 's_pipelines_hint' => 'Every pipeline has its own stages and can have its own deal fields. A stage of kind "Won" or "Lost" closes the deal.',
    's_fields' => 'Custom fields', 's_fields_hint' => 'Add your own fields to clients, deals and tasks. Deal fields can belong to one pipeline only.', 's_profiles' => 'User profiles', 's_profiles_hint' => 'A profile says what a user may do and whose data he sees. Administrators always have full rights.', 's_general' => 'General',
    'st_name' => 'Stage', 'st_kind' => 'Kind', 'st_prob' => 'Probability', 'st_order' => 'Order', 'st_add' => 'New stage', 'new_pipeline' => 'New pipeline', 'delete_pipeline' => 'Delete pipeline', 'all_pipelines' => 'All pipelines',
    'ent_contact' => 'Client fields', 'ent_deal' => 'Deal fields', 'ent_task' => 'Task fields', 'f_new' => 'New field name', 'f_options' => 'Options (comma separated)', 'f_required' => 'required',
    'ft_text' => 'Text', 'ft_textarea' => 'Long text', 'ft_number' => 'Number', 'ft_money' => 'Money', 'ft_date' => 'Date', 'ft_select' => 'List', 'ft_multiselect' => 'Multi-select', 'ft_checkbox' => 'Checkbox', 'ft_url' => 'Link', 'ft_email' => 'E-mail', 'ft_phone' => 'Phone',
    'scope_own' => 'Sees own data', 'scope_all' => 'Sees all data', 'perm_create' => 'create', 'perm_edit' => 'edit', 'perm_delete' => 'delete', 'perm_export' => 'export', 'perm_import' => 'import', 'perm_settings' => 'settings', 'perm_reassign' => 'reassign',
    'new_profile' => 'New profile', 'assign_h' => 'Who has which profile', 'default_profile' => 'Profile for users without one', 'admin_full' => 'Administrator — full rights', 'lost_reasons' => 'Lost reasons (one per line)',
    /* examples created on the first visit */
    'seed_sales' => 'Sales', 'seed_projects' => 'Projects', 'st_new' => 'New', 'st_qualified' => 'Qualified', 'st_proposal' => 'Proposal', 'st_negotiation' => 'Negotiation', 'st_won' => 'Won', 'st_lost' => 'Lost',
    'st_planned' => 'Planned', 'st_progress' => 'In progress', 'st_review' => 'Review', 'st_done' => 'Done', 'st_cancelled' => 'Cancelled',
    'prof_manager' => 'Manager', 'prof_team_lead' => 'Team lead', 'prof_observer' => 'Observer',
    'lr_price' => 'Too expensive', 'lr_competitor' => 'Chose a competitor', 'lr_no_budget' => 'No budget', 'lr_no_answer' => 'No answer', 'lr_other' => 'Other',
    'fd_source' => 'Lead source', 'fd_position' => 'Position', 'src_web' => 'Website', 'src_call' => 'Phone call', 'src_referral' => 'Referral', 'src_ads' => 'Advertising',
];
