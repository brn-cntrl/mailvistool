# MailVisTool

A PHP-based email queue dashboard for managing, routing, and forwarding inbound emails. It connects to an IMAP server, stores emails in a local SQLite database, automatically routes them to team members based on subject keywords, and lets you forward them via SMTP from a browser UI.

## Features

- **Email Queue Dashboard** — Tabular view of all inbound emails with status, priority, sender, subject, and assigned recipient
- **IMAP Sync** — Fetches new emails from any IMAP server on demand; deduplicates by Message-ID
- **Keyword Routing** — Automatically suggests a recipient based on configurable subject-line keyword rules (e.g. "project proposal" → David, "urgent" → Admin)
- **Manual Assignment** — Override auto-routing from the dashboard via a per-row dropdown
- **Email Forwarding** — Select one or more emails and forward them via SMTP; original message is wrapped in a standard forward block
- **Stats Overview** — At-a-glance counts for total, unread, urgent (unactioned high-priority), and resolved emails
- **Charts** — Bar chart of emails by intended recipient; line chart of email volume over the last 7 days (Chart.js)
- **Action Tracking** — Mark emails as resolved; all actions (forwarded, resolved) are logged to an `actions` table
- **Auto Database Setup** — `db.php` bootstraps the SQLite schema automatically on first run

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ with PDO / SQLite |
| IMAP | [webklex/php-imap](https://github.com/Webklex/php-imap) ^6.2 |
| SMTP | [phpmailer/phpmailer](https://github.com/PHPMailer/PHPMailer) ^7.0 |
| Frontend | Vanilla HTML / CSS / JavaScript |
| Charts | [Chart.js](https://www.chartjs.org/) (CDN) |
| Test mail server | [GreenMail](https://greenmail-mail-test.github.io/greenmail/) standalone JAR |

## Project Structure

```
MailVisTool/
├── index.php               # Dashboard HTML page
├── api.php                 # REST API consumed by the frontend
├── sync.php                # EmailSync class — fetches from IMAP, writes to DB
├── send_emails.php         # EmailForwarder class — forwards via SMTP
├── db.php                  # Database singleton (auto-creates schema on first use)
├── config.php              # IMAP / SMTP / recipients / routing config
├── schema.sql              # SQLite schema (emails, routing_rules, actions)
├── setup_database.php      # One-time DB setup script (optional, db.php handles it)
├── migrate_add_forwarding.php  # Migration: adds forwarding columns to emails table
├── test_db.php             # Diagnostic: verifies DB connection and table list
├── test_greenmail.php      # Test: sends an email via SMTP and reads it back via IMAP
├── inspect_metadata.php    # Test: sends several emails and prints all IMAP metadata
├── full_metadata_test.php  # Same as inspect_metadata.php
├── check_sent_emails.php   # CLI utility: lists emails forwarded from the dashboard
├── composer.json
├── greenmail-standalone-2.1.8.jar
├── assets/
│   ├── dashboard.js        # All frontend logic
│   └── dashboard.css       # Dashboard styles
└── database/
    └── mailvis.db          # SQLite database (auto-created)
```

## Requirements

- PHP 8.1+ with extensions: `pdo`, `pdo_sqlite`, `imap` (or sockets)
- [Composer](https://getcomposer.org/)
- Java 11+ (only needed to run the bundled GreenMail test server)
- An IMAP-accessible mailbox (real or GreenMail)

## Installation

### 1. Install PHP dependencies

```bash
composer install
```

### 2. Configure

Edit [config.php](config.php) to point at your IMAP and SMTP server:

```php
'imap' => [
    'host'          => 'mail.example.com',
    'port'          => 993,
    'username'      => 'inbox@example.com',
    'password'      => 'your-password',
    'encryption'    => 'ssl',
    'validate_cert' => true,
],
'smtp' => [
    'host'       => 'smtp.example.com',
    'port'       => 587,
    'username'   => 'sender@example.com',
    'password'   => 'your-password',
    'encryption' => 'tls',
    'from_email' => 'dashboard@example.com',
    'from_name'  => 'Email Dashboard',
],
'recipients' => [
    ['email' => 'alice@example.com', 'name' => 'Alice'],
    ['email' => 'bob@example.com',   'name' => 'Bob'],
],
'routing_rules' => [
    'project proposal' => ['email' => 'alice@example.com', 'name' => 'Alice', 'priority' => 10],
    'urgent'           => ['email' => 'bob@example.com',   'name' => 'Bob',   'priority' => 20],
],
```

### 3. Set up the database

The database is created automatically when the app first runs. To set it up manually (and seed default routing rules from `config.php`):

```bash
php setup_database.php
```

### 4. Serve the app

Any PHP-capable web server works. For local development:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

## Using GreenMail for Local Testing

The bundled `greenmail-standalone-2.1.8.jar` runs a local IMAP/SMTP server — no real email account needed.

```bash
# Start GreenMail (SMTP on 3025, IMAP on 3143)
java -jar greenmail-standalone-2.1.8.jar \
  --greenmail.setup.test.all \
  --greenmail.users=test1:test1@localhost
```

The default `config.php` is already configured for GreenMail. Send test emails and verify the IMAP connection:

```bash
php test_greenmail.php
```

Send a batch of test emails with varied metadata:

```bash
php inspect_metadata.php
```

## Database Schema

### `emails`

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER PK | |
| `message_id` | TEXT UNIQUE | IMAP Message-ID header |
| `uid` | INTEGER | IMAP UID |
| `sender_name` / `sender_email` | TEXT | Parsed from headers |
| `subject` | TEXT | |
| `body_preview` | TEXT | First 500 characters of body |
| `priority` | TEXT | `high` / `normal` / `low` |
| `is_read` / `is_answered` / `is_flagged` | INTEGER | IMAP flags |
| `has_attachments` / `attachment_count` | INTEGER | |
| `intended_recipient` | TEXT | Auto-matched by routing rule |
| `assigned_recipient` | TEXT | Manually assigned via UI |
| `is_actioned` / `actioned_at` | INTEGER / DATETIME | Resolved in dashboard |
| `is_sent` / `sent_at` | INTEGER / DATETIME | Forwarded via SMTP |
| `is_selected` | INTEGER | Checkbox state in UI |

### `routing_rules`

Maps subject keywords to recipient email addresses with a numeric priority (higher = checked first).

### `actions`

Audit log of every `resolved` or `forwarded` action taken on an email.

## API Reference

All endpoints are served from [api.php](api.php) via query string (`?action=...`).

| Action | Method | Description |
|---|---|---|
| `emails` | GET | List emails; optional `filter=all\|unread\|urgent\|actioned` |
| `email` | GET | Single email with action history; requires `id` |
| `stats` | GET | Counts: total, unread, urgent, actioned |
| `chart_by_recipient` | GET | Email counts grouped by recipient |
| `chart_by_date` | GET | Email counts for last 7 days |
| `rules` | GET | List all routing rules |
| `sync` | POST | Trigger IMAP sync |
| `mark_actioned` | POST | Mark email resolved; params: `id`, `note` |
| `assign_recipient` | POST | Override routing; params: `id`, `recipient` |
| `toggle_select` | POST | Set checkbox state; params: `id`, `selected` |
| `get_recipients` | GET | Available recipients from config |
| `send_selected` | POST | Forward all selected emails via SMTP |

## Utility Scripts

```bash
# Verify DB connection and table list
php test_db.php

# List all emails forwarded from the dashboard
php check_sent_emails.php

# Run any pending database migrations
php migrate_add_forwarding.php
```
