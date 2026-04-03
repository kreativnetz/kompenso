<?php require_once('../includes/initialize.php'); ?>
<?
if ($session->is_logged_in(2)) {
	// alles ok, falls Admin
} else { 
	$session->message('Sie sind nicht mehr eingeloggt.',1);
	redirect_to('index.php');
}

$search = new Search();

// Suche
if (isset($_GET['search_submit'])) {
	$search->section = read_get('section');
	$search->class = read_get('class');
	$search->year = read_get('year');
	$search->save();
}

?>
<?php require('i_template/i_head.php'); ?>

<body>

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
            	<? if ($session->is_logged_in(2)) { ?>
                <tr>
                  <td>Jahr</td>
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
                </td>
              </tr>
            </table>
          </form>
        </div>
        
				<?				
				
				// Schüler zusammensuchen
				
				$sql  = "SELECT authors.*, thesis.section, thesis.title, thesis.password AS code FROM authors, thesis WHERE thesis.status > 0 AND thesis.id = authors.thesis ";
				
				// nach Jahr
				if (!empty($search->year)) {
					$sql .= "AND year = '".$years[$search->year]."' ";
				} else {
					$sql .= "AND year = '".$year."' ";
				}

				// nach Abteilung
				if (!empty($search->section)) $sql .= "AND section = ".$search->section." ";
				
				// nach Klasse
				if (!empty($search->class)) $sql .= "AND class = '".$search->class."'";
								
				$sql .= " ORDER BY class, last_name, first_name";

				$authors = Author::find_by_sql($sql);
				
				// Thesis auflisten
				
				if (!empty($authors)) {
					?>
          <table class="thesis_table">
          	<tr>
            	<td class="thesis_head">Klasse</td>
              <td class="thesis_head">Nachname</td>
              <td class="thesis_head">Vorname</td>
              <td class="thesis_head">E-Mail</td>
              <td class="thesis_head">Handy</td>
              <td class="thesis_head">IDPA/SA</td>
            </tr>
          <?
					$old_class = '';
					$counter = 1;
					foreach ($authors as $author) {
						if ($author->class != $old_class) { 
							?>
				    		<tr>
				    			<td><?=$counter?></td>
                </tr>
				    		<tr>
      						<td colspan="6" class="class_title"><?=$author->class?></td>
                </tr>
              <?
 							$counter = 1;
						} else {
							$counter++;
						}
						?>
						<tr>
            	<td><?=$author->class?></td>
            	<td><?=$author->last_name?></td>
            	<td><?=$author->first_name?></td>
            	<td><a href="mailto:<?=$author->email?>"><?=$author->email?></a></td>
            	<td><?=$author->handy?></td>
            	<td><a href="themeneingabe.php?edit&thesis=<?=$author->code?>"><?=$author->title?></a></td>
            </tr>
						<?
						$old_class = $author->class;
					}
					?>
          </table>
          <?
				} else {
					echo "<p>Keine Arbeiten gefunden.</p>";
				}
				?>        
				<hr />
				<a href="index.php">Zurück zur Startseite</a>

      </div> <!-- right -->
    </div> <!-- content -->

    <?php include('i_template/i_footer.php'); ?>
  
  </div> <!-- mutterschiff -->
</div>
</body>
</html>

<?php if (isset($db)) { $db->close_connection(); } ?>