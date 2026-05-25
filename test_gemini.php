<?php

require 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
$key = 'REDACTED';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $key;

$response = $client->request('POST', $url, [
    'json' => [
        'contents' => [
            ['parts' => [['text' => 'Test']]]
        ]
    ]
]);

echo $response->getStatusCode() . "\n";
print_r($response->toArray(false));
