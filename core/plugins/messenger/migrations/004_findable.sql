-- Before 0.1.4 "Let others find me" was off by default, so older accounts stayed hidden without choosing it. Everyone becomes findable once; the box can be switched off again.
UPDATE {p}msg_users SET discoverable = 1;
