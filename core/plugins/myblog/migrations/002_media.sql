-- Gallery pictures (JSON list of file names) and video links (JSON list) of a post
ALTER TABLE {p}myblog_posts ADD COLUMN images TEXT NULL, ADD COLUMN videos TEXT NULL;
