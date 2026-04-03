<?php require_once('../includes/initialize.php'); ?>
<?
if ($session->is_logged_in(2) || ($phase > 1 && $session->is_logged_in())) {
	// alles ok, falls Admin oder Phase 2 und eingeloggt.
} else { 
	$session->message('Es ist noch zu früh, um auf die Liste zuzugreifen.',1);
	redirect_to('index.php');
}

// buchen und ausbuchen
if (isset($_GET['book']) && ($phase == 3 || $phase == 4)) {
	$book = read_get('book');
	$type = read_get('type');
	if (is_numeric($book)) book($book,$type);
}

if (isset($_GET['unbook']) && ($phase < 5)) {
	$unbook = read_get('unbook');
	if (is_numeric($unbook)) unbook($unbook);
}

if (isset($_GET['mark']) && $phase == 3) {
	$mark = read_get('mark');
	$job = read_get('job');
	if (is_numeric($mark)) mark($mark, $job);
}

if (isset($_GET['confirm']) && $session->is_logged_in(2)) {
	$confirm = read_get('confirm');
	$whom = read_get('whom');
	$type = read_get('type');
	if (is_numeric($confirm)) confirm($confirm, $whom, $type);
}

if (isset($_GET['unconfirm']) && $session->is_logged_in(2)) {
	$unconfirm = read_get('unconfirm');
	$type = read_get('type');
	if (is_numeric($unconfirm)) unconfirm($unconfirm, $type);
}

if (isset($_GET['delete']) && $session->is_logged_in(2)) {
	$delete = read_get('delete');
	$type = read_get('type');
	if (is_numeric($delete)) delete($delete, $type);
}

if (isset($_GET['change']) && $session->is_logged_in(2)) {
	$change = read_get('change');
	if (is_numeric($change)) change($change);
}

if (isset($_GET['deletethesis']) && $session->is_logged_in(3)) {
	$deletethesis = read_get('deletethesis');
	if (is_numeric($deletethesis)) {
		$sql = "DELETE FROM authors WHERE thesis = ".$deletethesis;
		$db->query($sql);
		$sql = "DELETE FROM supervisions WHERE thesis = ".$deletethesis;
		$db->query($sql);
		$sql = "DELETE FROM thesis WHERE id = ".$deletethesis;
		$db->query($sql);
	}
}

// Offen halten
$open = read_get('open');

$search = new Search();

// optional filter: only theses where at least one supervision (main or secondary) is missing
$filterMissing = isset($_GET['missing']); // true/false

// Suche
if (isset($_GET['search_submit'])) {
	$search->year = read_get('year');
	$search->subject = read_get('subject');
	$search->section = read_get('section');
	$search->class = read_get('class');
	$search->save();
}

?>
<?php require('i_template/i_head.php'); ?>

<script type="text/javascript">
function switch_it(id) {
	var status = document.getElementById('thesis_'+id).style.display;
	if (status == 'table') {
		document.getElementById('thesis_'+id).style.display = 'none';
	} else {
		document.getElementById('thesis_'+id).style.display = 'table';
	}
}
</script>

<style type="text/css">
<? if (isset($_GET['teacher'])) { ?>
		.clapup {
			display:table;
		}
<? } ?>
</style>

<body <? if ($open > 0) { ?>onload="switch_it(<?=$open?>)"<? } ?>>

<?php include('i_template/i_header.php'); ?>
<div id="container">
  <div id="mutterschiff">

    <div id="content">
      
      <div id="left">
       <? include('../includes/login.php'); ?>
      </div>
      <div id="right">
				<?
				
				// Suchmaske ?>
				<div id="suchmaske">
          <form action="<?=$_SERVER['PHP_SELF']?>" method="get">
            <table>
            	<? if ($adminmodus) { ?>
                <tr>
                  <td width="80">Jahr</td>
                  <td><?=dropdown('year',isset($search->year)?$search->year:$year_key,array_keys($years),array_values($years))?></td>
                </tr>
              <? } ?>
              <tr>
                <td>Abteilung</td>
                <td><?=dropdown('section',$search->section,array_keys($sections),array_values($sections),'','alle')?></td>
              </tr>
              <tr>
                <td>Klasse</td>
                <td><?=dropdown('class',$search->class,array_values($classes),array_values($classes),'','alle')?></td>
              </tr>
              <tr>
              	<td></td>
                <td>
	                <?=submit('search_submit','Suchen')?>&nbsp;
                	<input class="<?=(!isset($_GET['teacher']) ? 'red_button' : '')?>" type="button" onClick="location.href='thesis.php'" value="alle IDPA/SA">&nbsp;
                	<input class="<?=(isset($_GET['teacher']) ? 'red_button' : '')?>" type="button" onClick="location.href='thesis.php?teacher'" value="meine IDPA/SA">
                </td>
              </tr>
              <tr>
                  <td></td>
                  <td>
                    <label>
                      <input type="checkbox" name="missing" value="any" <?= isset($_GET['missing']) ? 'checked' : '' ?>>
                      nur Arbeiten mit fehlender Haupt- oder Gegenkorrektur
                    </label>
                  </td>
                </tr>
            </table>
          </form>
        </div>
        
        <?
				
				if ($phase == 2) { ?>
					<p class="meldung">
          	Hier kannst du dir einen Überblick über die eingegebenen Themen verschaffen. 
            Klicke hierzu auf die Titel der Arbeiten, um die Details zu lesen.
          	Ab dem <?=datum($phases[3],3)?> kannst du dich für die einzelnen Arbeiten einschreiben.
             <a href="#">Ist es schon 12 Uhr?</a>
          </p><br /> 
        <?
				}
				if ($phase == 4) { ?>
					<p class="meldung">
          	Bis am <?=datum($phases[5],3)?> kannst du dich noch bei nicht vergebenen Arbeiten ein-
            bzw. bei Mehrfachbuchungen austragen.
          </p><br />
        <?
				}
				
// SQL fragment to require at least one missing supervision (type 1 = main, type 2 = secondary)
$missingExpr = "(
  NOT EXISTS (SELECT 1 FROM supervisions s1 WHERE s1.thesis = t.id AND s1.type = 1)
  OR
  NOT EXISTS (SELECT 1 FROM supervisions s2 WHERE s2.thesis = t.id AND s2.type = 2)
)";

				
$sql = "SELECT t.*, MIN(a.class) AS main_class 
        FROM thesis t
        JOIN authors a ON t.id = a.thesis
        WHERE t.status > 0";

// Nach Jahr filtern
$selected_year = !empty($search->year) ? $years[$search->year] : $year;
$sql .= " AND t.year = '".$selected_year."'";

// Nach Abteilung filtern
if (!empty($search->section)) {
    $sql .= " AND t.section = ".$search->section;
}

// Nach Klasse filtern
if (!empty($search->class)) {
    $sql .= " AND a.class = '".$search->class."'";
}

if ($filterMissing) {
    $sql .= " AND " . $missingExpr;
}

$sql .= " GROUP BY t.id ORDER BY main_class, t.title";

// Nach Lehrer filtern
if (isset($_GET['teacher'])) {
    $teacher = read_get('teacher');
    $teacher = empty($teacher) ? $session->user_id : $teacher;

    if ($teacher == $session->user_id || $adminmodus) {
        $teacher_field = is_numeric($teacher) ? 'id' : 'token';
        $this_teacher = Teacher::find_by($teacher_field, $teacher);

        if ($phase < 5) {
            $mythesis = $this_teacher->get_thesis();  // Alle, für die sich der Lehrer interessiert
        } elseif ($phase == 5) {
            $mythesis = $this_teacher->get_thesis(1); // Nur definitiv zugeteilte
        }

        if (!empty($mythesis)) {
            $sql = "SELECT t.*, MIN(a.class) AS main_class
                    FROM thesis t 
                    JOIN authors a ON t.id = a.thesis
                    WHERE t.id IN ".get_in_brackets($mythesis, 'thesis')."
                    ".($filterMissing ? " AND ".$missingExpr : "")."
                    GROUP BY t.id 
                    ORDER BY t.id";
        } else {
            if (empty($search->year)) {
                message($this_teacher->full_name().' hat sich noch für keine IDPA/SA eingetragen', 1);
            }
        }
    } else {
        message('Du bist leider nicht berechtigt, die Listen anderer Lehrpersonen einzusehen.', 1);
    }
}

$thesises = Thesis::find_by_sql($sql);
				zeigmir(count($thesises).' Arbeiten total');

// Thesis ohne Hauptbetreuung:
$sql_c1 = "SELECT COUNT(DISTINCT t.id) AS count_no_main_supervision
FROM thesis t
LEFT JOIN supervisions s ON t.id = s.thesis AND s.type = 1
WHERE s.thesis IS NULL
AND EXISTS (
    SELECT 1 
    FROM supervisions s2 
    WHERE s2.thesis = t.id AND s2.type = 2) AND t.status > 0 AND t.year = '".$selected_year."'";

$thesis_without_main_supervision = $db->get_value($db->query($sql_c1),'count_no_main_supervision');
zeigmir('Bei '.$thesis_without_main_supervision.' Arbeiten fehlt nur noch die Hauptbetreuung.');

// Thesis ohne Gegenbetreuung:
$sql_c2 = "SELECT COUNT(DISTINCT t.id) AS count_no_secondary_supervision
FROM thesis t
LEFT JOIN supervisions s ON t.id = s.thesis AND s.type = 2
WHERE s.thesis IS NULL
AND EXISTS (
    SELECT 1 
    FROM supervisions s2 
    WHERE s2.thesis = t.id AND s2.type = 1) AND t.status > 0 AND t.year = '".$selected_year."'";

$thesis_without_secondary_supervision = $db->get_value($db->query($sql_c2),'count_no_secondary_supervision');
zeigmir('Bei '.intval($thesis_without_secondary_supervision).' Arbeiten fehlt nur noch die Gegenbetreuung.');

$sql_c3 = "SELECT COUNT(DISTINCT t.id) AS count_both_supervisions
FROM thesis t
WHERE EXISTS (
    SELECT 1 
    FROM supervisions s 
    WHERE s.thesis = t.id AND s.type = 1 AND t.status > 0 AND t.year = '".$selected_year."'
)
AND EXISTS (
    SELECT 1 
    FROM supervisions s2 
    WHERE s2.thesis = t.id AND s2.type = 2 AND t.year = '".$selected_year."'
)";

$thesis_with_supervision = $db->get_value($db->query($sql_c3),'count_both_supervisions');
zeigmir($thesis_with_supervision.' Arbeiten haben Haupt- und Gegenbetreuung.');


$sql_c4 = "SELECT COUNT(DISTINCT t.id) AS count_no_supervision
FROM thesis t
WHERE NOT EXISTS (
    SELECT 1 
    FROM supervisions s 
    WHERE s.thesis = t.id AND s.type IN (1, 2)
) AND t.status > 0 AND t.year = '".$selected_year."'";

$thesis_with_no_supervision = $db->get_value($db->query($sql_c4),'count_no_supervision');
zeigmir('Bei '.$thesis_with_no_supervision.' fehlen noch Haupt- und Gegenbetreuung.');

zeigmir('=> '.($thesis_with_no_supervision + $thesis_without_main_supervision).' Hauptkorrekturen fehlen.');
zeigmir('=> '.($thesis_with_no_supervision + $thesis_without_secondary_supervision).' Gegenkorrekturen fehlen.');


				$sql = "SELECT * FROM teachers WHERE status > 0 ORDER BY token";
				$all_teachers = Teacher::find_by_sql($sql);

				// Thesis auflisten
				if (!empty($thesises)) {
					?>
          <table class="thesis_table thesis_head">
          	<tr>
            	<td class="thesis_title thesis_head">
              	&nbsp;<a style="color:white;" href="<?=$_SERVER['PHP_SELF']?>">
                <?=(isset($_GET['teacher']) ? 'IDPA/SA von '.$this_teacher->full_name() : 'alle IDPA/SA')?>
                </a>
              </td>
              <td class="thesis_teacher thesis_head">Hauptkorr.</td>
              <td class="thesis_teacher thesis_head">Gegenkorr.</td>
            </tr>
          </table>
          <?
$old_class = '';
foreach ($thesises as $thesis) {
    // Verwende die "main_class", die durch die SQL-Abfrage bestimmt wurde
    if ($thesis->main_class != $old_class) { ?>
        <table class="class_table">
            <tr>
                <td class="class_title"><?=$thesis->main_class?></td>
            </tr>
        </table>
        <?php
    }
    $thesis->display();
    $old_class = $thesis->main_class;
}				} else {
					echo "<p>Keine Arbeiten gefunden.</p>";
				}
				?>
                  
				<hr />
				<a href="index.php">Zurück zur Startseite</a>

      </div> <!-- right -->
    </div> <!-- content -->

    <?php include('i_template/i_footer.php'); ?>
  <?=$phase?>
  </div> <!-- mutterschiff -->
</div>
</body>
</html>

<?php if (isset($db)) { $db->close_connection(); } ?>