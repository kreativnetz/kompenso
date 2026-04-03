	<div id="header" class="trans">
    <a href="index.php" id="startlink">&nbsp;</a>
		<div id="top" class="trans">
    	<div class="top_link">
    		<a href="http://www.kreativnetz.ch/kontakt.php" target="_blank" title="kreativnetz.ch kontaktieren">Kontakt</a>
      </div>
    </div>
	</div>

<?php

// Falls sich in der Session eine Message befindet, diese jetzt anzeigen
if (strlen($session->message) > 0) {
	message($session->message, $session->message_type);
}

// Bei einem Loginfehler die Meldung anzeigen:
if (isset($_POST['login_submit']) && isset($loginfehler)) {
	if ($loginfehler) message("Benutzername und Passwort stimmen nicht &uuml;berein",1);
}
?>