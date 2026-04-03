<?php require_once('../includes/initialize.php'); ?>
<?php require('i_template/i_head.php'); ?>

</head>

<body>
<div id="container">

<?php include('i_template/i_header.php'); ?>

  <div id="mutterschiff">

    <div id="content">
      
      <div id="left" style="width:47%; float:left;">

        <?

	        if (isset($_GET['register']) || isset($_POST['register_submit']) || isset($_GET['sendpassword'])) { 
					 // zeige das nicht an... 
					} else { ?>
        
	        	<hr />
		      	<h2 style="background-image:url(layout/icon_student.jpg)">Lernende</h2>			
					
          	<?
						if ($phase == 0) { ?>
            	<h3>Das Einschreibefenster ist erst ab dem<br /><?=datum($phases[1])?> geöffnet.</h3>
              <?
            }
						if ($phase == 4) { ?>
            	<h3>Das Einschreibefenster ist geschlossen.</h3>
              <?
            }
						if (($phase > 0 && $phase < 4) || $session->is_logged_in(2)) {  // ********** VOR PHASE 4 können Lernende Themen eingeben ***************** ?>
							<h3>Thema eingeben</h3>
            	<p>
              	<? if ($phase == 1) { ?>
	            		Hier können Sie noch bis zum <b><?=datum($phases[2],1)?></b> Ihr Thema für die<br><?=$year?> eingeben:<br /><br />
                <? } ?>
              	<? if ($phase == 2 || $phase == 3) { ?>
	            		Geben Sie so schnell wie möglich Ihr Thema ein, wenn Sie das noch nicht gemacht haben.<br /><br />
                <? } ?>
								<input type="button" onClick="location.href='themeneingabe.php'" value="Thema eingeben">
            	</p>            
          		<?
              } ?>
          
     				<?
            if ($phase > 0 && $phase < 3) {  // ********** VOR PHASE 3 können Lernende Themen bearbeiten ***************** ?>

              <h3>Thema bearbeiten</h3>
              <p>
                Falls Sie Ihr Thema bereits eingegeben haben, können Sie dieses jetzt noch verändern, indem Sie hier den Code, den Sie per E-Mail erhalten haben, eingeben.<br /><br />
                <?=edit('code','','250px','text','Code','id="code"')?><br /><br />
                <input type="button" onClick="location.href='themeneingabe.php?thesis='+getElementById('code').value+'&edit'" value="Thema verändern">
              </p><br />
	  	        <?
						} ?>
	        	
            <hr />

				<?
				} ?>				
         
      </div>  <!-- left -->
      <?       
      if ($phase > 1 || !isset($_GET['admin']) || $session->is_logged_in(3) || isset($_POST['login_submit'])) {  // ********** Ab Phase 2 können LP sich einloggen ***************** ?>
  
        
        <div id="right" <?=(isset($_GET['register']) ? '' : 'style="width:47%; float:right;')?>">
  
          <hr />
          <h2 style="background-image:url(layout/icon_teacher.jpg)">Lehrpersonen</h2>
    
          <?
					$login_form_teacher = true;
          include('../includes/login.php');
           
          if ($session->is_logged_in()) { ?>
    
            <h3>IDPA/SA</h3>
            <p><a href="thesis.php"><?=icon('list3')?> IDPA/SA-Liste</a></p>
            <p><a href="thesis.php?teacher"><?=icon('list_ok')?> Meine Arbeiten</a></p>
  
            <? if ($session->is_logged_in(2)) { ?>
              <p><a href="liste.php"><?=icon('list3')?> Betreuungsliste für Lehrpersonen</a></p>          
              <p><a href="liste_lernende.php"><?=icon('list3')?> Betreuungsliste für Lernende</a></p>          
              <p><a href="excel.php"><?=icon('excel')?> Excel-Export</a></p>          
              <p><a href="students.php"><?=icon('female')?> Schüler-Liste</a></p>         
              <p><a href="teachers.php"><?=icon('school')?> Lehrer-Liste</a></p>        	
            <? } ?>
    
          <h3>Profil</h3>
            <p><a href="profile.php"><?=icon('password')?> Passwort ändern</a></p>
          
          <?
          } ?>
  
          <hr />
                  
          <?
          if (isset($_GET['register']) || isset($_POST['register_submit']) || isset($_GET['sendpassword'])) { ?>
            <a href="index.php">Zurück zur Startseite</a>
          <?
          } ?>
            
  
        </div> <!-- right -->
      <? } ?>
    </div> <!-- content -->

    <?php include('i_template/i_footer.php'); ?>
  
  </div> <!-- mutterschiff -->
</div>
</body>
</html>

<?php if (isset($db)) { $db->close_connection(); } ?>