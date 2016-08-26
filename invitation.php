<?php

	require 'config.php';
	
	dol_include_once('/core/lib/agenda.lib.php');
    dol_include_once('/comm/action/class/actioncomm.class.php');
	
	llxHeader('',$langs->trans('Invitations'));
	
	$fk_object = GETPOST('fk_action');
	
	$object = new ActionComm($db);
    $object->fetch($fk_object);
    $head = actions_prepare_head($object);
    dol_fiche_head($head, 'invitation', $langs->trans('Company'), 0, 'action');

	_header($object);

	_card($object);

	dol_fiche_end();
	
	llxFooter();
	
function _card($object) {
	
	
	
}
	
function _header(&$object) {
	global $conf,$user,$db,$langs,$form;
	
	
	print '<table class="border" width="100%">';
	print '<tr><td width="30%">'.$langs->trans("Ref").'</td><td colspan="3">';
	print $form->showrefnav($object, 'fk_action', $linkback, ($user->societe_id?0:1), 'id', 'ref', '');
	print '</td></tr>';
	print '<tr><td>'.$langs->trans("Title").'</td><td colspan="3">'.dol_htmlentities($object->label).'</td></tr>';
	
	$rowspan=4;
	if (empty($conf->global->AGENDA_DISABLE_LOCATION)) $rowspan++;

	// Date start
	print '<tr><td width="30%">'.$langs->trans("DateActionStart").'</td><td colspan="3">';
	if (! $object->fulldayevent) print dol_print_date($object->datep,'dayhour');
	else print dol_print_date($object->datep,'day');
	if ($object->percentage == 0 && $object->datep && $object->datep < ($now - $delay_warning)) print img_warning($langs->trans("Late"));
	print '</td>';
	print '</tr>';

	// Date end
	print '<tr><td>'.$langs->trans("DateActionEnd").'</td><td colspan="3">';
    if (! $object->fulldayevent) print dol_print_date($object->datef,'dayhour');
	else print dol_print_date($object->datef,'day');
	if ($object->percentage > 0 && $object->percentage < 100 && $object->datef && $object->datef < ($now- $delay_warning)) print img_warning($langs->trans("Late"));
	print '</td></tr>';

	// Status
	print '<tr><td class="nowrap">'.$langs->trans("Status").' / '.$langs->trans("Percentage").'</td><td colspan="3">';
	print $object->getLibStatut(4);
	print '</td></tr>';

	// Location
    if (empty($conf->global->AGENDA_DISABLE_LOCATION))
	{
		print '<tr><td>'.$langs->trans("Location").'</td><td colspan="3">'.$object->location.'</td></tr>';
	}
	
	
	// Description
	print '<tr><td class="tdtop">'.$langs->trans("Description").'</td><td colspan="3">';
	print dol_htmlentitiesbr($object->note);
	print '</td></tr>';

	print '</table>';
	
	
}
