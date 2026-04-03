<?php require_once('../includes/initialize.php'); ?>
<?php if (!$session->is_logged_in()) { redirect_to("index.php"); } ?>
<?
	foreach ($mythesis as $my) {
		book($my,1,0);
	}
?>
<?php require('i_template/i_head.php'); ?>

<body>
	Hallo! Was geht? Alles geht!


</body>

<?php if (isset($db)) { $db->close_connection(); } ?>               
