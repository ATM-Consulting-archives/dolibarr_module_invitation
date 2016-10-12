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
		
		case 'setStatut':
		
			$invitation=new TInvitation;
			if($invitation->load($PDOdb, GETPOST('id'))) {
				$invitation->statut = GETPOST('statut');
				$invitation->save($PDOdb);
				
			}
			//setEventMessage($langs->trans('InvitationStatutChanged'));
			break;
		
			break;
		case 'deleteinvitation':
			
			$invitation=new TInvitation;
			if($invitation->load($PDOdb, GETPOST('id'))) {
				$invitation->delete($PDOdb);
				
			}
			setEventMessage($langs->trans('RemoveInvitationDone'));
			break;
		case 'remove_pending':
			TInvitation::removePending($PDOdb, $fk_object);
			setEventMessage($langs->trans('RemovePendingInvitationDone'));
			
			break;
		
		case 'adduser':
			
			$TUser = array();
			$fk_user = (int)GETPOST('fk_user');
			$fk_usergroup = (int)GETPOST('fk_usergroup');
			
			if($fk_user>0)$TUser[] = $fk_user;
			if($fk_usergroup>0) {
				
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
	echo $form->select_dolusers(-1,'fk_user',1);
	echo $form->select_dolgroups(-1,'fk_usergroup',1);
	echo '</td><td>'.$formCore->btsubmit($langs->trans('Add'), 'bt_add').'</td></tr>';
	echo '</table>';
	
	$formCore->end();
	
	_list($object);
	
	echo '<div class="tabsAction">
		<a class="butActionDelete" href="?fk_action='.$object->id.'&action=remove_pending">'.$langs->trans('RemoveAllUserInvitationPending').'</a>
		<a class="butAction" href="?fk_action='.$object->id.'&action=send_mail">'.$langs->trans('SendInvitationTouser').'</a>
		<a class="butAction" href="?fk_action='.$object->id.'&action=create_bill">'.$langs->trans('CreateBillConfirmed').'</a>
		<a class="butAction" href="?fk_action='.$object->id.'&action=create_bill&type=present">'.$langs->trans('CreateBillPresent').'</a>
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
			<td>'.$inv->libStatut(true);
		
		if($inv->statut!=0) echo ' <a href="?fk_action='.$inv->fk_action.'&id='.$inv->getId().'&action=setStatut&statut=0">'.img_picto($langs->trans('SetStatutInvitation0'), 'stcomm0.png').'</a>';
		if($inv->statut!=1) echo ' <a href="?fk_action='.$inv->fk_action.'&id='.$inv->getId().'&action=setStatut&statut=1">'.img_picto($langs->trans('SetStatutInvitation1'), 'stcomm2.png').'</a>';
		if($inv->statut!=2) echo ' <a href="?fk_action='.$inv->fk_action.'&id='.$inv->getId().'&action=setStatut&statut=2">'.img_picto($langs->trans('SetStatutInvitation2'), 'stcomm1.png').'</a>';
		if($inv->statut!=3) echo ' <a href="?fk_action='.$inv->fk_action.'&id='.$inv->getId().'&action=setStatut&statut=3">'.img_picto($langs->trans('SetStatutInvitation3'), 'warning.png').'</a>';
		if($inv->statut!=4) echo ' <a href="?fk_action='.$inv->fk_action.'&id='.$inv->getId().'&action=setStatut&statut=4">'.img_picto($langs->trans('SetStatutInvitation4'), 'stcomm3.png').'</a>';
			
			
		echo '</td>
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
