<?php

$url = 'https://api-sandbox.ratehawk.com';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CAINFO => 'C:/certificates/cacert.pem',
]);

curl_exec($ch);

echo 'HTTP Status: ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo 'cURL Error Number: ' . curl_errno($ch) . PHP_EOL;
echo 'cURL Error: ' . curl_error($ch) . PHP_EOL;

curl_close($ch);
