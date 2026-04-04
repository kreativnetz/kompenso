<?php require_once('../includes/initialize.php'); ?>
<?php

function current_school_year_number(int $startMonth = 8): int {
  $nowY = (int)date('Y');
  $nowM = (int)date('n');
  $schoolStartY = ($nowM >= $startMonth) ? $nowY : ($nowY - 1);
  return $schoolStartY % 100; // 2025 -> 25
}

// usage:
$yearnumber = current_school_year_number(8);

// Helper: generiert Klassenlabels (a–g, dynamisch) aus Abteilung + $yearnumber
function generate_classes_for_section(array $sections, $section_key, $yearnumber) {
  if (!isset($sections[$section_key])) return array();
  $prefix = $sections[$section_key]['prefix'];
  $y = (int)$yearnumber + (int)$sections[$section_key]['year_offset'];
  $y_str = sprintf('%02d', $y); // z. B. 25 → "25"
  $last = isset($sections[$section_key]['last_letter']) ? strtolower($sections[$section_key]['last_letter']) : 'g';
  if (!preg_match('/^[a-z]$/', $last)) { $last = 'g'; }
  $out = array();
  for ($ch = ord('a'); $ch <= ord($last); $ch++) {
    $out[] = $prefix . $y_str . chr($ch);
  }
  return $out;
}

$max_lernende = 3;
if ($phase < 3 || $session->is_logged_in(2) || ($phase == 3 && !isset($_GET['edit']))) {
	// alles ok, falls Phase 1 oder 2 noch läuft oder ein Admin eigeloggt ist oder in der dritten Phase ein neues Thema eingegeben werden soll
} else {
	$session->message('Sie können kein Thema mehr eingeben oder bearbeiten.',1);
	redirect_to('index.php');
}

if (isset($_GET['edit'])) {
	// Falls ein bestehendes Thema editiert wurde, hole bestehende ID aus der DB
	$sql = "SELECT id FROM thesis WHERE password = '".$_GET['thesis']."'";
	$result_set = $db->query($sql);
	if ($db_id = $db->get_value($result_set,'id')) {
	} else {
		$session->message('Es existiert keine Arbeit mit diesem Code.',1);
		redirect_to('index.php');
	}
}

// Falls ein Thema eingereicht oder bearbeitet wird
if (isset($_POST['thesis_submit'])) {

	// Alle Felder prüfen
	$fehler = '';
	$fehler .= check_field('Abteilung','section','/^[1-7]{1}$/');
	$fehler .= check_field('Titel','title',3);
	$fehler .= check_field('Beschreibung','description',10);  // min. Länge von 10 Zeichen

  // Teste die Eingabe der Art der Arbeit
	$fehler .= check_field('Art der Arbeit','type','/^[1-9]{1}$/');
	
	// Teste die Eingaben der Autoren
  for ($i = 1; $i <= 3; $i++) {
		if ($i > 1 && empty($_POST['first_name_'.$i]) && empty($_POST['last_name_'.$i]) && empty($_POST['class_'.$i]) && empty($_POST['email_'.$i])) continue;
		$fehler .= check_field('Vorname ('.$i.')','first_name_'.$i,'/^[a-zA-ZéèàâîïëêçøåæäöüÄÖÜs \-\']{2,}$/');
		$fehler .= check_field('Nachname ('.$i.')','last_name_'.$i,'/^[a-zA-ZéèàâîïëêçøåæäöüÄÖÜs \-\']{2,}$/');
		$fehler .= check_field('Klasse ('.$i.')','class_'.$i,'/^[0-9BVGDFMWabcdef]{3,5}$/');
		$fehler .= check_field('E-Mail ('.$i.')','email_'.$i,'/^[a-zA-Z0-9]+[a-zA-Z0-9\-_\.]+@[a-zA-Z0-9\-_\.]+\.[a-zA-Z]{2,4}$/', true); // true: trim before check
		if (strlen($_POST['handy_'.$i]) > 0) $fehler .= check_field('Handy ('.$i.')','handy_'.$i,'/^[0-9 -\/]{10,}$/');	
	}
	
  // Falls ein Fehler vorhanden, ausgeben!
	if (strlen($fehler)>0) {
		message('Bitte korrekt ausfüllen: '.substr($fehler,0,-2),1);

  // sonst, falls also alle Felder korrekt ausgefüllt wurden, geht es hier weiter
	} else {
		
		// Lernende zählen
		$author_count = 0;
    for ($i = 1; $i <= $max_lernende; $i++) {
        if (strlen(trim($_POST['first_name_'.$i] ?? '')) > 0) {
            $author_count++;
        }
    }
    $is_vg = (isset($_POST['section']) && (int)$_POST['section'] === 1);

		// alle Werte der Thesis zuordnen
		$new_thesis = new Thesis();
		$new_thesis->section = read_post('section',1,1);
		$new_thesis->title = read_post('title',1,1);
		$new_thesis->description = read_post('description',1,1);
		
		$new_thesis->subject1 = 0;
		$new_thesis->subject2 = 0;

    // Art der Arbeit
		$new_thesis->type = read_post('type');

    // Falls neu eingegebenes Thema: Passwort generieren
		if (!isset($_GET['edit'])) {
			srand((double)microtime()*1000000); 
			$new_pass = substr(md5(rand(0,9999999)), 0, 10);  // 10 Zeichen lang
			$new_thesis->password = $new_pass;
			$new_thesis->year = $year;
			$new_thesis->status = 1;
			if ($is_vg && $author_count === 1) {
        $new_thesis->status = 0;	// muss vom Rektor bewilligt werden.
      }
  
  	// Sonst, falls ein bestehendes Thema editiert wurde, nimm die bestehende ID aus der DB
    } else {
			$new_thesis->id = $db_id;
		}

		// Thesis speichern
		$new_thesis->save();
		if (!isset($new_thesis->id)) $new_thesis->id = $db->insert_id();
								
		// Bereits gespeicherte Autoren löschen
		$sql = "DELETE FROM authors WHERE thesis = ".$new_thesis->id;
		$db->query($sql);
    $autoren = array();
		
		// Sammle Klassenlabels für evtl. DB-Anlage in Tabelle `classes`
		$used_class_labels = array();

		// Autoren speichern
		for ($i = 1; $i <= $max_lernende; $i++) {
			
			// Falls der Vorname nicht eingegeben wurde, springe zum nächsten
			if (strlen($_POST['first_name_'.$i]) == 0) continue;
			
			// Autor alles zuweisen und speichern
			$new_author = new Author();
			$new_author->first_name = read_post('first_name_'.$i);
			$new_author->last_name = read_post('last_name_'.$i);
			$new_author->class = read_post('class_'.$i);
			if (strlen($new_author->class) > 0) { $used_class_labels[] = $new_author->class; }
			$new_author->email = read_post('email_'.$i);
			$new_author->handy = sauber_handy(read_post('handy_'.$i));
			$new_author->password = '';
			$new_author->thesis = $new_thesis->id;
			$new_author->status = 1;
			$new_author->save();
			
			// Mail an den Autor senden (nur falls neu registriert)
			if (!isset($_GET['edit'])) {
				$mymail = new Mail();
				$mymail->to = $new_author->first_name." ".$new_author->last_name." <".$new_author->email.">";
				$mymail->subject = 'Thema eingereicht';
				$mymail->message  = '<p class="absatz">Hallo '.clear($new_author->full_name()).'</p>';
				$mymail->message .= '<p class="absatz">Sie haben Ihr IDPA/SA-Thema erfolgreich eingereicht:</p>';
				$mymail->message .= '<p class="fett absatz">'.read_post('title',1,1).'</p>';
				$mymail->message .= '<p class="absatz">Mit folgendem Code können Sie Ihr Thema noch bis zum '.datum($phases[2],3).' bearbeiten:</p>';
				$mymail->message .= '<p class="absatz" id="code">'.$new_pass.'</p>';
				$mymail->message .= '<p class="absatz">Freundliche Grüsse<br />kreativnetz.ch</p>';
				$gesendet = $mymail->send(0);
			}
      $autoren[] = $new_author->full_name().' ('.$new_author->class.')';
		}	

		// Einmalig alle verwendeten Klassenlabels in Tabelle `classes` anlegen, falls nicht vorhanden
		if (!empty($used_class_labels)) {
		$used_class_labels = array_unique($used_class_labels);
		foreach ($used_class_labels as $label) {
			$sql_chk = "SELECT id FROM classes WHERE label = '" . $db->escape_value($label) . "' LIMIT 1";
			$rs_chk = $db->query($sql_chk);
			$class_id = $db->get_value($rs_chk, 'id');
			if (!$class_id) {
			// teacher = 0, status = 1 (oder egal)
			$sql_ins = "INSERT INTO classes (label, teacher, status) VALUES ('" . $db->escape_value($label) . "', 0, 1)";
			$db->query($sql_ins);
			}
		}
		}
	
  
    // E-Mail an Superadmin senden
    $mymail = new Mail();
    $mymail->to = "Jan Siegwart <jan@kreativnetz.ch>";
    if (!isset($_GET['edit'])) $mymail->subject = 'Neues Thema eingereicht';
    if (isset($_GET['edit'])) $mymail->subject = 'Thema verändert';
    $mymail->message .= '<p class="fett absatz" id="code">'.read_post('title',1,1).'</p>';
    $autoren = implode(', ', $autoren);
    $mymail->message .= '<p class="absatz"><i>'.$autoren.'</i></p>';
    $mymail->message .= '<p class="absatz">'.$new_thesis->description.'</p>';
    $gesendet = $mymail->send(0);
		
		// Falls ein Thema bearbeitet wurde
		if (isset($_GET['edit']) && $gesendet) message('Die Änderungen wurden gespeichert.');

		// Flag für VG-Einzelarbeit
		$show_vg_single = (!isset($_GET['edit']) && $gesendet && $is_vg && $author_count === 1);

		// Falls ein neues Thema eingegeben wurde: passende Meldung
		if (!isset($_GET['edit']) && $gesendet) {
		    if ($show_vg_single) {
		        message('Ihre Einzelarbeit wurde gespeichert. Bitte wenden Sie sich an Ihren Rektor, um sie genehmigen zu lassen.');
		    } else {
		        message('Das Thema wurde eingereicht.');
		    }
		}

		// Redirect inkl. Flag
		if (!isset($_GET['edit'])) {
		    $url = 'themeneingabe.php?thesis='.$new_thesis->password;
		    if ($show_vg_single) $url .= '&vg_single=1';
		    redirect_to($url);
		}
	}
}

// Falls das Thema bearbeitet werden soll, generiere die Variable $db_quelle (bestehende Thesis aus $db)
if (isset($_GET['edit']) && $db_id) {
	$db_quelle = Thesis::find_by('password',read_get('thesis'));
	// Fächer in Ordnung bringen
	$db_quelle->subject_g1 = $db_quelle->subject1;
	$db_quelle->subject_g2 = $db_quelle->subject2;
	$db_quelle->subject_w1 = $db_quelle->subject1;
	$db_quelle->subject_w2 = $db_quelle->subject2;
	// Autoren aus der DB holen
	$authors = $db_quelle->get_authors();	
	if (!isset($authors[1])) $authors[1] = new Author();
	if (!isset($authors[2])) $authors[2] = new Author();
	if (!isset($authors[3])) $authors[3] = new Author();
}

?>


<?php require('i_template/i_head.php'); ?>

<style type="text/css">
  #row_title, #row_description { display: none; }
</style>

<script>
function show_teachers(teacher_number, subject) {
  var el = document.getElementById('td_teacher' + teacher_number);
  if (!el) return;               // ✅ element not present in this form/section
  el.style.display = 'table-cell';
}

function show_subjects(sectionKey) {
  var g = document.getElementById('subjects_g');
  var w = document.getElementById('subjects_w');
  var t = document.getElementById('teachers');
  if (g) g.style.display = 'none';
  if (w) w.style.display = 'none';
  if (t) t.style.display = 'none';

  sectionKey = parseInt(sectionKey, 10) || 0;

  // Sichtbarkeit gemäss neuer Abteilungslogik:
  // 1 BMGS Vollzeit  -> subjects_g
  // 2 BMGS Teilzeit  -> subjects_g
  // 3 FMS            -> keine Fächer, keine Lehrpersonen
  // 4 GMS            -> subjects_g + Lehrpersonen
  // 5 WML            -> subjects_w + Lehrpersonen
  // 6 IMS            -> subjects_w + Lehrpersonen (UI teilt sich das W-Formular)
  // 7 Fachmatura     -> aktuell keine Fächer/Lehrpersonen im Formular

  if ([1, 2, 4].indexOf(sectionKey) !== -1) { if (g) g.style.display = ''; }
  if ([5, 6].indexOf(sectionKey) !== -1) { if (w) w.style.display = ''; }

  if ([4, 5, 6].indexOf(sectionKey) !== -1) { if (t) t.style.display = ''; }
}

// Sections + yearnumber aus PHP in JS übertragen
var SECTIONS = <?php echo json_encode($sections); ?>;
var YEARNUMBER = <?=(int)$yearnumber?>;

function computeYearForSection(sectionKey) {
  if (!SECTIONS[sectionKey]) return null;
  return (YEARNUMBER + parseInt(SECTIONS[sectionKey]['year_offset'] || 0));
}

function buildClassOptionsForSelect(select, sectionKey, preselectValue) {
  if (!select) return;
  select.innerHTML = '';

  // Placeholder immer zuerst
  var ph = document.createElement('option');
  ph.value = '';
  ph.text = 'Klasse wählen';
  ph.selected = !preselectValue;
  select.appendChild(ph);

  if (!SECTIONS[sectionKey]) return;

  var prefix = SECTIONS[sectionKey]['prefix'];
  var y = computeYearForSection(sectionKey);
  if (y === null) return;
  var ystr = (''+y).padStart(2,'0');

  var last = (SECTIONS[sectionKey]['last_letter'] || 'g').toString().toLowerCase();
  if (!/^[a-z]$/.test(last)) { last = 'g'; }

  for (var code = 'a'.charCodeAt(0); code <= last.charCodeAt(0); code++) {
    var letter = String.fromCharCode(code);
    var label = prefix + ystr + letter;
    var opt = document.createElement('option');
    opt.value = label;
    opt.text = label;
    if (preselectValue && preselectValue === label) opt.selected = true;
    select.appendChild(opt);
  }
}

var MAX_L = <?=(int)$max_lernende?>;
var PRESELECT_CLASSES = <?=json_encode(isset($old_class) ? $old_class : array())?>;

function buildAllClassDropdowns(sectionKey) {
  for (var i = 1; i <= MAX_L; i++) {
    var sel = document.getElementById('class_'+i) || document.getElementsByName('class_'+i)[0];
    var pre = (PRESELECT_CLASSES && PRESELECT_CLASSES[i]) ? PRESELECT_CLASSES[i] : '';
    buildClassOptionsForSelect(sel, sectionKey, pre);
  }
}

function toggleAfterSection(show) {
  var r1 = document.getElementById('row_title');
  var r2 = document.getElementById('row_description');
  var blk = document.getElementById('afterSectionBlock');
  if (r1) r1.style.display = show ? 'table-row' : 'none';
  if (r2) r2.style.display = show ? 'table-row' : 'none';
  if (blk) blk.style.display = show ? '' : 'none';
}

function start() {
  <?=(isset($_POST['section']) ? 'show_subjects('.(int)$_POST['section'].');' : '')?> 
  <?=(isset($_POST['teacher1']) ? 'show_teachers(1);' : '')?> 
  <?=(isset($_POST['teacher2']) ? 'show_teachers(2);' : '')?> 
  <?=(isset($db_quelle) ? 'show_subjects('.(int)$db_quelle->section.');' : '')?>
  <?=(isset($db_quelle) ? 'show_teachers(1);' : '')?>
  <?=(isset($db_quelle) ? 'show_teachers(2);' : '')?>

	// Per-Lernende Klassen-Dropdowns initialisieren
	var sel = document.getElementById('section');
	var val = sel ? sel.value : '';
	var valid = (val && SECTIONS.hasOwnProperty(val));
	if (valid) {
	  buildAllClassDropdowns(val);
	} else {
	  buildAllClassDropdowns('');
	}
	// Zeige erst nach gültiger Abteilungswahl Titel/Beschreibung und Folgendes an
	toggleAfterSection(valid);
}

function confirmSingleVG() {
  var sel = document.getElementById('section');
  var sectionVal = sel ? parseInt(sel.value, 10) : 0;

  if (sectionVal !== 1) return true; // nicht VG -> kein Confirm

  // Anzahl Lernende zählen (mindestens Vorname ausgefüllt)
  var count = 0;
  for (var i = 1; i <= MAX_L; i++) {
    var fn = document.getElementById('first_name_' + i) || document.getElementsByName('first_name_' + i)[0];
    if (fn && fn.value.trim().length > 0) count++;
  }

  if (count === 1) {
    return confirm('Einzelarbeiten müssen von Ihrem Rektor genehmigt werden. Trotzdem fortfahren?');
  }
  return true;
}
</script>

<body onLoad="start()">

<div id="container">

<?php include('i_template/i_header.php'); ?>

  <div id="mutterschiff">

    <div id="content">
      
      <div id="right">
      	<h2 style="background-image:url(layout/icon_student.jpg)"><?=(isset($_GET['edit']) ? 'Bearbeiten' : 'Einreichen')?> eines Themas</h2>


      	<?php
				if (!(!isset($_GET['edit']) && isset($_GET['thesis']))) { // 1. Schritt

				    // 1) EDIT-Fall: Basis DB, aber POST (bei Fehlern) hat Vorrang
				    if (isset($_GET['edit'])) {
				        $old_section      = isset($_POST['section'])      ? read_post('section')      : $db_quelle->section;
				        $old_title        = isset($_POST['title'])        ? read_post('title')        : $db_quelle->title;
				        $old_description  = isset($_POST['description'])  ? read_post('description')  : $db_quelle->description;

				        $old_subject_g1   = isset($_POST['subject_g1'])   ? read_post('subject_g1')   : $db_quelle->subject_g1;
				        $old_subject_g2   = isset($_POST['subject_g2'])   ? read_post('subject_g2')   : $db_quelle->subject_g2;
				        $old_subject_w1   = isset($_POST['subject_w1'])   ? read_post('subject_w1')   : $db_quelle->subject_w1;
				        $old_subject_w2   = isset($_POST['subject_w2'])   ? read_post('subject_w2')   : $db_quelle->subject_w2;
				        $old_subject_i1   = isset($_POST['subject_i1'])   ? read_post('subject_i1')   : $db_quelle->subject_w1;
				        $old_subject_i2   = isset($_POST['subject_i2'])   ? read_post('subject_i2')   : $db_quelle->subject_w2;

				        $old_teacher1     = isset($_POST['teacher1'])     ? read_post('teacher1')     : $db_quelle->teacher1;
				        $old_teacher2     = isset($_POST['teacher2'])     ? read_post('teacher2')     : $db_quelle->teacher2;

				        $old_type         = isset($_POST['type'])         ? read_post('type')         : $db_quelle->type;

				        // Klassen (3 Autoren) – POST hat Vorrang
				        $old_class = array();
				        $old_class[1] = isset($_POST['class_1']) ? read_post('class_1') : $authors[0]->class;
				        $old_class[2] = isset($_POST['class_2']) ? read_post('class_2') : $authors[1]->class;
				        $old_class[3] = isset($_POST['class_3']) ? read_post('class_3') : $authors[2]->class;

				    // 2) NEU-ERFASSUNG mit POST (Validierungsfehler): alles aus POST
				    } elseif (isset($_POST['thesis_submit'])) {
				        $old_section      = read_post('section');
				        $old_title        = read_post('title');
				        $old_description  = read_post('description');

				        $old_subject_g1   = read_post('subject_g1');
				        $old_subject_g2   = read_post('subject_g2');
				        $old_subject_w1   = read_post('subject_w1');
				        $old_subject_w2   = read_post('subject_w2');
				        $old_subject_i1   = read_post('subject_i1');
				        $old_subject_i2   = read_post('subject_i2');

				        $old_teacher1     = read_post('teacher1');
				        $old_teacher2     = read_post('teacher2');

				        $old_type         = read_post('type');

				        $old_class = array();
				        $old_class[1] = read_post('class_1');
				        $old_class[2] = read_post('class_2');
				        $old_class[3] = read_post('class_3');

				    // 3) Erstaufruf ohne POST: Defaults
				    } else {
				        $old_section = '';
				        $old_title = '';
				        $old_description = '';
				        $old_subject_g1 = '';
				        $old_subject_g2 = '';
				        $old_subject_w1 = '';
				        $old_subject_w2 = '';
				        $old_teacher1 = '';
				        $old_teacher2 = '';
				        $old_type = 1; // Default: schriftliche Arbeit
				        $old_class = array('','','','');
				    }

				    // Klassenliste aus gewählter Abteilung generieren (für Dropdowns)
				    $classes = array();
				    if (!empty($old_section)) {
				        $classes = generate_classes_for_section($sections, $old_section, $yearnumber);
				    }


          // Vorbereiten der Dropdowns "Fahlehrperson"
          $sql = "SELECT * FROM teachers WHERE status > 0 ORDER BY last_name, first_name";
          $teachers = Teacher::find_by_sql($sql);
          $teachers_keys = get_array($teachers,'token');
          $teachers_first_names = get_array($teachers,'first_name');
          $teachers_last_names = get_array($teachers,'last_name');
          $teachers_names = array();
          foreach ($teachers_first_names as $key => $value) {
            $teachers_names[$key] = $teachers_first_names[$key].' '.$teachers_last_names[$key];
          }
					?>
          
          
          <form id="register_form" name="register_thesis" action="<?php echo $_SERVER['PHP_SELF']; ?><?=(isset($_GET['edit']) ? '?thesis='.$_GET['thesis'].'&edit' : '')?>" method="post" onsubmit="return confirmSingleVG();">
            <table id="register_table" class="info_table" width="100%" border="0">
              <tr>
                <td class="register_td">
					<?
					$section_keys = array_keys($sections);
					$section_names = array();
					foreach ($sections as $k => $def) { $section_names[] = $def['name']; }
					?>
					<?=dropdown('section',$old_section,$section_keys,$section_names,'id="section" onchange="javascript:show_subjects(this.value); buildAllClassDropdowns(this.value); toggleAfterSection(SECTIONS.hasOwnProperty(this.value));"','Wählen Sie Ihre Abteilung')?>
                </td>
              </tr>
              <tr id="row_title">
                <td class="register_td"><?=edit('title',$old_title,'400px','text" maxlength="50','Thema (möglicher Titel, max. 50 Zeichen inkl. Leerzeichen)')?></td>
              </tr>
              <tr id="row_description">
                <td class="register_td"><?=textarea('description',$old_description,'400px','8','Kurzbeschreibung Ihres Themas (konkrete Fragestellung)')?></td>
                <td width="20" align="center"><?=icon('info')?></td>
                <td>Geben Sie hier in drei bis vier Sätzen eine Kurzbeschreibung Ihres Themas (konkrete Fragestellung) ein.</td>
              </tr>
            </table>
<div id="afterSectionBlock" style="display:none;">
            <table>
              <tr>
                <td class="register_td"><?=dropdown('type',$old_type,array_keys($art_der_arbeit),array_values($art_der_arbeit),'','Art der Arbeit')?>
                </td>
              </tr>                               
            </table>

						<? 
						if (!isset($_GET['thesis'])) { ?>

              <h3>Erfassen der Lernenden</h3>
			  

			<table>
                <? for ($i = 1; $i <= $max_lernende; $i++) { ?>
                <tr>
                  <td class="register_td"><?=edit('first_name_'.$i,read_post('first_name_'.$i),'180px','text','Vorname')?></td>
                  <td class="register_td"><?=edit('last_name_'.$i,read_post('last_name_'.$i),'180px','text','Nachname')?></td>
                  <td class="register_td"><?=dropdown('class_'.$i,$old_class[$i],array_values($classes),array_values($classes),'id="class_'.$i.'"','Klasse')?></td>
                  <td class="register_td"><?=edit('email_'.$i,read_post('email_'.$i),'180px','text','E-Mail-Adresse')?></td>
                  <td class="register_td"><?=edit('handy_'.$i,read_post('handy_'.$i),'180px','text','Handynummer')?></td>
                </tr>
                <? } ?>
              </table>
  
						<? 
						} else { 
						?>
            
              <h3 class="active_tab">Erfasste Lernende</h3>
  
              <table>
                <? 
                for ($i = 1; $i <= $max_lernende; $i++) { ?>
                  <tr>
                    <td class="register_td"><?=edit('first_name_'.$i,$authors[$i-1]->first_name,'180px','text','Vorname')?></td>
                    <td class="register_td"><?=edit('last_name_'.$i,$authors[$i-1]->last_name,'180px','text','Nachname')?></td>
                    <td class="register_td"><?=dropdown('class_'.$i,$authors[$i-1]->class,array_values($classes),array_values($classes),'id="class_'.$i.'"','Klasse')?></td>
                    <td class="register_td"><?=edit('email_'.$i,$authors[$i-1]->email,'180px','text','E-Mail-Adresse')?></td>
                    <td class="register_td"><?=edit('handy_'.$i,$authors[$i-1]->handy,'180px','text','Handynummer')?></td>
                  </tr>
                <? 
                } 
                ?>
              </table>

						<? 
						} 
						?>
            
            
            <table>
              <tr>
                <td colspan="2">
                  <?=submit('thesis_submit',(isset($_GET['edit']) ? 'Änderungen speichern' : 'Thema einreichen'),'','')?>
                </td>
              </tr>
            </table>
            
            <hr />
 						<a href="index.php">Zurück zur Startseite</a><br />
 						<? if ($session->is_logged_in(2)) { ?> <a href="thesis.php">zurück zur Themenliste</a> <? } ?>

          </form>                
        </div>
        <?
        } else { // 2. Schritt
        ?>
        
          <h3 class="active_tab">Vielen Dank für Ihre Themeneingabe</h3>
          <?php if (!empty($_GET['vg_single'])): ?>
					  <p><strong>Ihre Einzelarbeit wurde gespeichert. Bitte wenden Sie sich an Ihren Rektor, um sie genehmigen zu lassen.</strong></p>
					<?php endif; ?>
          <p>
            Eine Bestätigung wurde an Ihre angegebene(n) E-Mail-Adresse(n) geschickt.<br />
            Mit folgendem Code können Sie Ihr Thema noch bis zum <b><?=datum($phases[2],3)?></b> bearbeiten:</p>
          <p id="code"><?=$_GET['thesis']?></p>
          <p>Sie können dieses Fenster nun schliessen, oder Ihr <a href="themeneingabe.php?thesis=<?=$_GET['thesis']?>&edit">Thema bearbeiten</a></p>
     
        <? 
        } 
				?>
                  


      </div> <!-- right -->
    </div> <!-- content -->

    <?php include('i_template/i_footer.php'); ?>
  
  </div> <!-- mutterschiff -->
</div>
</body>
</html>

<?php if (isset($db)) { $db->close_connection(); } ?>