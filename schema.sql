-- Stores emails fetched from IMAP
CREATE TABLE IF NOT EXISTS emails (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id TEXT UNIQUE,           -- IMAP Message-ID
    uid INTEGER,                      -- IMAP UID
    
    -- Sender info (from IMAP)
    sender_name TEXT,
    sender_email TEXT,
    
    -- Email content (from IMAP)
    subject TEXT,
    body_preview TEXT,                -- First 200 chars
    size INTEGER,                     -- Bytes
    
    -- Date/time (from IMAP)
    date_received DATETIME,
    
    -- Priority (from IMAP)
    priority TEXT DEFAULT 'normal',   -- high, normal, low
    
    -- IMAP flags
    is_read INTEGER DEFAULT 0,        -- \Seen flag
    is_answered INTEGER DEFAULT 0,    -- \Answered flag
    is_flagged INTEGER DEFAULT 0,     -- \Flagged flag
    
    -- Attachments (from IMAP)
    has_attachments INTEGER DEFAULT 0,
    attachment_count INTEGER DEFAULT 0,
    
    -- Routing (calculated by your app)
    intended_recipient TEXT,          -- Matched from routing_rules
    matched_rule_id INTEGER,
    
    -- Action tracking (your app only)
    is_actioned INTEGER DEFAULT 0,    -- Did admin handle it?
    actioned_at DATETIME,
    
    -- Email forwarding (NEW FIELDS)
    assigned_recipient TEXT,          -- Manually assigned recipient (overrides routing rules)
    is_sent INTEGER DEFAULT 0,        -- Has this email been forwarded?
    sent_at DATETIME,                 -- When was it sent?
    is_selected INTEGER DEFAULT 0,    -- Is checkbox checked in UI?
    
    -- Metadata
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY(matched_rule_id) REFERENCES routing_rules(id)
);

-- Routing rules (subject keyword -> recipient)
CREATE TABLE IF NOT EXISTS routing_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    keyword TEXT,                     -- e.g., "project proposal"
    recipient_email TEXT,             -- e.g., "david@anfarch.com"
    recipient_name TEXT,              -- e.g., "David"
    priority INTEGER DEFAULT 0,       -- Higher = checked first
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Admin actions log
CREATE TABLE IF NOT EXISTS actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_id INTEGER,
    action_type TEXT,                 -- "noted", "forwarded", "resolved"
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(email_id) REFERENCES emails(id)
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_emails_date ON emails(date_received);
CREATE INDEX IF NOT EXISTS idx_emails_recipient ON emails(intended_recipient);
CREATE INDEX IF NOT EXISTS idx_emails_read ON emails(is_read);
CREATE INDEX IF NOT EXISTS idx_emails_actioned ON emails(is_actioned);