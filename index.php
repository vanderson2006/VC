<?php
require __DIR__ . '/vendor/autoload.php'; // Composer autoload

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$channelSecret = $_ENV['CHANNEL_SECRET'];
$channelToken  = $_ENV['CHANNEL_ACCESS_TOKEN'];
$openrouterKey = $_ENV['OPENROUTER_API_KEY'];

$input = file_get_contents('php://input');
$events = json_decode($input, true);

$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
if (!hash_equals(base64_encode(hash_hmac('sha256', $input, $channelSecret, true)), $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

foreach ($events['events'] as $event) {
    if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
        continue;
    }

    $userMessage = $event['message']['text'];
    $replyToken = $event['replyToken'];

    $aiReply = callOpenRouter($userMessage, $openrouterKey);
    replyToLine($replyToken, $aiReply, $channelToken);
}

function replyToLine($token, $message, $channelToken) {
    $url = 'https://api.line.me/v2/bot/message/reply';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $channelToken
    ];
    $postData = json_encode([
        'replyToken' => $token,
        'messages' => [['type' => 'text', 'text' => $message]]
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $postData
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function callOpenRouter($userMessage, $apiKey) {
    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: your-app-id',
        'X-Title: LineBot-PHP'
    ];
    $data = [
        'model' => 'openai/gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => '你是LINE機器人助手'],
            ['role' => 'user', 'content' => $userMessage]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($result, true);
    return $response['choices'][0]['message']['content'] ?? '抱歉，我暫時無法回答你。';
}
?>
