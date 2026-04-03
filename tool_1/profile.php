<?php require_once('../includes/initialize.php'); ?>
<?php if (!$session->is_logged_in()) { redirect_to("index.php"); } ?>

<?php require('i_template/i_head.php'); ?>

<script src="javascript/myjquery.js" type="text/javascript"></script>
<script type="text/javascript">
		$(function() {
			table_highlight('.nice_table');
			table_highlight('.info_table');
			set_countdown();
		});
</script>

<body>
<div id="container">

  <?php include('i_template/i_header.php'); ?>

  <div id="mutterschiff">
  
    <div id="content">
      <div id="left">
        <?php include('../includes/login.php');?>
      </div>
 
<?php
	// Falls das Formular Profil ändern abgesendet wurde
	if (isset($_POST['profile_submit'])) {
		
		$fehler_passiert = 0;
		// Alle Felder prüfen
		$key1 = 'E-Mail-Adresse';
		$key2 = 'Wunsch';

		$fehler = array($key1 => '', $key2 => '');
		//$fehler['email'] = check_field($key1,'email','/^[a-zA-Z0-9]+[a-zA-Z0-9\-_\.]+@[a-zA-Z0-9\-_\.]+\.[a-zA-Z]{2,4}$/');
		$fehler['wish'] = check_field($key2,'wish','/^[0-9]{1,2}$/');
		unset($_POST['profile_submit']);
		
		foreach($_POST as $key => $value) {
			if (strlen($fehler[$key])>0) { 
				message("Bitte korrekt ausfüllen: ".substr($fehler[$key],0,-2),1); 
				$fehler_passiert = 1;
			} else {
				$login_user->$key = $db->escape_value($value);
			}
		}
							
		if ($fehler_passiert == 0) { 
			if ($login_user->save()) {
				message('Die Änderungen wurden gespeichert.');
			} else {
				message('Es wurden keine Änderungen vorgenommen',1);
			}
		}

	}
	
	// Falls das Formular Passwort ändern abgesendet wurde
	if (isset($_POST['password_submit'])) {
		
		// Alte Passwörter vergleichen
		$new_pass_1 = read_post('new_pass_1');
		$new_pass_2 = read_post('new_pass_2');
		if ($new_pass_1 == $new_pass_2 && strlen($new_pass_1) >= 4) {
			
			// Altes Passwort verschlüsseln und prüfen
			$old_pass = read_post('old_pass');
			$old_hashed_pass = sha1($old_pass);
			if ($old_hashed_pass == $login_user->password) {
				$new_hashed_pass = sha1($new_pass_1);
				$login_user->password = $new_hashed_pass;

				// Speichern
				if ($login_user->update()) {
					message('Das neue Passwort wurde gespeichert.');
				} else {
					message('Das neue Passwort muss sich vom alten unterscheiden', 1);
				}
			} else {
				message('Das alte Passwort ist nicht korrekt.', 1);
			}
		} else {
			message('Dein neues Passwort wurde entweder nicht zweimal identisch eingegeben oder ist zu kurz (min. 4 Zeichen)', 1);
		}
	}	



	// Falls das Formular Profil löschen abgesendet wurde
	if (isset($_POST['delete_profile'])) {
		
		if (!empty($_POST['delete'])) {
			$login_user->deactivate();
			message('Das Profil wurde deaktiviert. Bitte logge dich nun aus.');
		} else {
			message('Bitte bestätige zuerst, dass du dir das gut überlegt hast.',1);
		}
	}	

?>     
      <div id="right">
      	<?
      	/*
        <form class="modul_left" action="<?=$_SERVER['PHP_SELF']?>" name="change_profile" method="post">
          <h3><?=icon('edit_profile');?> Profil von <?=clear($login_user->full_name())?> bearbeiten</h3>
          Hier kannst du deine E-Mail-Adresse ändern und angeben, wie viele Arbeiten du dieses Jahr gerne übernehmen würdest.
          <table class="nice_table">
            <tr><td>Anzahl gew&uuml;nschte Arbeiten:</td><td><?=edit('wish',$login_user->wish,'40px')?></td></tr>
            <tr><td>&nbsp;</td><td><?=submit('profile_submit','Profil speichern')?></td></tr>
          </table>
        </form>
        */
        ?>

        <form class="modul_left" action="<?=$_SERVER['PHP_SELF']?>" name="change_password" method="post">
          <h3><?=icon('password');?> Passwort ändern</h3>
					Hier kannst du dein Passwort ändern.
          <table class="nice_table">
            <tr><td width="120">altes Passwort:</td><td><input type="password" name="old_pass" size="20" /></td></tr>
            <tr><td>neues Passwort:</td><td><input type="password" name="new_pass_1" size="20" /></td></tr>
            <tr><td>neues Passwort:</td><td><input type="password" name="new_pass_2" size="20" /></td></tr>
            <tr><td>&nbsp;</td><td><?=submit('password_submit','Passwort ändern')?></td></tr>
        </table>
        </form>

				<? if (1 == 2) { ?>
        <form class="modul_left" action="<?=$_SERVER['PHP_SELF']?>" name="form_delete_profile" method="post">
          <h3><?=icon('remove_m');?> Profil deaktivieren</h3>
					Hier kannst du dein Profil deaktivieren.
          <table class="nice_table">
            <tr>
            	<td>
             		<input type="checkbox" name="delete" value="1" />
								Ja, ich hab mir das gut überlegt und möchte mein Profil deaktivieren.
              </td>
            </tr>
            <tr>
              <td>
              	<input type="submit" name="delete_profile" value="Profil deaktivieren" />
              </td>
            </tr>
        	</table>
        </form>
        <? } ?>

			<hr />
			<a href="index.php">Zurück zur Startseite</a>        
      </div>
      


    </div>
    
    <?php include('i_template/i_footer.php'); ?>
	</div>
</div>
</body>
</html>

<?php if (isset($db)) { $db->close_connection(); } ?>               


