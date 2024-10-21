<?php

if (!defined('BOOTSTRAP_PATH')) {
	define('BOOTSTRAP_PATH',  '../../bootstrap.php');
}

require_once BOOTSTRAP_PATH;

session_start();
if (!isset($_SESSION['username'])) {
	http_response_code(401);
	exit;
}

// Set appropriate headers for non-streaming mode
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if (file_exists(ENV_FILE_PATH)){
    $env = parse_ini_file(ENV_FILE_PATH);
}

// Replace with your API URL and API key
$apiUrl = isset($env) ? $env['OPENAI_API_URL'] : getenv('OPENAI_API_URL');
$apiKey = isset($env) ? $env['OPENAI_API_KEY'] : getenv('OPENAI_API_KEY');

// Read the request payload from the client
$requestPayload = file_get_contents('php://input');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
	'Authorization: Bearer ' . $apiKey,  // Necessary for OpenAI
	'api-key: ' . $apiKey,               // Necessary for Microsoft Azure AI
	'Content-Type: application/json'
]);

// Execute the request and capture the full response (non-streaming)
$response = curl_exec($ch);

if (curl_errno($ch)) {
	echo json_encode(['error' => curl_error($ch)]);
} else {
	echo $response;  // Send the full response back as JSON
}

curl_close($ch);