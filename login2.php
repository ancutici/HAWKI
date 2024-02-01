<?php
require './vendor/autoload.php';

session_start();
  
if (isset($_SESSION['username'])) {
  header("Location: interface.php");
  exit;
}

$templates = new League\Plates\Engine('./template');

echo $templates->render('loginTemplate');



