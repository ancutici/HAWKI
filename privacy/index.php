<?php
	define('ROOT_PATH', dirname(__DIR__));
    define('PRIVATE_PATH', ROOT_PATH . '/private');
    define('BOOTSTRAP_PATH', PRIVATE_PATH . '/bootstrap.php');

    session_start();

	require_once BOOTSTRAP_PATH;
	require_once LIBRARY_PATH . 'language_controller.php';

	if(!isset($_SESSION['translation'])){
		setLanguage();
	}
	$translation = $_SESSION['translation'];

	// Load the environment variables
	if (file_exists(ENV_FILE_PATH)){
		$env = parse_ini_file(ENV_FILE_PATH);		
	}
?>
<!DOCTYPE html>
<html lang="de">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Datenschutzerklärung</title>
	<style>
		@font-face {
			font-family: 'Fira Sans';
			src: url('/font/FiraSans-Regular.ttf') format('truetype');
			font-weight: 400;
			font-style: normal;
		}

		@font-face {
			font-family: 'Fira Sans';
			src: url('/font/FiraSans-Italic.ttf') format('truetype');
			font-weight: 400;
			font-style: italic;
		}

		@font-face {
			font-family: 'Fira Sans';
			src: url('/font/FiraSans-Bold.ttf') format('truetype');
			font-weight: bold;
			font-style: normal;
		}

		@font-face {
			font-family: 'Fira Sans';
			src: url('/font/FiraSans-BoldItalic.ttf') format('truetype');
			font-weight: bold;
			font-style: italic;
		}

		body {
			max-width: 80ch;
			margin: auto;
			padding: 10vmin;
			line-height: 1.75;
			font-family: 'Fira Sans', sans-serif;
			font-size: 1rem;
		}
	</style>
</head>

<body>
	<div class="privacy">
        <?php echo $translation["privacy"]; ?>
	</div>
</body>

</html>