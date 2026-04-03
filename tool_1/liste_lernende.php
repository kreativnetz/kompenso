<?php require_once('../includes/initialize.php'); ?>
<? if (!$session->is_logged_in(2)) redirect_to('index.php'); ?>

<style type="text/css">
  body { font-family: Arial, sans-serif; margin:0; background-color:#fff; }
  #container { width: 18cm; margin: 20px auto; }
  .class-block { margin-bottom: 28px; }
  .class-title { margin: 18px 0 8px; font-size: 14pt; border-bottom: 2px solid #333; padding-bottom: 4px; display: flex; align-items: center; gap: 10px; }
  .table-container { overflow-x:auto; width:100%; }
  table { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:9.5pt; }
  th, td { text-align:left; padding:10px 12px; border:1px solid #ddd; vertical-align:top; }
  th { background:#f2f2f2; }
  tr:nth-child(even) { background:#f9f9f9; }
  tr:hover { background:#f1f1f1; }
  .meta { color:#666; font-size:9pt; margin:0 0 12px; }
  .btn { border:1px solid #bbb; background:#fafafa; padding:6px 10px; border-radius:6px; cursor:pointer; font-size:9.5pt; }
  .btn:hover { background:#f0f0f0; }
  .btn:active { transform: translateY(1px); }
  .copy-ok { color:#2e7d32; font-size:9pt; margin-left:6px; display:none; }
  @media print {
    .noprint { display:none !important }
    tr { page-break-inside: avoid; }
    .class-block { page-break-inside: avoid; }
    .class-title { page-break-after: avoid; }
  }
</style>

<body>
<div id="container">

  <?
  // Jahr bestimmen wie im bestehenden File
  if (isset($_GET['year'])) {
    $this_year = $_GET['year'];
  } else {
    $this_year = $year_key;
  }

  // ---- 1a) E-Mails pro Klasse vorbereiten (distinct) ----
  $emailsByClass = [];

  $sql_emails = "
    SELECT 
      a.class,
      tchr.email
    FROM authors AS a
    JOIN thesis  AS t   ON t.id = a.thesis
    LEFT JOIN supervisions AS s   ON t.id = s.thesis
    LEFT JOIN teachers     AS tchr ON s.teacher = tchr.id
    WHERE t.year = '".$db->escape_value($years[$this_year])."'
      AND s.status = 1
      AND tchr.email IS NOT NULL
      AND tchr.email <> ''
    GROUP BY a.class, tchr.email
    ORDER BY a.class ASC
  ";
  $rs_emails = $db->query($sql_emails);
  while ($er = $db->fetch_array($rs_emails)) {
    $cls = trim($er['class']);
    $mail = trim($er['email']);
    if ($cls === '' || $mail === '') continue;
    if (!isset($emailsByClass[$cls])) $emailsByClass[$cls] = [];
    $emailsByClass[$cls][$mail] = true; // set für Eindeutigkeit
  }
  // jetzt $emailsByClass['FM25a'] = ['a@b.ch'=>true, 'c@d.ch'=>true, ...]

  // ---- 1b) Lernenden-E-Mails pro Klasse vorbereiten (distinct) ----
  $studentEmailsByClass = [];

  $sql_student_emails = "
    SELECT
      a.class,
      a.email
    FROM authors AS a
    JOIN thesis AS t ON t.id = a.thesis
    WHERE t.year = '".$db->escape_value($years[$this_year])."'
      AND a.email IS NOT NULL
      AND a.email <> ''
    GROUP BY a.class, a.email
    ORDER BY a.class ASC
  ";
  $rs_student_emails = $db->query($sql_student_emails);
  while ($sr = $db->fetch_array($rs_student_emails)) {
    $cls = trim($sr['class']);
    $mail = trim($sr['email']);
    if ($cls === '' || $mail === '') continue;
    if (!isset($studentEmailsByClass[$cls])) $studentEmailsByClass[$cls] = [];
    $studentEmailsByClass[$cls][$mail] = true;
  }
  ?>

  <div class="noprint" style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
    <select name="year" onchange="window.location='liste_lernende.php?year=' + this.value">
      <? foreach($years as $key => $year) { ?>
        <option value="<?=$key?>" <?=$key == $this_year ? "selected='selected'" : ""?>><?=$year?></option>
      <? } ?>
    </select>
  </div>

  <h3>Betreuungsliste nach Klassen – <?=$years[$this_year]?></h3>

  <div class="table-container">
    <?
    // ---- 2) Hauptliste: eine Zeile pro Lernende/r ----
    $sql = "
      SELECT 
        a.class,
        a.first_name,
        a.last_name,
        t.title,
        MAX(CASE WHEN s.type = 1 THEN tchr.token END) AS haupt,
        MAX(CASE WHEN s.type = 2 THEN tchr.token END) AS gegen,
        MAX(CASE WHEN s.type = 1 THEN tchr.email END) AS haupt_email,
        MAX(CASE WHEN s.type = 2 THEN tchr.email END) AS gegen_email
      FROM authors AS a
      JOIN thesis  AS t   ON t.id = a.thesis
      LEFT JOIN supervisions AS s   ON t.id = s.thesis
      LEFT JOIN teachers     AS tchr ON s.teacher = tchr.id
      WHERE t.year = '".$db->escape_value($years[$this_year])."'
        AND s.status = 1
      GROUP BY a.id, a.class, a.first_name, a.last_name, t.title
      ORDER BY a.class ASC, a.last_name ASC, a.first_name ASC
    ";

    $result_set = $db->query($sql);

    $current_class = null;
    $opened_table = false;

    while ($row = $db->fetch_array($result_set)) {

      // Klassenwechsel → Block und Tabellenkopf neu öffnen
      if ($current_class !== $row['class']) {
        if ($opened_table) {
          // Vorherige Tabelle sauber schliessen
          echo "</tbody></table></div>";
        }
        $current_class = $row['class'];
        $opened_table = true;

        // E-Mail-Liste dieser Klasse als kommagetrennter String
        $emailList = '';
        if (isset($emailsByClass[$current_class])) {
          $emailList = implode(', ', array_keys($emailsByClass[$current_class]));
        }

        // Lernenden-E-Mail-Liste dieser Klasse
        $studentEmailList = '';
        if (isset($studentEmailsByClass[$current_class])) {
          $studentEmailList = implode(', ', array_keys($studentEmailsByClass[$current_class]));
        }

        // Klassen-Übertitel + Copy-Button + Tabellenkopf
        ?>
        <div class="class-block">
          <div class="class-title">
            <span>Klasse <?=$current_class?></span>
            <? if ($emailList !== '') { ?>
              <button class="btn noprint" type="button"
                      onclick="copyEmails(this)"
                      data-emails="<?=htmlspecialchars($emailList, ENT_QUOTES, 'UTF-8')?>">
                Lehrpersonen-E-Mails kopieren
              </button>
              <span class="copy-ok noprint">Kopiert ✓</span>
            <? } ?>
            <? if ($studentEmailList !== '') { ?>
              <button class="btn noprint" type="button"
                      onclick="copyEmails(this)"
                      data-emails="<?=htmlspecialchars($studentEmailList, ENT_QUOTES, 'UTF-8')?>">
                Lernenden-E-Mails kopieren
              </button>
              <span class="copy-ok noprint">Kopiert ✓</span>
            <? } ?>
          </div>
          <table>
            <thead>
              <tr>
                <th width="28%">Lernende/r</th>
                <th width="52%">Titel der Arbeit</th>
                <th width="10%">HB</th>
                <th width="10%">GB</th>
              </tr>
            </thead>
            <tbody>
        <?
      }

      // Datenzeile (eine Person = eine Zeile)
      $name = trim($row['last_name'] . ' ' . $row['first_name']);
      ?>
      <tr>
        <td><?=$name?></td>
        <td><?=$row['title']?></td>
        <td><?=$row['haupt']?></td>
        <td><?=$row['gegen']?></td>
      </tr>
      <?
    }

    // Falls überhaupt Daten vorhanden waren, die letzte Tabelle schliessen
    if ($opened_table) {
      echo "</tbody></table></div>";
    } else {
      echo "<p>Keine Daten gefunden.</p>";
    }
    ?>
  </div>

</div>

<script>
// Kopierfunktion pro Klassen-Button
function copyEmails(btn) {
  const emails = btn.getAttribute('data-emails') || '';
  if (!emails) return;

  const okEl = btn.parentNode.querySelector('.copy-ok');

  // Clipboard API nutzen, mit Fallback
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(emails).then(() => {
      if (okEl) { okEl.style.display = 'inline'; setTimeout(()=> okEl.style.display='none', 1800); }
    }).catch(() => fallbackCopy(emails, okEl));
  } else {
    fallbackCopy(emails, okEl);
  }
}

function fallbackCopy(text, okEl) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.setAttribute('readonly', '');
  ta.style.position = 'absolute';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); } catch(e){}
  document.body.removeChild(ta);
  if (okEl) { okEl.style.display = 'inline'; setTimeout(()=> okEl.style.display='none', 1800); }
}
</script>

</body>
</html>

<?php if (isset($db)) { $db->close_connection(); } ?>