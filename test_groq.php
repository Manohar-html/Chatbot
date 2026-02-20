<?php
require_once 'dbconfig/config.php';

echo "Testing Groq API Connection...\n";
echo "Model: " . GROQ_MODEL . "\n";
echo "API Key: " . substr(GROQ_API_KEY, 0, 10) . "...\n\n";

$ch = curl_init(GROQ_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . GROQ_API_KEY
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$request_data = [
    'model' => GROQ_MODEL,
    'messages' => [
        ['role' => 'user', 'content' => 'say hello']
    ],
    'temperature' => 0.7,
    'max_tokens' => 100
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

echo "HTTP Code: " . $http_code . "\n";

if ($curl_error) {
    echo "cURL Error: " . $curl_error . "\n";
} else {
    $data = json_decode($response, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        echo "✅ SUCCESS!\n";
        echo "Bot Response: " . $data['choices'][0]['message']['content'] . "\n";
    } else {
        echo "❌ Response Error:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
}
?>
