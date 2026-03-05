<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Dashboard - MailOps</title>
    <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>MailOps</h1>
            <button id="syncBtn" class="btn btn-primary">🔄 Sync Emails</button>
        </header>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Emails</div>
                <div class="stat-value" id="stat-total">-</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unread</div>
                <div class="stat-value" id="stat-unread">-</div>
            </div>
            <div class="stat-card urgent">
                <div class="stat-label">Urgent</div>
                <div class="stat-value" id="stat-urgent">-</div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Sent</div>
                <div class="stat-value" id="stat-sent">-</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-container">
                <h3>Emails by Recipient</h3>
                <canvas id="recipientChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Emails Over Time (Last 7 Days)</h3>
                <canvas id="timeChart"></canvas>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <button id="sendSelectedBtn" class="btn btn-success" style="font-size: 16px; padding: 12px 24px;">
                Send Selected Emails
            </button>
            <span id="selectedCount" style="margin-left: 15px; color: #718096;"></span>
        </div>

        <div class="filters">
            <button class="filter-btn active" data-filter="inbox">📥 Inbox</button>
            <button class="filter-btn" data-filter="urgent">⭐ Urgent</button>
            <button class="filter-btn" data-filter="sent">📤 Sent</button>
        </div>

        <!-- Email Table -->
        <div class="table-container">
            <table class="email-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Intended Recipient</th>
                        <th>Assign To</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="emailTableBody">
                    <tr>
                        <td colspan="7" class="loading">Loading emails...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Email Detail Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="emailDetail">
                <!-- Email details will be loaded here -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/dashboard.js"></script>
</body>
</html>