<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>HAWKI</title>
	<link rel="shortcut icon" type="image/x-icon" href="<?= $TEMPLATE_PATH ?>/img/favicon.png" media="screen" />
	<link rel="icon" type="image/x-icon" href="<?= $TEMPLATE_PATH ?>/img/favicon.png" media="screen" />
	<link rel="stylesheet" href="<?= $TEMPLATE_PATH ?>/css/login.css">
	<link rel="stylesheet" href="https://sibforms.com/forms/end-form/build/sib-styles.css">
</head>

<body>
		<!-- partial:index.partial.html -->
		<div class="wrapper">
			<aside>
				<div class="loginPanel">
					<img id="HAWK_logo" src="/img/logo.svg" alt="">
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
							<h1 class="headerLine"><span class="accentText">GPT</span> FÜR DIE HOCHSCHULE</h1>
							<p>
								HAWKI ist ein didaktisches Interface für Hochschulen, das auf der API von OpenAI basiert. Für die Nutzerinnen und Nutzer ist es nicht notwendig, einen Account anzulegen, die Hochschul-ID reicht für den Login aus - es werden keine nutzerbezogenen Daten gespeichert.<br>
								Das Angebot wurde im Interaction Design Lab der <a href="https://www.hawk.de/de/hochschule/fakultaeten-und-standorte/fakultaet-gestaltung/werkstaetten/interaction-design-lab" target="_blank"><b>HAWK</b></a>  entwickelt, um allen Hochschulangehörigen die Möglichkeit zu geben, Künstliche Intelligenz in ihre Arbeitsprozesse zu integrieren und einen Begegnungsraum zu haben, damit sich eventuell neue Arbeitsweisen ergeben und eine hochschulinterne Diskussion über den Einsatz von K.I. entstehen kann. Derzeit ist die Oberfläche in drei Bereiche unterteilt:<br>
							</p>

							<h3>Konversation</h3>
							<p>Ein Chatbereich wie bei ChatGPT, für einen schnellen Einstieg in jede beliebige Aufgabe.<br>
							</p>

							<h3>Virtuelles Büro</h3>
							<p>Gespräche mit fiktiven Expertinnen und Experten als mentales Modell, um sich in fachfremde Bereiche einzuarbeiten und gezieltere Anfragen an echte Hochschul-Expertinnen und -Experten zu stellen.
							</p>

							<h3>Lernraum</h3>
							<p>Die Lernräume sollen helfen, die verschiedenen Unterstützungsmöglichkeiten zu verstehen und zu lernen, was einen effektiven Prompt ausmacht.<br><br>
							</p>
						</div>
					</div>
				</div>
				<div class="backgroundImageContainer">
					<video class="image_preview_container" src="./img/HAWKIBG.m4v" type="video/m4v" autoplay loop muted></video>
				</div>
			</main>
		</div>
	</body>
</html>
