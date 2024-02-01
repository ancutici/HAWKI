<?php
require_once './vendor/autoload.php';
require_once 'auth.php';

session_start();

if (isset($_SESSION['username'])) {
  header('Location: interface.php');
  exit;
}

$templateData = [];

if (file_exists('.env')) {
  $env = parse_ini_file('.env');
  $templateData = $env;
}

$templateData['SCRIPT'] = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');

if ($templateData['AUTHENTICATION'] == 'LDAP' && isset($_POST['login']) && isset($_POST['password'])) {
  $login = filter_var($_POST['login'], FILTER_SANITIZE_STRING);
  $password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);

  $loginResult = authLDAP($login, $password);
  if ($loginResult['authStatus'] == 'ok') {
    $_SESSION['username'] = substr($loginResult['firstname'], 0, 1) . substr($loginResult['surname'], 0, 1);
    header('Location: interface.php');
    exit;
  } else {
    $templateData['LOGIN_ERROR'] = $loginResult['authStatus'];
  }
}

if ($templateData['AUTHENTICATION'] == 'TEST' && isset($_POST['login']) && isset($_POST['password'])) {
  $login = filter_var($_POST['login'], FILTER_SANITIZE_STRING);
  $password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);

  if ($login == 'tester' && $password == 'superlangespasswort123') {
    $_SESSION['username'] = 'T';
    header('Location: interface.php');
    exit;
  } else {
    $templateData['LOGIN_ERROR'] = 'Benutzername oder Passwort falsch';
  }
}

if ($templateData['AUTHENTICATION'] == 'LTI' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oauth_consumer_key'])) {
  $post_oauth_consumer_key = filter_var($_POST['oauth_consumer_key'], FILTER_SANITIZE_STRING);

  $loginResult = authLTI($templateData['LTI_CONSUMER_KEY'], $templateData['LTI_CONSUMER_SECRET'], $post_oauth_consumer_key);
  if ($loginResult['authStatus'] == 'ok') {
    $_SESSION['username'] = substr($loginResult['firstname'], 0, 1) . substr($loginResult['surname'], 0, 1);
    $_SESSION['initials'] = $_SESSION['username'];
    header('Location: interface.php');
    exit;
  } else {
    $templateData['LOGIN_ERROR'] = $loginResult['authStatus'];
  }
}

$templates = new League\Plates\Engine('./template/' . $templateData['TEMPLATE']);

echo $templates->render('loginTemplate', $templateData);
