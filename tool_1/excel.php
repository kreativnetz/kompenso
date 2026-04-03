<?php require_once('../includes/initialize.php'); ?>
<? if (!$session->is_logged_in(2)) redirect_to('index.php'); ?>
<?php require('i_template/i_head.php'); ?>

<script type="text/javascript">
  function selecttxt(objId) {
  if (document.selection) {
  var range = document.body.createTextRange();
  range.moveToElementText(document.getElementById(objId));
  range.select();
  }
  else if (window.getSelection) {
  var range = document.createRange();
  range.selectNode(document.getElementById(objId));
  window.getSelection().addRange(range);
  }
  }
</script>

<style type="text/css">
  table td {
    vertical-align:top;
    border: 1px solid #EEE;
  }
</style>

<body>
<div id="container">

<?
if (isset($_GET['year'])) {
  $this_year = $_GET['year'];
} else {
  $this_year = $year_key;
}
?>
<?php include('i_template/i_header.php'); ?>

  <div id="mutterschiff">

      <select name="year" onchange="window.location = 'excel.php?year=' + this.value">
        <?
        foreach($years as $key => $year) {
          ?>
          <option value="<?=$key?>" <?=$key == $this_year ? "selected='selected'" : ""?>><?=$year?></option>
          <?
        } 
        ?>
      </select>


    <div id="content">
      
      <div id="left">

        <?
      
        $sql  = "SELECT t.year, t.id, t.title, t.description, a.last_name, a.first_name, t.section, a.class, t.subject1, t.subject2, t.teacher1, t.teacher2 ";
        $sql .= "FROM thesis AS t, authors AS a ";
        $sql .= "WHERE t.id = a.thesis AND t.year = '".$years[$this_year]."' AND t.status > 0 ";
        $sql .= "ORDER BY t.section";
  
        $result_set = $db->query($sql); ?>
        
        <a href="" onClick="selecttxt('excel_tabelle'); return false;">alles markieren</a>
        
        <table id="excel_tabelle" cellpadding="0" cellspacing="0">
          
          <?
          $alt_thesis = 0;

          while ($row = $db->fetch_array($result_set)) {
          
            // Anzahl Autoren
            $sql = "SELECT count(id) AS anzahl FROM authors WHERE thesis = ".$row['id'];
            $result_set_authors = $db->query($sql);
            $anzahl = $db->get_value($result_set_authors, 'anzahl');
          
            // Lehrer einlesen
            $sql  = "SELECT token, type FROM teachers AS t, supervisions AS s ";
            $sql .= "WHERE t.id = s.teacher AND s.thesis = ".$row['id']." AND s.status = 1";
            
            $result_set_teacher = $db->query($sql);
            $haupt = '';
            $gegen = '';
            while ($row_teacher = $db->fetch_array($result_set_teacher)) {
              if ($row_teacher['type'] == 1) $haupt = $row_teacher['token'];
              if ($row_teacher['type'] == 2) $gegen = $row_teacher['token'];
            }
            
            ?>
        <tr>
              <td><?=$row['year']?></td>
              <td><?=$row['id']?></td>
              <td><?=$row['title']?></td>
              <td><?=$row['description']?></td>
              <td><?=$row['first_name']?> <?=$row['last_name']?></td>
              <?php 
              // Use new structured $sections (expects ['name' => ..., 'prefix' => ...]).
              // Show first 3 chars of the section name; fallback to an em dash if missing.
              $secName = isset($sections[$row['section']]['name']) ? (string)$sections[$row['section']]['name'] : '';
              ?>
              <td><?= $secName !== '' ? mb_substr($secName, 0, 3) : '—' ?></td>
              <td><?=$row['class']?></td>
              <td><?=(!is_numeric($row['subject1']) ? $row['subject1'] : '')?></td>
              <td><?=$row['teacher1']?></td>
              <td></td>
              <td><?=(!is_numeric($row['subject2']) ? $row['subject2'] : '')?></td>
              <td><?=$row['teacher2']?></td>
              <td></td>
              <td></td>
              <td></td>
              <td><?=$haupt?></td>
              <td><?=(!empty($haupt)&&$alt_thesis!=$row['id'] ? str_replace(',','.', $entschaedigung_haupt[$anzahl]) : '')?></td>
              <td><?=$gegen?></td>
              <td><?=(!empty($gegen)&&$alt_thesis!=$row['id'] ? str_replace(',','.', $entschaedigung_gegen[$anzahl]) : '')?></td>

          </tr>            
          <?
            // damit die Entschädigung nicht mehrfach angezeigt wird:
            $alt_thesis = $row['id'];
          }
          ?>        
        </table>           

            <hr />
            <a href="index.php">Zurück zur Startseite</a><br />
      </div>  <!-- left -->
      
      <div id="right">

      </div> <!-- right -->
    </div> <!-- content -->

    <?php include('i_template/i_footer.php'); ?>
  
  </div> <!-- mutterschiff -->
</div>
</body>
</html>

<?php if (isset($db)) { $db->close_connection(); } ?>