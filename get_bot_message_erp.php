<?php
date_default_timezone_set('Asia/Dhaka');
session_start();
require_once 'dbconfig/config.php';
require_once 'ERPIntegration.php';

// Get user message
$user_input = isset($_POST['txt']) ? trim($_POST['txt']) : '';
$action = isset($_POST['action']) ? $_POST['action'] : 'message'; // 'message' or 'set_erp_creds'

if (empty($user_input) && $action === 'message') {
    echo "Please type a message";
    exit;
}

// Get current user ID from session
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // Try to get from username
    if (isset($_SESSION['username'])) {
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$_SESSION['username']]);
        $result = $stmt->fetch();
        $user_id = $result['id'] ?? 1;
    } else {
        $user_id = 1; // Demo user
    }
}

// Handle ERP credential setting
if ($action === 'set_erp_creds') {
    $erp_username = $_POST['erp_username'] ?? '';
    $erp_password = $_POST['erp_password'] ?? '';
    
    if (empty($erp_username) || empty($erp_password)) {
        echo json_encode(['success' => false, 'error' => 'ERP credentials required']);
        exit;
    }
    
    $erp = new ERPIntegration($db, $user_id);
    $erp->setCredentials($erp_username, $erp_password);
    $erp->saveCredentials($erp_username, $erp_password);
    
    echo json_encode(['success' => true, 'message' => 'ERP credentials saved. You can now ask about marks and attendance.']);
    exit;
}

// Check if user wants to access ERP data
$erp_relevant = stripos($user_input, 'marks') !== false || 
                  stripos($user_input, 'grades') !== false || 
                  stripos($user_input, 'attendance') !== false ||
                  stripos($user_input, 'results') !== false ||
                  stripos($user_input, 'score') !== false;

$erp_data = '';
if ($erp_relevant) {
    $erp = new ERPIntegration($db, $user_id);
    
    // Try to use stored credentials
    if (!$erp->getStoredCredentials()) {
        $erp_data = "\n\n[Note: User has not set up ERP login. Ask them to provide their ERP credentials.]";
    } else {
        // Fetch data based on query
        if (stripos($user_input, 'attendance') !== false) {
            $attendance_result = $erp->getAttendance();
            if ($attendance_result['success']) {
                $erp_data = "\n\n[Retrieved from ERP - Student Attendance Data: " . json_encode($attendance_result['attendance']) . "]";
            }
        } elseif (stripos($user_input, 'marks') !== false || stripos($user_input, 'grades') !== false) {
            $marks_result = $erp->getMarks();
            if ($marks_result['success']) {
                $erp_data = "\n\n[Retrieved from ERP - Student Marks Data: " . json_encode($marks_result['marks']) . "]";
            }
        }
    }
}

// Get conversation history (last 10 messages)
$sql = "SELECT message, type FROM message ORDER BY id DESC LIMIT 10";
$stmt = $db->prepare($sql);
$stmt->execute();
$messages_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Reverse to get chronological order
$messages_raw = array_reverse($messages_raw);

// Build conversation history
$conversation_history = [];
foreach ($messages_raw as $msg) {
    $role = ($msg['type'] === 'user') ? 'user' : 'assistant';
    $conversation_history[] = [
        'role' => $role,
        'content' => $msg['message']
    ];
}

// Add current user message with ERP data if applicable
$final_user_message = $user_input . $erp_data;
$conversation_history[] = [
    'role' => 'user',
    'content' => $final_user_message
];

// System prompt for ERP guidance
$system_prompt = "You are a helpful assistant for KL University's ERP (Enterprise Resource Planning) system. You help students and staff navigate the portal and answer questions about their academic details.

Key ERP features you can help with:
- Login: Use registration credentials at https://newerp.kluniversity.in/
- Academic modules: Grades, attendance, course materials
- Personal info: Profile and contact updates
- Fee management: Track payments and receipts
- Exam registration: Register through the portal
- Timetable & Results: View schedules and exam results
- Leave management: Apply for leave
- Notifications: Receive university updates
- Support: Contact help desk for issues

When users ask about marks or attendance:
- If you have ERP data, analyze and present it clearly
- Highlight trends (e.g., attendance percentage, grades in different subjects)
- Provide helpful suggestions (e.g., if attendance is low, remind them of importance)
- If ERP data is not available, ask them to provide their ERP login credentials

If a user hasn't provided ERP credentials yet and wants to check marks/attendance:
- Ask them to provide their ERP username and password
- Assure them it's for this session only and will help you access their real data
- Never store credentials permanently unless user confirms

Always be helpful, professional, and guide users effectively.";

// Call Groq API
$ch = curl_init(GROQ_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . GROQ_API_KEY
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$request_data = [
    'model' => GROQ_MODEL,
    'messages' => array_merge(
        [['role' => 'system', 'content' => $system_prompt]],
        $conversation_history
    ),
    'temperature' => 0.7,
    'max_tokens' => 500
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));

$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($curl_error) {
    $content = "Connection error: $curl_error";
    error_log("Groq cURL Error: $curl_error");
} elseif ($http_code !== 200) {
    $content = "API Error (HTTP $http_code). Please try again.";
    error_log("Groq API Error: HTTP $http_code - $response");
} else {
    $data = json_decode($response, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = trim($data['choices'][0]['message']['content']);
    } else {
        $content = "Sorry, couldn't generate a response. Please try again.";
        error_log("Groq Response Error: " . json_encode($data));
    }
}

// Save user message to database
$added_on = date('Y-m-d h:i:s');
$stmt = $db->prepare("INSERT INTO message(message,added_on,type) VALUES(?,?,'user')");
$stmt->execute(array($user_input, $added_on));

// Save bot response to database
$added_on = date('Y-m-d h:i:s');
$stmt = $db->prepare("INSERT INTO message(message,added_on,type) VALUES(?,?,'bot')");
$stmt->execute(array($content, $added_on));

// Return response
echo $content;
?>
