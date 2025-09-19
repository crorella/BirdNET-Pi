<?php

define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__ . '/scripts/common.php');

$config = get_config();
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

if (preg_match('#^/api/v1/probes/([^/]+)/recordings$#', $requestUri, $matches)) {
  if ($requestMethod !== 'POST') {
    sendResponse405();
  }
  handle_probe_upload($matches[1], $config);
  exit;
}

if ($requestMethod !== 'GET') {
  sendResponse405();
}

if (preg_match('#^/api/v1/image/(\S+)$#', $requestUri, $matches)) {
  if ($config["IMAGE_PROVIDER"] === 'FLICKR') {
    $image_provider = new Flickr();
  } else {
    $image_provider = new Wikipedia();
  }
  $sci_name = urldecode($matches[1]);
  $result = $image_provider->get_image($sci_name);

  if ($result == false) {
    http_response_code(404);
    echo "Error 404! No image found!";
  } else {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
      "status" => "success",
      "message" => "successfully image data from database",
      "data" => $result
    ]);
  }
} else {
  http_response_code(404);
  echo "Error 404! No route found!";
}

function sendResponse405() {
  http_response_code(405);
  echo json_encode(["message" => "Method Not Allowed"]);
  exit;
}

function send_json($status, $payload) {
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($payload);
  exit;
}

function require_probe_auth($config) {
  if (is_authenticated()) {
    return;
  }

  $expected = isset($config['PROBE_API_TOKEN']) ? trim($config['PROBE_API_TOKEN']) : '';
  $provided = isset($_SERVER['HTTP_X_PROBE_TOKEN']) ? trim($_SERVER['HTTP_X_PROBE_TOKEN']) : '';
  if ($expected !== '' && hash_equals($expected, $provided)) {
    return;
  }

  header('WWW-Authenticate: Basic realm="BirdNET-Pi"');
  send_json(401, ["status" => "unauthorized", "message" => "Authentication required"]);
}

function sanitize_probe_id($probeId) {
  $normalized = strtolower($probeId);
  return preg_replace('/[^a-z0-9_-]/', '_', $normalized);
}

function parse_timestamp($input) {
  if ($input === null || $input === '') {
    return time();
  }
  if (ctype_digit((string) $input)) {
    return (int) $input;
  }
  $parsed = strtotime($input);
  if ($parsed === false) {
    return false;
  }
  return $parsed;
}

function handle_probe_upload($probeIdRaw, $config) {
  require_probe_auth($config);

  if (!isset($_FILES['recording'])) {
    send_json(400, ["status" => "error", "message" => "Missing 'recording' file upload"]);
  }

  $upload = $_FILES['recording'];
  if (!isset($upload['tmp_name']) || $upload['error'] !== UPLOAD_ERR_OK) {
    send_json(400, ["status" => "error", "message" => "Invalid upload", "code" => $upload['error']]);
  }

  $timestampInput = isset($_POST['timestamp']) ? $_POST['timestamp'] : null;
  $timestamp = parse_timestamp($timestampInput);
  if ($timestamp === false) {
    send_json(400, ["status" => "error", "message" => "Invalid timestamp format"]);
  }

  $probeTag = sanitize_probe_id($probeIdRaw);
  if ($probeTag === '') {
    send_json(400, ["status" => "error", "message" => "Probe identifier cannot be empty"]);
  }

  $rtspPart = '';
  if (isset($_POST['rtsp_id']) && preg_match('/^[0-9]+$/', $_POST['rtsp_id'])) {
    $rtspPart = 'RTSP_' . $_POST['rtsp_id'] . '-';
  }

  $targetDir = rtrim($config['RECS_DIR'], '/') . '/StreamData';
  if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
      send_json(500, ["status" => "error", "message" => "Unable to create target directory"]);
    }
  }

  $datePart = date('Y-m-d', $timestamp);
  $timePart = date('H:i:s', $timestamp);
  if ($rtspPart !== '') {
    $baseName = sprintf('%s-birdnet-%s-%s%s', $datePart, $probeTag, $rtspPart, $timePart);
  } else {
    $baseName = sprintf('%s-birdnet-%s-%s', $datePart, $probeTag, $timePart);
  }
  $targetPath = $targetDir . '/' . $baseName . '.wav';
  $suffix = 1;
  while (file_exists($targetPath)) {
    $targetPath = $targetDir . '/' . $baseName . '-' . $suffix . '.wav';
    $suffix += 1;
  }

  if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
    send_json(500, ["status" => "error", "message" => "Failed to persist uploaded file"]);
  }
  chmod($targetPath, 0644);

  send_json(201, [
    "status" => "created",
    "message" => "Recording accepted",
    "data" => [
      "probe" => $probeTag,
      "path" => basename($targetPath),
      "timestamp" => $timestamp
    ]
  ]);
}
