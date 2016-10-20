<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */

if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');

}


dol_include_once('/invitation/class/invitation.class.php');

$PDOdb=new TPDOdb;

$o=new TInvitation($db);
$o->init_db_by_vars($PDOdb);
/*
$resultset = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."c_email_templates WHERE type_template='invitation_send'");
if($obj = $db->fetch_object($resultset)) {
	null;
}
else{
	$db->query("INSERT INTO ".MAIN_DB_PREFIX."c_email_templates(entity,module,label,content)
			VALUES (".$conf->entity.",'invitation_send','')");
	
}
 * TODO model de mail invitation ?
*/