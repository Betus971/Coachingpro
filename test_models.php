<?php

require 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
$key = 'REDACTED';
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $key;

$response = $client->request('GET', $url);
$data = $response->toArray(false);
foreach ($data['models'] as $m) {
    if (str_contains($m['name'], 'flash')) {
        echo $m['name'] . "\n";
    }
}
