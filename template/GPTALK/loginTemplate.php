<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>HAWKI</title>
	<link rel="shortcut icon" type="image/x-icon" href="<?= $TEMPLATE_PATH ?>/img/favicon.png" media="screen" />
	<link rel="icon" type="image/x-icon" href="<?= $TEMPLATE_PATH ?>/img/favicon.png" media="screen" />
	<link rel="stylesheet" href="<?= $TEMPLATE_PATH ?>/css/login.css">
</head>

<body>
	<!-- partial:index.partial.html -->
	<div class="wrapper">
		<aside>
			<img src="<?= $TEMPLATE_PATH ?>/img/logo.svg" alt="">
			<h2>Willkommen zurück!</h2>

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

			<h2 class="top-auto">Interesse?</h2>
			<p>Wenn Sie das Interface für Ihre Hochschule ausprobieren möchten, hinterlassen Sie bitte hier Ihre
				E-Mail-Adresse.</p>
			<form action="<?= $SCRIPT ?>" class="column" method="post" id="newsletterForm">
				<label for="newsletter">E-Mail-Adresse</label>
				<input type="email" name="newsletter" id="newsletter">
				<button>Senden</button>
			</form>
			<a href="/datenschutz" target="_blank">Datenschutzerklärung</a>
			<a href="/impressum" target="_blank">Impressum</a>
		</aside>

		<main>
			<h1>GPT für die Hochschule</h1>
			<p><small><i>HAWKI</i> ist ein didaktisches Interface für Hochschulen, das auf der API von OpenAI basiert.
				Für die Nutzerinnen und Nutzer ist es nicht notwendig, einen Account anzulegen, die Hochschul-ID
				reicht für den Login aus - es werden keine nutzerbezogenen Daten gespeichert.</small></p>
			<p>Das Angebot wurde im Interaction Design Lab der HAWK entwickelt, um allen Hochschulangehörigen die
				Möglichkeit zu geben, Künstliche Intelligenz in ihre Arbeitsprozesse zu integrieren und einen
				Begegnungsraum zu haben, damit sich eventuell neue Arbeitsweisen ergeben und eine hochschulinterne
				Diskussion über den Einsatz von K.I. entstehen kann. Derzeit ist die Oberfläche in drei Bereiche
				unterteilt:</p>

			<ul>
				<li>
					<strong>Konversation</strong>Ein Chatbereich wie bei ChatGPT, für einen schnellen Einstieg in jede
					beliebige Aufgabe.
				</li>
				<li>
					<strong>Virtuelles Büro</strong>Gespräche mit fiktiven Expert*innen als mentales Modell, um sich in
					fachfremde Bereiche einzuarbeiten und gezieltere Anfragen an echte Hochschul-Expert*innen zu
					stellen.
				</li>
				<li>
					<strong>Lernraum</strong>Die Lernräume sollen helfen, die verschiedenen Unterstützungsmöglichkeiten
					zu verstehen und zu lernen, was einen effektiven Prompt ausmacht.
				</li>
			</ul>

			<div class="video-button" id="openModal">
				<svg viewBox="0 0 512 512" title="play-circle">
					<path
						d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm115.7 272l-176 101c-15.8 8.8-35.7-2.5-35.7-21V152c0-18.4 19.8-29.8 35.7-21l176 107c16.4 9.2 16.4 32.9 0 42z" />
				</svg>
				<video src="hawkistart.mp4" playsinline preload muted loop autoplay></video>
			</div>
		</main>

		<div class="image_preview_container">
			<div class="image_preview"></div>
		</div>

	</div>

	<div id="videoModal" class="modal">
		<div class="modal-content">
			<span id="closeModal" class="close">&times;</span>
			<video src="hawkistart.mp4" controls>
		</div>
	</div>
	<!-- partial -->
	<script src="./login.js"></script>
</body>

</html>