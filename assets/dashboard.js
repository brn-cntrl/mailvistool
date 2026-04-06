let currentFilter = 'inbox';
let recipientChart = null;
let timeChart = null;
let availableRecipients = [];

document.addEventListener('DOMContentLoaded', function() {
    loadRecipients();
    loadStats();
    loadEmails('inbox');
    loadCharts();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('syncBtn').addEventListener('click', syncEmails);
    document.getElementById('sendSelectedBtn').addEventListener('click', sendSelectedEmails);
    document.getElementById('manageRecipientsBtn').addEventListener('click', openRecipientsModal);
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.email-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
            toggleEmailSelection(cb.dataset.id, this.checked);
        });
    });
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            currentFilter = this.dataset.filter;
            loadEmails(currentFilter);
        });
    });
    
    document.querySelector('.close').addEventListener('click', closeModal);
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('detailModal');
        if (e.target === modal) {
            closeModal();
        }
    });
}

async function loadStats() {
    try {
        const response = await fetch('api.php?action=stats');
        const result = await response.json();
        
        if (result.success) {
            const stats = result.data;
            document.getElementById('stat-total').textContent = stats.total;
            document.getElementById('stat-unread').textContent = stats.unread;
            document.getElementById('stat-urgent').textContent = stats.urgent;
            document.getElementById('stat-sent').textContent = stats.sent;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadEmails(filter = 'inbox') {
    if (availableRecipients.length === 0) {
        await loadRecipients();
    }
    const tbody = document.getElementById('emailTableBody');
    tbody.innerHTML = '<tr><td colspan="9" class="loading">Loading emails...</td></tr>';
    
    try {
        const response = await fetch(`api.php?action=emails&filter=${filter}`);
        const result = await response.json();
        
        if (result.success) {
            const emails = result.data;
            
            if (emails.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="loading">No emails found</td></tr>';
                return;
            }
            
            tbody.innerHTML = emails.map(email => {
                const assignedRecipientIds = email.assigned_recipient_ids || '';
                const isSent = email.is_sent == 1;
                
                return `
                <tr>
                    <td onclick="event.stopPropagation();">
                        <input type="checkbox" class="email-checkbox" data-id="${email.id}" ${email.is_selected ? 'checked' : ''} onchange="toggleEmailSelection(${email.id}, this.checked)">
                    </td>
                    <td onclick="showEmailDetail(${email.id})">
                        <span class="status-badge ${isSent ? 'status-sent' : (email.is_read ? 'status-read' : 'status-unread')}">
                            ${isSent ? '📤 Sent' : (email.is_read ? 'Read' : 'New')}
                        </span>
                    </td>
                    <td onclick="showEmailDetail(${email.id})" class="${email.priority === 'high' ? 'priority-high' : 'priority-normal'}">
                        ${email.priority === 'high' ? '🔴 High' : '⚪ Normal'}
                    </td>
                    <td onclick="showEmailDetail(${email.id})">
                        <strong>${escapeHtml(email.sender_name || 'Unknown')}</strong><br>
                        <small>${escapeHtml(email.sender_email || '')}</small>
                    </td>
                    <td onclick="showEmailDetail(${email.id})">${escapeHtml(email.subject)}</td>
                    <td id="recipient-cell-${email.id}" onclick="showEmailDetail(${email.id})">${escapeHtml(email.assigned_recipient_names || 'Unassigned')}</td>
                    <td onclick="event.stopPropagation();">
                        ${createRecipientDropdown(email.id, assignedRecipientIds)}
                    </td>
                    <td onclick="showEmailDetail(${email.id})">${formatDate(email.date_received)}</td>
                    <td onclick="event.stopPropagation();">
                        ${isSent ? '📤' : ''}
                    </td>
                </tr>
            `}).join('');
            
            document.querySelectorAll('.recipient-select').forEach(el => {
                const emailId = el.id.replace('recipient-select-', '');
                new TomSelect(el, {
                    plugins: ['remove_button'],
                    placeholder: 'Assign recipients...',
                    onChange: function(values) {
                        assignRecipient(emailId, values);
                    }
                });
            });
            
            updateSelectedCount();
        }
    } catch (error) {
        console.error('Error loading emails:', error);
        tbody.innerHTML = '<tr><td colspan="7" class="loading">Error loading emails</td></tr>';
    }
}

async function loadCharts() {
    try {
        const recipientResponse = await fetch('api.php?action=chart_by_recipient');
        const recipientResult = await recipientResponse.json();
        
        if (recipientResult.success) {
            const data = recipientResult.data;
            const ctx = document.getElementById('recipientChart').getContext('2d');
            
            if (recipientChart) recipientChart.destroy();
            
            recipientChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.recipient),
                    datasets: [{
                        label: 'Emails',
                        data: data.map(d => d.count),
                        backgroundColor: '#3182ce',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
        
        const timeResponse = await fetch('api.php?action=chart_by_date');
        const timeResult = await timeResponse.json();
        
        if (timeResult.success) {
            const data = timeResult.data;
            const ctx = document.getElementById('timeChart').getContext('2d');
            
            if (timeChart) timeChart.destroy();
            
            timeChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.date),
                    datasets: [{
                        label: 'Emails',
                        data: data.map(d => d.count),
                        borderColor: '#38a169',
                        backgroundColor: 'rgba(56, 161, 105, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading charts:', error);
    }
}

async function showEmailDetail(emailId) {
    const modal = document.getElementById('detailModal');
    const detailDiv = document.getElementById('emailDetail');
    
    detailDiv.innerHTML = '<p class="loading">Loading email details...</p>';
    modal.style.display = 'block';
    
    try {
        const response = await fetch(`api.php?action=email&id=${emailId}`);
        const result = await response.json();
        
        if (result.success) {
            const email = result.data;
            
            detailDiv.innerHTML = `
                <div class="detail-section">
                    <h3>From</h3>
                    <p><strong>${escapeHtml(email.sender_name || 'Unknown')}</strong> &lt;${escapeHtml(email.sender_email)}&gt;</p>
                </div>
                
                <div class="detail-section">
                    <h3>Subject</h3>
                    <p>${escapeHtml(email.subject)}</p>
                </div>
                
                <div class="detail-section">
                    <h3>Date</h3>
                    <p>${formatDate(email.date_received)}</p>
                </div>
                
                <div class="detail-section">
                    <h3>Intended Recipient</h3>
                    <p>${escapeHtml(email.intended_recipient || 'Unassigned')}</p>
                </div>
                
                <div class="detail-section">
                    <h3>Priority</h3>
                    <p>${email.priority === 'high' ? '🔴 High' : '⚪ Normal'}</p>
                </div>
                
                <div class="detail-section">
                    <h3>Message</h3>
                    <div class="email-body">${escapeHtml(email.body_preview || 'No preview available')}</div>
                </div>
                
                ${email.is_sent ? '<p style="color: #2c5aa0; font-weight: 600;">📤 Sent on ' + formatDate(email.sent_at) + '</p>' : ''}
            `;
        }
    } catch (error) {
        console.error('Error loading email detail:', error);
        detailDiv.innerHTML = '<p style="color: red;">Error loading email details</p>';
    }
}


function closeModal() {
    document.getElementById('detailModal').style.display = 'none';
}

function createRecipientDropdown(emailId, assignedRecipientIds) {
    const selectedIds = assignedRecipientIds ? assignedRecipientIds.split(',') : [];

    let options = '';
    availableRecipients.forEach(recipient => {
        const selected = selectedIds.includes(String(recipient.id)) ? 'selected' : '';
        options += `<option value="${recipient.id}" ${selected}>${recipient.name} &lt;${recipient.email}&gt;</option>`;
    });

    return `<select id="recipient-select-${emailId}" class="recipient-select" multiple>${options}</select>`;
}

async function syncEmails() {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true;
    btn.textContent = '🔄 Syncing...';
    
    try {
        const response = await fetch('api.php?action=sync', { method: 'POST' });
        const result = await response.json();
        
        if (result.success) {
            await loadStats();
            await loadEmails(currentFilter);
            await loadCharts();
            alert('✓ Emails synced successfully!');
        } else {
            alert('Error syncing emails: ' + result.error);
        }
    } catch (error) {
        console.error('Error syncing:', error);
        alert('Error syncing emails');
    } finally {
        btn.disabled = false;
        btn.textContent = '🔄 Sync Emails';
    }
}

// async function markActioned(emailId) {
//     if (!confirm('Mark this email as resolved?')) return;
    
//     try {
//         const formData = new FormData();
//         formData.append('id', emailId);
//         formData.append('note', 'Marked as resolved from dashboard');
        
//         const response = await fetch('api.php?action=mark_actioned', {
//             method: 'POST',
//             body: formData
//         });
        
//         const result = await response.json();
        
//         if (result.success) {
//             await loadStats();
//             await loadEmails(currentFilter);
//             await loadCharts();
//         } else {
//             alert('Error: ' + result.error);
//         }
//     } catch (error) {
//         console.error('Error marking email:', error);
//         alert('Error marking email as actioned');
//     }
// }

async function loadRecipients() {
    try {
        const response = await fetch('api.php?action=get_recipients');
        const result = await response.json();
        
        if (result.success) {
            availableRecipients = result.data;
        }
    } catch (error) {
        console.error('Error loading recipients:', error);
    }
}

async function toggleEmailSelection(emailId, selected) {
    try {
        const formData = new FormData();
        formData.append('id', emailId);
        formData.append('selected', selected ? 1 : 0);
        
        await fetch('api.php?action=toggle_select', {
            method: 'POST',
            body: formData
        });
        
        updateSelectedCount();
    } catch (error) {
        console.error('Error toggling selection:', error);
    }
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.email-checkbox:checked').length;
    const countEl = document.getElementById('selectedCount');
    
    if (checked > 0) {
        countEl.textContent = `${checked} email${checked > 1 ? 's' : ''} selected`;
    } else {
        countEl.textContent = '';
    }
}


async function assignRecipient(emailId, recipientIds) {
    try {
        const formData = new FormData();
        formData.append('id', emailId);
        recipientIds.forEach(id => formData.append('recipient_ids[]', id));

        const response = await fetch('api.php?action=assign_recipient', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error assigning recipient:', error);
        alert('Error assigning recipient');
    }
}

async function sendSelectedEmails() {
    const checked = document.querySelectorAll('.email-checkbox:checked').length;
    
    if (checked === 0) {
        alert('Please select at least one email to send');
        return;
    }
    
    if (!confirm(`Send ${checked} selected email${checked > 1 ? 's' : ''}?`)) {
        return;
    }
    
    const btn = document.getElementById('sendSelectedBtn');
    btn.disabled = true;
    btn.textContent = 'Sending...';
    
    try {
        const response = await fetch('api.php?action=send_selected', { method: 'POST' });
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            await loadStats();
            await loadEmails(currentFilter);
            await loadCharts();
            updateSelectedCount();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error sending emails:', error);
        alert('Error sending emails');
    } finally {
        btn.disabled = false;
        btn.textContent = '📤 Send Selected Emails';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function openRecipientsModal() {
    document.getElementById('recipientsModal').style.display = 'block';
    loadRecipientsTable();
    
    setTimeout(function() {
        const addBtn = document.getElementById('addRecipientBtn');
        if (addBtn) {
            addBtn.onclick = function() {
                openRecipientForm(null, '', '');
            };
        }
    }, 100);
}

function closeRecipientsModal() {
    document.getElementById('recipientsModal').style.display = 'none';
}

async function loadRecipientsTable() {
    const tbody = document.getElementById('recipientsTableBody');
    tbody.innerHTML = '<tr><td colspan="3" class="loading">Loading...</td></tr>';
    
    try {
        const response = await fetch('api.php?action=get_recipients');
        const result = await response.json();
        
        if (result.success) {
            const recipients = result.data;
            
            if (recipients.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="loading">No recipients configured</td></tr>';
                return;
            }
            
            tbody.innerHTML = recipients.map(r => `
                <tr>
                    <td>${escapeHtml(r.name)}</td>
                    <td>${escapeHtml(r.email)}</td>
                    <td>
                        <button class="btn btn-small" onclick="editRecipient(${r.id}, '${escapeHtml(r.name)}', '${escapeHtml(r.email)}')" style="background: #3182ce; color: white; margin-right: 5px;">Edit</button>
                        <button class="btn btn-small" onclick="deleteRecipient(${r.id}, '${escapeHtml(r.name)}')" style="background: #e53e3e; color: white;">Delete</button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading recipients:', error);
        tbody.innerHTML = '<tr><td colspan="3" class="loading">Error loading recipients</td></tr>';
    }
}

function openRecipientForm(id = null, name = '', email = '') {
    document.getElementById('recipientFormModal').style.display = 'block';
    document.getElementById('recipientFormTitle').textContent = id ? 'Edit Recipient' : 'Add Recipient';
    document.getElementById('recipientId').value = id ? id : '';
    document.getElementById('recipientName').value = name || '';
    document.getElementById('recipientEmail').value = email || '';
}

function closeRecipientForm() {
    document.getElementById('recipientFormModal').style.display = 'none';
    document.getElementById('recipientForm').reset();
}

function editRecipient(id, name, email) {
    openRecipientForm(id, name, email);
}

async function saveRecipient(event) {
    event.preventDefault();
    
    const id = document.getElementById('recipientId').value;
    const name = document.getElementById('recipientName').value;
    const email = document.getElementById('recipientEmail').value;
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('email', email);
    
    if (id) {
        formData.append('id', id);
    }
    
    const action = id ? 'update_recipient' : 'add_recipient';
    
    try {
        const response = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeRecipientForm();
            loadRecipientsTable();
            await loadRecipients();
            await loadEmails(currentFilter);
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error saving recipient:', error);
        alert('Error saving recipient');
    }
}

async function deleteRecipient(id, name) {
    if (!confirm(`Delete recipient "${name}"?`)) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    try {
        const response = await fetch('api.php?action=delete_recipient', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            loadRecipientsTable();
            await loadRecipients();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error deleting recipient:', error);
        alert('Error deleting recipient');
    }
}