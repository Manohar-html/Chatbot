<?php
date_default_timezone_set('Asia/Dhaka');
require_once 'dbconfig/config.php';

// Get user message
$user_input = isset($_POST['txt']) ? trim($_POST['txt']) : '';

if (empty($user_input)) {
    echo "Please type a message";
    exit;
}

// Get conversation history (last 10 messages for context)
$sql = "SELECT message, type FROM message ORDER BY id DESC LIMIT 10";
$stmt = $db->prepare($sql);
$stmt->execute();
$messages_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Reverse to get chronological order
$messages_raw = array_reverse($messages_raw);

// Build conversation history for AI context
$conversation_history = [];
foreach ($messages_raw as $msg) {
    $role = ($msg['type'] === 'user') ? 'user' : 'assistant';
    $conversation_history[] = [
        'role' => $role,
        'content' => $msg['message']
    ];
}

// Add current user message
$conversation_history[] = [
    'role' => 'user',
    'content' => $user_input
];

// System prompt for ERP guidance
$system_prompt = "You are a helpful assistant for KL University's ERP (Enterprise Resource Planning) system. You help students and staff navigate the portal and answer questions about features. 

Key features of KL University ERP:
- Login: Use your registration credentials at https://newerp.kluniversity.in/
- Academic modules: View grades, attendance, course materials
- Personal info: Update profile and contact details
- Fee management: Track fee payments and receipts
- Exam registration: Register for exams through the portal
- Time table: View class schedule and exam dates
- Results: Check exam results once published
- Leave management: Apply for leave through the system
- Notifications: Receive important updates via the portal
- Support: Contact help desk for technical issues

Always provide helpful guidance while being clear about what users can do in the ERP system. If users ask about login issues, suggest contacting the help desk at the university.";

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


<!--
<!DOCTYPE html>
<html>
<head>
	<title></title>
</head>
<style>

	<link href="style.css" rel="stylesheet">
</style>
<a href="#"><small><input name="invalid"  type="button" id="admin_btn" value="Invalid?"></small></a>

<body>

</body>
</html>-->
