-- ====================================================================
-- MIGRATION 003: Hàng đợi thông báo SQLite (Notification Outbox)
-- Issue #13: Triển khai hàng đợi thông báo SQLite + Dashboard + Email dự phòng
-- ====================================================================

CREATE TABLE IF NOT EXISTS notification_outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_key TEXT NOT NULL UNIQUE,
    event_type TEXT NOT NULL,
    order_id INTEGER NOT NULL,
    channel TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempt_count INTEGER NOT NULL DEFAULT 0,
    next_retry_at TEXT,
    last_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TEXT,
    payload_json TEXT
);

CREATE INDEX IF NOT EXISTS idx_notification_pending
ON notification_outbox(status, next_retry_at);

CREATE INDEX IF NOT EXISTS idx_notification_order
ON notification_outbox(order_id);
