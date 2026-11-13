-- An administrator can ask to delete an account; it is deleted only when its owner agrees.
ALTER TABLE {p}users ADD COLUMN delete_requested_at DATETIME NULL;
