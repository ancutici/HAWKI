<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>HAWKI</title>
	<link rel="shortcut icon" type="image/x-icon" href="<?= $TEMPLATE_PATH ?>/img/favicon.png" media="screen" />
	<link rel="icon" type="image/x-icon" href="<?= $TEMPLATE_PATH ?>/img/favicon.png" media="screen" />
	<link rel="stylesheet" href="<?= $TEMPLATE_PATH ?>/css/login.css">
	<link rel="stylesheet" href="<?= $TEMPLATE_PATH ?>/css/hohenheim.css">
<link rel="stylesheet" href="https://sibforms.com/forms/end-form/build/sib-styles.css">
</head>

<body>
	<!-- partial:index.partial.html -->
	<div class="wrapper">
		<aside>
			<div class="loginPanel">
					<img id="Universität Hohenheim Logo" src="<?= $TEMPLATE_PATH ?>/img/universitaet-hohenheim-logo3.svg" alt="">
			<h3>Willkommen zurück!</h3>
			<?php if ($AUTHENTICATION == 'OIDC'): ?>
				<form action='oidc_login.php' class='column' method='post'>
					<button>Login</button>
				</form>
			<?php endif; ?>

			<?php if ($AUTHENTICATION == 'LDAP'): ?>
				<?php if (isset($LOGIN_ERROR)): ?>
					<p><?= $LOGIN_ERROR ?></p>
				<?php endif; ?>
				<form action="<?= $SCRIPT ?>" class="column" method="post">
					<label for="login">Benutzername</label>
					<input type="text" name="login" id="login">
					<label for="password">Kennwort</label>
					<input type="password" name="password" id="password">
					<button>Login</button>
				</form>
			<?php endif; ?>

			<?php if ($AUTHENTICATION == 'LTI'): ?>
				<p>Automatisches Login nur über LTI möglich</p>
				<?php if (isset($LOGIN_ERROR)): ?>
					<p><?= $LOGIN_ERROR ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ($AUTHENTICATION == 'TEST'): ?>
				<?php if (isset($LOGIN_ERROR)): ?>
					<p><?= $LOGIN_ERROR ?></p>
				<?php endif; ?>
				<form action="<?= $SCRIPT ?>" class="column" method="post">
					<label for="login">Benutzername</label>
					<input type="text" name="login" id="login">
					<label for="password">Kennwort</label>
					<input type="password" name="password" id="password">
					<button>Login</button>
				</form>
			<?php endif; ?>

			<?php
				if (!isset($AUTHENTICATION) || !in_array($AUTHENTICATION, ['LDAP', 'OIDC', 'LTI', 'TEST'])):
					echo 'No authentication method defined';
					die;
				endif;
			?>
</div>	
		</aside>

		<main>
			<div class="infoPanel">
					<div class="textPanel">
						<div class="page">
							<h1 class="headerLine">GPTalk Hohenheim</h1>
							<p><small>Mit <i>GPTalk</i> haben Studierende und Lehrende der Universität die Möglichkeit, ChatGPT zu verwenden, ohne einen separaten Account anlegen zu müssen. Die Anmeldung erfolgt einfach über die Hohenheimer Benutzerkennung. Es werden keine nutzerbezogenen Daten gespeichert.
</small></p>

<p>GPTalk bietet die Möglichkeit, Künstliche Intelligenz in der Lehre zu integrieren. Es schafft zudem einen Begegnungsraum, in dem neue Arbeitsweisen entstehen können und eine hochschulinterne Diskussion über den Einsatz von K.I. angeregt wird. Das Interface ist in drei Bereiche unterteilt:</p>


							<h3>Konversation</h3>
							<p>Ein Chatbereich wie bei ChatGPT, für einen schnellen Einstieg in jede beliebige Aufgabe.<br>
				</p>

							<h3>Virtuelles Büro</h3>
							<p>Gespräche mit fiktiven Expertinnen und Experten als mentales Modell, um sich in fachfremde Bereiche einzuarbeiten und gezieltere Anfragen an echte Hochschul-Expertinnen und -Experten zu stellen.
				</p>

							<h3>Lernraum</h3>
							<p>Die Lernräume sollen helfen, die verschiedenen Unterstützungsmöglichkeiten zu verstehen und zu lernen, was einen effektiven Prompt ausmacht.<br><br>
				</p>
				</br>
    
	<p><a href="https://www.uni-hohenheim.de/einsatz-von-generativer-ki-in-pruefungen#c560357" target="_blank">Empfehlungen des Senats für den Einsatz künstlicher Intelligenz (KI) in Prüfungen</a>.</p>
	

<p>GPTalk Hohenheim ist ein Service des <a href="https://kim.uni-hohenheim.de" target="_blank">KIM </a> der Universität Hohenheim</p>    
</p>				
						</div>
			</div>
				</div>

		</main>
		</div>
	</body>

</html>
