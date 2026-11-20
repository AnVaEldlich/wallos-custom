<?php

// Assisted cancellation: when a user presses "Cancel", Diamon opens the
// service's cancel page and records a pending attempt. The attempt is resolved
// by the browser extension (it saw the confirmation page) or by the user
// answering "Cancelled / Not cancelled". previous_cancellation_date keeps the
// value the subscription had, so resuming it puts that date back.

$db->exec("CREATE TABLE IF NOT EXISTS cancellation_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    subscription_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    source TEXT DEFAULT NULL,
    previous_cancellation_date TEXT DEFAULT NULL,
    restored INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    resolved_at INTEGER DEFAULT NULL
)");

$db->exec("CREATE INDEX IF NOT EXISTS idx_cancellation_attempts_user_status ON cancellation_attempts (user_id, status)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_cancellation_attempts_subscription ON cancellation_attempts (subscription_id)");

?>
