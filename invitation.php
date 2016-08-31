<?php

	require 'config.php';
	
	dol_include_once('/invitation/class/invitation.class.php');
    dol_include_once('/core/lib/agenda.lib.php');
    dol_include_once('/comm/action/class/actioncomm.class.php');
	dol_include_once('/user/class/usergroup.class.php');
	
	$action = GETPOST('action');
	$fk_object = GETPOST('fk_action');
	
	$object = new ActionComm($db);
    $object->fetch($fk_object);

	$PDOdb=new TPDOdb;

	switch($action) {
		
		case 'adduser':
			
			$TUser = array();
			$fk_user = (int)GETPOST('fk_user');
			$fk_usergroup = (int)GETPOST('fk_usergroup');
			
			if(!empty($fk_user))$TUser[] = $fk_user;
			if(!empty($fk_usergroup)) {
				
				$g=new UserGroup($db);
				if($g->fetch($fk_usergroup)>0) {
					
					$Tab = $g->listUsersForGroup();
					foreach($Tab as &$user) {
						if($user->statut>0) {
							$TUser[] = $user->id;							
						}
					}
					
				}
				
			}
			
			TInvitation::addUser($PDOdb, $fk_object, $TUser);
			
			setEventMessage($langs->trans('UserAdded'));
			
			break;
		
	}


	_card($object);

	
function _card(&$object) {
	global $langs, $user, $conf, $db;
	
	llxHeader('',$langs->trans('Invitations'));
	
    $head = actions_prepare_head($object);
    dol_fiche_head($head, 'invitation', $langs->trans('Action'), 0, 'action');
	_header($object);
	
	$form = new Form($db);
	
	$formCore=new TFormCore('auto','formInvit', 'post');
	echo $formCore->hidden('action', 'adduser');
	echo $formCore->hidden('fk_action', $object->id);
	
	echo '<br /><table class="border" width="100%">';
	echo '<tr><td width="30%">'.$langs->trans("AddUserOrGroupUser").'</td><td>';
	echo $form->select_dolusers(-1,'fk_user');
	echo $form->select_dolgroups(-1,'fk_usergroup');
	echo '</td><td>'.$formCore->btsubmit($langs->trans('Add'), 'bt_add').'</td></tr>';
	echo '</table>';
	
	$formCore->end();
	
	_list($object);
	
	echo '<div class="tabsAction">
		<a class="butAction">'.$langs->trans('SendInvitationTouser').'</a>
		<a class="butAction">'.$langs->trans('CreateBillConfirmed').'</a>
		<a class="butAction">'.$langs->trans('CreateBillPresent').'</a>
	</div>';
	
	
	dol_fiche_end();
	
	llxFooter();
	
}
	
function _list($object){
	global $conf,$user,$db,$langs,$form;
	
	$PDOdb=new TPDOdb;
	
	$Tinvitation = TInvitation::getAllForAction($PDOdb, $object->id);
	
	if(empty($Tinvitation)) return false;
	
	echo '<br /><table class="border" width="100%">';
	echo '<tr class="entete"><td>'.$langs->trans('User').'</td><td>'.$langs->trans('Statut').'</td><td>&nbsp;</td></tr>';
	foreach($Tinvitation as &$inv) {
		
		$u=new User($db);
		$u->fetch($inv->fk_user);
		
		echo '<tr><td>'.$u->getNomUrl(1).'</td>
			<td>'.$inv->libStatut().'</td>
			<td><a href="?fk_action='.$object->id.'&action=deleteinvitation&id='.$inv->getId().'">'.img_delete().'</a></td>
		</tr>';
	}
	
	echo '</table>';
}	

function _header(&$object) {
	global $conf,$user,$db,$langs,$form;
	
	
	echo '<table class="border" width="100%">';
	echo '<tr><td width="30%">'.$langs->trans("Ref").'</td><td colspan="3">';
	echo $form->showrefnav($object, 'fk_action', $linkback, ($user->societe_id?0:1), 'id', 'ref', '');
	echo '</td></tr>';
	echo '<tr><td>'.$langs->trans("Title").'</td><td colspan="3">'.dol_htmlentities($object->label).'</td></tr>';
	
	$rowspan=4;
	if (empty($conf->global->AGENDA_DISABLE_LOCATION)) $rowspan++;

	// Date start
	echo '<tr><td width="30%">'.$langs->trans("DateActionStart").'</td><td colspan="3">';
	if (! $object->fulldayevent) echo dol_print_date($object->datep,'dayhour');
	else echo dol_print_date($object->datep,'day');
	if ($object->percentage == 0 && $object->datep && $object->datep < ($now - $delay_warning)) echo img_warning($langs->trans("Late"));
	echo '</td>';
	echo '</tr>';

	// Date end
	echo '<tr><td>'.$langs->trans("DateActionEnd").'</td><td colspan="3">';
    if (! $object->fulldayevent) echo dol_print_date($object->datef,'dayhour');
	else echo dol_print_date($object->datef,'day');
	if ($object->percentage > 0 && $object->percentage < 100 && $object->datef && $object->datef < ($now- $delay_warning)) echo img_warning($langs->trans("Late"));
	echo '</td></tr>';

	// Status
	echo '<tr><td class="nowrap">'.$langs->trans("Status").' / '.$langs->trans("Percentage").'</td><td colspan="3">';
	echo $object->getLibStatut(4);
	echo '</td></tr>';

	// Location
    if (empty($conf->global->AGENDA_DISABLE_LOCATION))
	{
		echo '<tr><td>'.$langs->trans("Location").'</td><td colspan="3">'.$object->location.'</td></tr>';
	}
	
	
	// Description
	echo '<tr><td class="tdtop">'.$langs->trans("Description").'</td><td colspan="3">';
	echo dol_htmlentitiesbr($object->note);
	echo '</td></tr>';

	echo '</table>';
	
	
}
