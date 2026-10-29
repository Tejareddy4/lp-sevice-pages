<?php

// Same-origin relay for the quote wizard. The browser posts here and the server
// forwards the lead to the Zoho Flow webhook, so a Content-Security-Policy,
// an ad blocker or a lost cross-origin request can't silently drop it, and the
// page learns whether Zoho actually accepted it.

$config = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function lead_respond(int $status, array $body): void
{
  http_response_code($status);
  echo json_encode($body);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  lead_respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

// Only accept posts from our own pages.
$originHeader = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($originHeader !== '') {
  $originHost = strtolower((string) parse_url($originHeader, PHP_URL_HOST));
  $selfHost = strtolower((string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
  if ($originHost === '' || $originHost !== $selfHost) {
    lead_respond(403, ['ok' => false, 'error' => 'origin']);
  }
}

// Fields the Zoho Flow expects, with their maximum length.
$allowed = [
  'zf_referrer_name' => 255,
  'zf_redirect_url' => 255,
  'zc_gad' => 255,
  'From' => 80,
  'To' => 80,
  'First_Name' => 80,
  'Last_Name' => 80,
  'Product_Type' => 80,
  'Packet_Weight' => 40,
  'Phone' => 14,
];

$lead = [];
foreach ($allowed as $field => $max) {
  $value = isset($_POST[$field]) && is_string($_POST[$field]) ? trim($_POST[$field]) : '';
  $lead[$field] = function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

$invalid = [];
if (!preg_match('/^[6-9]\d{9}$/', $lead['Phone'])) $invalid[] = 'Phone';
if ($lead['Last_Name'] === '') $invalid[] = 'Last_Name';
if ($lead['Product_Type'] === '') $invalid[] = 'Product_Type';
if ($lead['To'] === '') $invalid[] = 'To';
if ($invalid) {
  lead_respond(422, ['ok' => false, 'error' => 'invalid', 'fields' => $invalid]);
}

$webhook = $config['lead_webhook'];
$status = 0;
$error = '';

if (function_exists('curl_init')) {
  $ch = curl_init($webhook);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $lead, // array => multipart/form-data, same as the browser form
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
  ]);
  curl_exec($ch);
  $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);
} else {
  $context = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
    'content' => http_build_query($lead),
    'timeout' => 12,
    'ignore_errors' => true,
  ]]);
  $result = @file_get_contents($webhook, false, $context);
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m)) {
    $status = (int) $m[1];
  }
  if ($result === false && $status === 0) $error = 'request failed';
}

if ($status >= 200 && $status < 300) {
  lead_respond(200, ['ok' => true]);
}

error_log('lead.php: Zoho webhook failed (HTTP ' . $status . ($error !== '' ? ', ' . $error : '') . ')');
lead_respond(502, ['ok' => false, 'error' => 'upstream']);
