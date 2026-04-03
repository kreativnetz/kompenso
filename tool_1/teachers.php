<?php require_once('../includes/initialize.php'); ?>

<style type="text/css">
  body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #fff;
    padding: 0;
  }

  #container {
    width: 18cm;
    margin: 20px auto;
  }

  #mutterschiff {
    display: flex;
    flex-wrap: wrap;
  }

  #left {
    flex: 2;
    padding: 20px;
  }

  #right {
    flex: 1;
    padding: 20px;
  }

  .table-container {
    overflow-x: auto;
    width: 100%;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    font-size: 9.5pt;
  }

  th, td {
    text-align: left;
    padding: 12px;
    border: 1px solid #ddd;
    vertical-align: top;
  }

  th {
    background-color: #f2f2f2;
  }

  tr:nth-child(even) {
    background-color: #f9f9f9;
  }

  tr:hover {
    background-color: #f1f1f1;
  }

@media print { 
  .noprint {
    display: none; 
  }
}


</style>

<?
if ($session->is_logged_in(2)) {
	// alles ok, falls Admin
} else { 
	$session->message('Sie sind nicht mehr eingeloggt.',1);
	redirect_to('index.php');
}

$year = (isset($_GET['year']) ? $_GET['year'] : $year);

// Zählfunktionen
function count_thesis($teacher, $type, $status) {
	global $db;
	global $year;
	$sql = "SELECT sup.id FROM supervisions AS sup, thesis WHERE sup.thesis = thesis.id AND thesis.year = '".$year."' AND sup.teacher = ".$teacher." AND sup.type = ".$type;
	$result_set = $db->query($sql);
	$anzahl = $db->num_rows($result_set);
	return $anzahl;
}

$sql = "SELECT t.*, MIN(a.class) AS main_class FROM thesis t JOIN authors a ON t.id = a.thesis WHERE t.status > 0 AND t.year = '$year' GROUP BY t.id ORDER BY main_class, t.title";
$thesises = Thesis::find_by_sql($sql);


?>

<body>
<div id="container">

		<?
		
      
		// Lehrer zusammensuchen
		
		$sql  = "SELECT * FROM teachers WHERE fmz = 1 AND status > 0 ORDER BY token";
		
		$teachers = Teacher::find_by_sql($sql);
		
		// Thesis auflisten
		
		if (!empty($teachers)) {
			?>
      <h3>Liste der Lehrpersonen <?=$year?></h3>

      <?php if ($session->is_logged_in(5)) { ?>
      <div class="noprint" style="margin-bottom: 15px;">
        <button onclick="showModalFiltered(0, 0)">LP ohne Einträge</button>
        <button onclick="showModalFiltered(1, 3)">LP mit 1–2 Einträgen</button>
        <button onclick="showModalFiltered(4, 6)">LP mit 3–6 Einträgen</button>
        <button onclick="showModalFiltered(7, 99)">LP mit mehr als 6 Einträgen</button>
        <button onclick="resetFilter()">Alle anzeigen</button>
      </div>
      <?php } ?>

      <table class="thesis_table" style="width: 100%;" width="100%">
      	<tr>
        	<td class="thesis_head" rowspan="1">Kürzel</td>
          <td class="thesis_head" rowspan="1">Nachname</td>
          <td class="thesis_head" rowspan="1">Vorname</td>
          <td class="thesis_head" colspan="1" align="center">Hauptkorrektur</td>
          <td class="thesis_head" colspan="1" align="center">Gegenkorrektur</td>
        </tr>
        
      <?
      $haupt_total = 0;
      $gegen_total = 0;
			foreach ($teachers as $teacher) {
				$haupt_definitiv = count_thesis($teacher->id,1,1);
				$haupt_total += $haupt_definitiv;
				$gegen_definitiv = count_thesis($teacher->id,2,1);
				$gegen_total += $gegen_definitiv;
				?>
				<tr 
          <?=$haupt_definitiv + $gegen_definitiv == 0 ? ' style="background-color: rgba(200,0,0,0.2);"' : ''?>
          data-email="<?=htmlentities($teacher->email ?? '')?>"
        >
        	<td 
        		class="cell"
        	>
        		<?=$teacher->token?>
        	</td>
        	<td class="cell"><?=$teacher->last_name?></td>
        	<td class="cell"><?=$teacher->first_name?></td>
          <td class="cell2" align="center"><?=$haupt_definitiv?></td>
          <td class="cell2" align="center"><?=$gegen_definitiv?></td>
        </tr>
				<?
			}
			?>
      </table>
      <?
		} else {
			echo "<p>Keine Lehrpersonen gefunden.</p>";
		}
		?>
              
		<hr />
		<a class="noprint" href="index.php">Zurück zur Startseite</a>

</div>

<!-- MODAL -->
<div id="emailModal" style="display: none; position: fixed; z-index: 9999; background: rgba(0,0,0,0.6); top: 0; left: 0; width: 100%; height: 100%;">
  <div style="background: white; margin: 5% auto; padding: 20px; width: 60%; border-radius: 6px; max-height: 80vh; overflow-y: auto;">
    <h3>E-Mail an ausgewählte Lehrpersonen</h3>
    <p><strong>Empfänger (abwählbar):</strong></p>
    <div id="recipientList" style="margin-bottom: 10px;"></div>
    <button onclick="copyEmails()" style="margin-bottom: 20px;">E-Mail-Adressen kopieren</button>

    <p><strong>Text:</strong></p>
    <textarea id="emailText" rows="20" style="width: 100%;"></textarea>

    <div style="margin-top: 15px;">
      <button onclick="copyText()">Text kopieren</button>
      <button onclick="closeModal()" style="float: right;">Schliessen</button>
    </div>
  </div>
</div>
</body>

<script>
function filterRows(min, max) {
  const rows = document.querySelectorAll(".thesis_table tr:not(:first-child)");
  const emailsToCopy = [];

  rows.forEach(row => {
    const haupt = parseInt(row.cells[3].textContent) || 0;
    const gegen = parseInt(row.cells[4].textContent) || 0;
    const sum = haupt + gegen;

    if (sum >= min && sum <= max) {
      row.style.display = "";
      const email = row.dataset.email;
      if (email) emailsToCopy.push(email);
    } else {
      row.style.display = "none";
    }
  });

  if (emailsToCopy.length > 0) {
    const temp = document.createElement("textarea");
    temp.value = emailsToCopy.join("; ");
    document.body.appendChild(temp);
    temp.select();
    document.execCommand("copy");
    document.body.removeChild(temp);
    alert(emailsToCopy.length + " E-Mail-Adressen kopiert.");
  } else {
    alert("Keine passenden Lehrpersonen gefunden.");
  }
}

function resetFilter() {
  const rows = document.querySelectorAll(".thesis_table tr");
  rows.forEach(row => row.style.display = "");
}
</script>

<script>
function generateEmailText(sum) {
  const grundtext = `Liebe Kollegin, lieber Kollege

Es sind dieses Jahr <?=count($thesises)?> IPDA bzw. SA Arbeiten zu betreuen, das erfordert also <?=2 * count($thesises)?> Einsätze unsererseits. Das ist viel und es braucht uns alle, damit wir das hinbekommen. Weiterhin gilt, dass die Übernahme von Arbeiten wie folgt entschädigt wird (Haupt bzw. Gegenkorrektur):

3er-Arbeit: 0,24 bzw. 0,09 Lektionen
2er-Arbeit: 0,22 bzw. 0,08 Lektionen
1er-Arbeit: 0,20 bzw. 0,07 Lektionen

Erfreulicherweise wurden bereits zahlreiche Einsätze übernommen. Vielen Dank allen, die bereits dazu beigetragen haben. Für die Zuteilung der verbleibenden Themen habe ich von der Schulleitung folgende Prämissen erhalten:

===

- Faustregel: Bei einem Vollpensum sollten mindestens 4 Betreuungen übernommen werden.
- Jede Lehrperson* übernimmt dabei bitte mindestens eine Hauptbetreuung.
- Ob man die Lernenden kennt und ob das Thema fachbezogen ist, spielt eine untergeordnete Rolle.
- WML-Lehrpersonen sollen bitte mind. eine Betreuung in einer ihrer WML Klassen übernehmen. 

*) falls sich eine neue Lehrperson im ersten Jahr lieber auf Gegenkorrekturen beschränken möchte, ist das natürlich ok. Wer zusätzlich Fachmaturaarbeiten übernimmt, kann sich auf Wunsch ebenfalls auf einzelne Gegenkorrekturen beschränken.

===

`;

  let individuell = '';
  if (sum === 0) {
    individuell = `Du hast dich bisher noch für keine Arbeiten eingetragen. Darf ich dich bitten, dies zeitnah gemäss den obigen Prämissen der Schulleitung zu tun?\n`;
  } else if (sum >= 1 && sum <= 2) {
    individuell = `Du hast dich bereits eingetragen, vielen Dank. Falls du gemäss den obigen Prämissen noch Arbeiten übernehmen könntest, schau gerne noch einmal rein:\n`;
  } else if (sum >= 4) {
    individuell = `Du erhältst diese Mail im Sinne der Transparenz. Vielen Dank, dass du mit deiner Übernahme von Arbeiten den nötigen Beitrag bereits leistest.\n`;
  }

  return grundtext + individuell + `
https://idpa.kreativnetz.ch
(Login mit deinem Kürzel und dem alten Blackboard-Passwort, du kannst dir dieses an deine sluz-Adresse schicken lassen, wenn du es noch nicht oder nicht mehr weisst)

Falls das dir nicht möglich ist, komm gerne auf mich oder direkt auf Sabine Stutz zu.

Herzlichen Dank für deine Mitarbeit und eine gute Woche!
Jan`;
}

function showModalFiltered(min, max) {
  const rows = document.querySelectorAll(".thesis_table tr:not(:first-child)");
  const listContainer = document.getElementById("recipientList");
  const textarea = document.getElementById("emailText");
  listContainer.innerHTML = '';
  let emails = [];

  rows.forEach(row => {
    const haupt = parseInt(row.cells[3].textContent) || 0;
    const gegen = parseInt(row.cells[4].textContent) || 0;
    const sum = haupt + gegen;

    if (sum >= min && sum <= max) {
      row.style.display = ""; // 🟢 TABELLE wird sichtbar
      const email = row.dataset.email;
      const name = row.cells[1].textContent + " " + row.cells[2].textContent;
      if (email) {
        const id = "cb_" + email.replace(/[^a-zA-Z0-9]/g, "");
        listContainer.innerHTML += `
          <div>
            <label><input type="checkbox" id="${id}" data-email="${email}" checked> ${name} (${email})</label>
          </div>
        `;
        emails.push(email);
      }
    } else {
      row.style.display = "none"; // 🔴 ausblenden
    }
  });

  if (emails.length === 0) {
    alert("Keine passenden Lehrpersonen gefunden.");
    return;
  }

  textarea.value = generateEmailText(min);
  document.getElementById("emailModal").style.display = "block";
}

function closeModal() {
  document.getElementById("emailModal").style.display = "none";
}

function copyEmails() {
  const checkboxes = document.querySelectorAll('#recipientList input[type=checkbox]:checked');
  const emails = Array.from(checkboxes).map(cb => cb.dataset.email);
  const temp = document.createElement("textarea");
  temp.value = emails.join("; ");
  document.body.appendChild(temp);
  temp.select();
  document.execCommand("copy");
  document.body.removeChild(temp);
  alert(emails.length + " Adressen kopiert.");
}

function copyText() {
  const textarea = document.getElementById("emailText");
  textarea.select();
  textarea.setSelectionRange(0, 99999); // für mobile Geräte

  try {
    document.execCommand("copy");
    alert("Text in Zwischenablage kopiert.");
  } catch (err) {
    alert("Fehler beim Kopieren.");
  }

  // Fokus entfernen
  textarea.blur();
}
</script>

<?php if (isset($db)) { $db->close_connection(); } ?>