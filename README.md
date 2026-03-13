# MailOps

A PHP-based email queue dashboard for managing, routing, and forwarding inbound emails. It connects to an IMAP server, stores emails in a local SQLite database, automatically routes them to team members based on subject keywords, and lets you forward them via SMTP from a browser UI.

## Features

- **Email Queue Dashboard** — Tabular view of all inbound emails with status, priority, sender, subject, and assigned recipient
- **IMAP Sync** — Fetches new emails from any IMAP server on demand; deduplicates by Message-ID
- **Keyword Routing** — Automatically suggests a recipient based on configurable subject-line keyword rules (e.g. "project proposal" → David, "urgent" → Admin)
- **Manual Assignment** — Override auto-routing from the dashboard via a per-row dropdown
- **Email Forwarding** — Select one or more emails and forward them via SMTP; original message is wrapped in a standard forward block
- **Stats Overview** — At-a-glance counts for total, unread, urgent (unsent high-priority), and sent emails
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
| Test mail server | [GreenMail](https://greenmail-mail-test.github.io/greenmail/) standalone JAR (not included) |

## Project Structure

```
MailVisTool/
├── index.php               # Dashboard HTML page
├── api.php                 # REST API consumed by the frontend
├── sync.php                # EmailSync class — fetches from IMAP, writes to DB
├── send_emails.php         # EmailForwarder class — forwards via SMTP
├── db.php                  # Database singleton (auto-creates schema on first use)
├── config.php              # Loads env vars; sets IMAP / SMTP / routing config
├── .env                    # Your local credentials (not committed)
├── .env.example            # Template — copy to .env and fill in values
├── schema.sql              # SQLite schema (emails, routing_rules, actions)
├── setup_database.php      # One-time DB setup script (optional, db.php handles it)
├── migrate_add_forwarding.php  # Migration: adds forwarding columns to emails table
├── test_db.php             # Diagnostic: verifies DB connection and table list
├── test_greenmail.php      # Test: sends an email via SMTP and reads it back via IMAP
├── inspect_metadata.php    # Test: sends several emails and prints all IMAP metadata
├── full_metadata_test.php  # Same as inspect_metadata.php
├── check_sent_emails.php   # CLI utility: lists emails forwarded from the dashboard
├── composer.json
├── assets/
│   ├── dashboard.js        # All frontend logic
│   └── dashboard.css       # Dashboard styles
└── database/
    └── mailvis.db          # SQLite database (auto-created)
```

## Installation

### 1. Install PHP

The app requires PHP 8.1 or newer with the `pdo` and `pdo_sqlite` extensions. These are included in most standard PHP distributions.

**macOS (Homebrew)**

```bash
brew install php
```

**Ubuntu / Debian**

```bash
sudo apt update
sudo apt install php php-cli php-sqlite3
```

**Windows**

Download a pre-built binary from [windows.php.net](https://windows.php.net/download/). The thread-safe x64 build includes PDO and SQLite by default. Add the install directory to your `PATH`.

Verify your installation:

```bash
php -v
php -m | grep -E "pdo|sqlite"
```

### 2. Install Composer

[Composer](https://getcomposer.org/) is the PHP dependency manager used to install the IMAP and SMTP libraries.

**macOS (Homebrew)**

```bash
brew install composer
```

**Linux / macOS (direct)**

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

**Windows**

Download and run the [Composer installer](https://getcomposer.org/Composer-Setup.exe).

### 3. Clone the repo and install dependencies

```bash
git clone <repo-url> MailVisTool
cd MailVisTool
composer install
```

### 4. Configure

Copy the example environment file and fill in your IMAP and SMTP credentials:

```bash
cp .env.example .env
```

Then open `.env` and set your values:

```env
IMAP_HOST=mail.example.com
IMAP_PORT=993
IMAP_USERNAME=inbox@example.com
IMAP_PASSWORD=your-password
IMAP_ENCRYPTION=ssl
IMAP_VALIDATE_CERT=true

SMTP_HOST=smtp.example.com
SMTP_PORT=465
SMTP_USERNAME=sender@example.com
SMTP_PASSWORD=your-password
SMTP_ENCRYPTION=ssl
SMTP_FROM_EMAIL=dashboard@example.com
SMTP_FROM_NAME=Email Dashboard
```

Credentials are loaded at runtime via [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) — the `.env` file is read by [config.php](config.php) and should never be committed to version control.

Routing rules can be added directly in [config.php](config.php) under the `routing_rules` key. Recipients are managed through the dashboard UI (stored in the `recipients` DB table) rather than in `config.php`.

### 5. Set up the database

The SQLite database is created automatically on the first request. To set it up manually and seed routing rules from `config.php`:

```bash
php setup_database.php
```

### 6. Serve the app

Any PHP-capable web server works. For local development:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

## Using GreenMail for Local Testing

[GreenMail](https://greenmail-mail-test.github.io/greenmail/) is a self-contained Java mail server that provides IMAP and SMTP without needing a real email account.

### Prerequisites

Java 11 or newer is required. Check with `java -version`. If not installed:

- **macOS:** `brew install openjdk`
- **Ubuntu / Debian:** `sudo apt install default-jre`
- **Windows:** download from [adoptium.net](https://adoptium.net/)

### Download GreenMail

```bash
curl -L -o greenmail-standalone.jar \
  https://github.com/greenmail-mail-test/greenmail/releases/download/greenmail-2.1.3/greenmail-standalone-2.1.3.jar
```

Or download the latest standalone JAR from the [GreenMail releases page](https://github.com/greenmail-mail-test/greenmail/releases).

### Start GreenMail

```bash
# SMTP on port 3025, IMAP on port 3143
java -jar greenmail-standalone.jar \
  --greenmail.setup.test.all \
  --greenmail.users=test1:test1@localhost
```

To use GreenMail, set the following in your `.env`:

```env
IMAP_HOST=localhost
IMAP_PORT=3143
IMAP_USERNAME=test1@localhost
IMAP_PASSWORD=test1
IMAP_ENCRYPTION=

SMTP_HOST=localhost
SMTP_PORT=3025
SMTP_USERNAME=test1@localhost
SMTP_PASSWORD=test1
SMTP_ENCRYPTION=
```

### Verify the connection

Send a test email via SMTP and read it back via IMAP:

```bash
php test_greenmail.php
```

Send a batch of test emails with varied priorities and metadata:

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
| `body_preview` | TEXT | First 200 characters of body |
| `size` | INTEGER | Message size in bytes |
| `date_received` | DATETIME | Date/time from IMAP |
| `priority` | TEXT | `high` / `normal` / `low` |
| `is_read` / `is_answered` / `is_flagged` | INTEGER | IMAP flags |
| `has_attachments` / `attachment_count` | INTEGER | |
| `intended_recipient` | TEXT | Auto-matched by routing rule |
| `matched_rule_id` | INTEGER | FK to `routing_rules.id` |
| `assigned_recipient` | TEXT | Manually assigned via UI |
| `is_actioned` / `actioned_at` | INTEGER / DATETIME | Resolved in dashboard |
| `is_sent` / `sent_at` | INTEGER / DATETIME | Forwarded via SMTP |
| `is_selected` | INTEGER | Checkbox state in UI |
| `fetched_at` | DATETIME | When the record was inserted |

### `routing_rules`

Maps subject keywords to recipient email addresses with a numeric priority (higher = checked first).

### `actions`

Audit log of every `noted`, `forwarded`, or `resolved` action taken on an email.

### `recipients`

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER PK | |
| `name` | TEXT | Display name |
| `email` | TEXT UNIQUE | Email address |
| `is_active` | INTEGER | Soft-delete flag (1 = active) |
| `created_at` / `updated_at` | DATETIME | |

Recipients are stored in the database and managed via the dashboard UI rather than in `config.php`.

## API Reference

All endpoints are served from [api.php](api.php) via query string (`?action=...`).

| Action | Method | Description |
|---|---|---|
| `emails` | GET | List emails; optional `filter=all\|inbox\|urgent\|sent` |
| `email` | GET | Single email with action history; requires `id` |
| `stats` | GET | Counts: total, unread, urgent, sent |
| `chart_by_recipient` | GET | Email counts grouped by recipient |
| `chart_by_date` | GET | Email counts for last 7 days |
| `rules` | GET | List all routing rules |
| `sync` | POST | Trigger IMAP sync |
| `assign_recipient` | POST | Override routing; params: `id`, `recipient` |
| `toggle_select` | POST | Set checkbox state; params: `id`, `selected` |
| `get_recipients` | GET | Active recipients from the `recipients` table |
| `add_recipient` | POST | Add a recipient; params: `name`, `email` |
| `update_recipient` | POST | Update a recipient; params: `id`, `name`, `email` |
| `delete_recipient` | POST | Soft-delete a recipient; params: `id` |
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
