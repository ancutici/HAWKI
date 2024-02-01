<?php
require_once './vendor/autoload.php';

session_start();

if (!isset($_SESSION['username'])) {
	header("Location: login.php");
	exit;
}

$templateData = [];

if (file_exists('.env')) {
  $env = parse_ini_file('.env');
  $templateData = $env;
}

$templateData['USERNAME'] = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');

$templates = new League\Plates\Engine('./template');

echo $templates->render('interfaceTemplate', $templateData);