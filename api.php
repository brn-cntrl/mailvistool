<?php
/**
 * REST API Endpoints
 * Handles all data requests from the frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'emails':
            // Get all emails with optional filters
            $filter = $_GET['filter'] ?? 'all'; // all, unread, urgent, actioned
            
            $sql = 'SELECT * FROM emails WHERE 1=1';
            $params = [];
            
            if ($filter === 'inbox') {
                $sql .= ' AND is_sent = 0';
            } elseif ($filter === 'urgent') {
                $sql .= ' AND priority = ? AND is_sent = 0';
                $params[] = 'high';
            } elseif ($filter === 'sent') {
                $sql .= ' AND is_sent = 1';
            }
            
            $sql .= ' ORDER BY date_received DESC';
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $emails = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $emails]);
            break;
            
        case 'email':
            // Get single email detail
            $id = $_GET['id'] ?? 0;
            
            $stmt = $db->prepare('SELECT * FROM emails WHERE id = ?');
            $stmt->execute([$id]);
            $email = $stmt->fetch();
            
            if ($email) {
                // Get actions history for this email
                $stmt = $db->prepare('SELECT * FROM actions WHERE email_id = ? ORDER BY created_at DESC');
                $stmt->execute([$id]);
                $actions = $stmt->fetchAll();
                
                $email['actions'] = $actions;
                
                echo json_encode(['success' => true, 'data' => $email]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Email not found']);
            }
            break;
            
        case 'stats':
            // Get summary statistics
            $stats = [];
            
            // Total emails
            $stats['total'] = $db->query('SELECT COUNT(*) FROM emails')->fetchColumn();
            
            // Unread
            $stats['unread'] = $db->query('SELECT COUNT(*) FROM emails WHERE is_read = 0')->fetchColumn();
            
            // Urgent
            $stats['urgent'] = $db->query('SELECT COUNT(*) FROM emails WHERE priority = "high" AND is_sent = 0')->fetchColumn();
            
            // Sent
            $stats['sent'] = $db->query('SELECT COUNT(*) FROM emails WHERE is_sent = 1')->fetchColumn();
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'chart_by_recipient':
            // Emails grouped by intended recipient
            $stmt = $db->query('
                SELECT 
                    COALESCE(intended_recipient, "Unassigned") as recipient,
                    COUNT(*) as count
                FROM emails
                GROUP BY intended_recipient
                ORDER BY count DESC
            ');
            
            $data = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'chart_by_date':
            // Emails grouped by date (last 7 days)
            $stmt = $db->query('
                SELECT 
                    DATE(date_received) as date,
                    COUNT(*) as count
                FROM emails
                WHERE date_received >= date("now", "-7 days")
                GROUP BY DATE(date_received)
                ORDER BY date ASC
            ');
            
            $data = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'rules':
            // Get all routing rules
            $stmt = $db->query('SELECT * FROM routing_rules ORDER BY priority DESC');
            $rules = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $rules]);
            break;
            
        case 'sync':
            // Trigger IMAP sync (we'll build this next)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // This will call the sync script
                require_once 'sync.php';
                echo json_encode(['success' => true, 'message' => 'Sync completed']);
            } else {
                echo json_encode(['success' => false, 'error' => 'POST required']);
            }
            break;
        
        case 'assign_recipient':
            // Assign recipient to an email
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = $_POST['id'] ?? 0;
                $recipient = $_POST['recipient'] ?? '';

                $stmt = $db->prepare('UPDATE emails SET assigned_recipient = ? WHERE id = ?');
                $stmt->execute([$recipient, $id]);

                echo json_encode(['success' => true, 'message' => 'Recipient assigned']);
            } else {
                echo json_encode(['success' => false, 'error' => 'POST required']);
            }
            break;
            
        case 'toggle_select':
            // Toggle email selection checkbox
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = $_POST['id'] ?? 0;
                $selected = $_POST['selected'] ?? 0;
                
                $stmt = $db->prepare('UPDATE emails SET is_selected = ? WHERE id = ?');
                $stmt->execute([$selected, $id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'POST required']);
            }
            break;
            
        case 'get_recipients':
            $stmt = $db->query('SELECT id, name, email FROM recipients WHERE is_active = 1 ORDER BY name ASC');
            $recipients = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $recipients]);
            break;
            
        case 'add_recipient':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                
                if (empty($name) || empty($email)) {
                    echo json_encode(['success' => false, 'error' => 'Name and email required']);
                    break;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                    break;
                }
                
                try {
                    $stmt = $db->prepare('INSERT INTO recipients (name, email) VALUES (?, ?)');
                    $stmt->execute([$name, $email]);
                    echo json_encode(['success' => true, 'message' => 'Recipient added']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Email already exists']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'POST required']);
            }
            break;
            
        case 'update_recipient':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = $_POST['id'] ?? 0;
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                
                if (empty($name) || empty($email)) {
                    echo json_encode(['success' => false, 'error' => 'Name and email required']);
                    break;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                    break;
                }
                
                try {
                    $stmt = $db->prepare('UPDATE recipients SET name = ?, email = ?, updated_at = datetime("now") WHERE id = ?');
                    $stmt->execute([$name, $email, $id]);
                    echo json_encode(['success' => true, 'message' => 'Recipient updated']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Email already exists']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'POST required']);
            }
            break;
            
        case 'delete_recipient':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = $_POST['id'] ?? 0;
                $stmt = $db->prepare('UPDATE recipients SET is_active = 0 WHERE id = ?');
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Recipient deleted']);
            } else {
                echo json_encode(['success' => false, 'error' => 'POST required']);
            }
            break;
            
        case 'send_selected':
            // Send all selected emails
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once 'send_emails.php';
                
                try {
                    $mailer = new EmailForwarder();
                    $result = $mailer->sendSelectedEmails();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "Sent {$result['sent']} emails",
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'POST required']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>