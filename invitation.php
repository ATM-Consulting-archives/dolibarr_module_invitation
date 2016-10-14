<?php

	require 'config.php';
	
	dol_include_once('/invitation/class/invitation.class.php');
    dol_include_once('/core/lib/agenda.lib.php');
    dol_include_once('/comm/action/class/actioncomm.class.php');
	dol_include_once('/user/class/usergroup.class.php');
	
	$langs->load('invitation@invitation');
	
	$action = GETPOST('action');
	$fk_object = GETPOST('fk_action');
	
	$object = new ActionComm($db);
    $object->fetch($fk_object);
	$object->fetch_optionals();

	$PDOdb=new TPDOdb;

	$fk_user_author = $object->author->id;
	$admin_right = ($fk_user_author == $user->id);
	
	switch($action) {
	
		case 'create_bill':
			
			$mesgs = TInvitation::createBills($PDOdb, $object->id, GETPOST('type'));
			
			setEventMessage($mesgs);
			
			break;
	
		case 'set-product':
			
			$object->array_options['options_fk_product'] = (int)GETPOST('fk_product');
			echo $object->insertExtraFields();
			
			exit;
			
			break;
		
		case 'send':
			
			if($admin_right) _action_send($object,$action);
					
			break;
		
		case 'setStatut':
		
			$invitation=new TInvitation;
			if($invitation->load($PDOdb, GETPOST('id')) && ($admin_right || $invitation->fk_user = $user->id)) {
				$invitation->setStatut($PDOdb, GETPOST('statut'),GETPOST('answer'));
				
			}
			//setEventMessage($langs->trans('InvitationStatutChanged'));
			break;
		
			break;
		case 'deleteinvitation':
			
			$invitation=new TInvitation;
			if($invitation->load($PDOdb, GETPOST('id')) && ($admin_right || $invitation->fk_user = $user->id)) {
				$invitation->delete($PDOdb);
				
			}
			setEventMessage($langs->trans('RemoveInvitationDone'));
			break;
		case 'remove_pending':
			if($admin_right) {
				TInvitation::removePending($PDOdb, $fk_object);
				setEventMessage($langs->trans('RemovePendingInvitationDone'));
			}
			break;
		
		case 'adduser':
			if($admin_right) {
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
			}
			break;
		
	}


	_card($PDOdb, $object,$action,$fk_user_author);

function _action_send(&$object,$action) {
	global $db,$langs,$conf,$user,$mysoc;
	
	$id = $object->id;
	$actiontypecode='AC_PROP';
	$trigger_name='INVITATION_SENTBYMAIL';
	$paramname='fk_action';
	$mode='emailfrominvitation';
	
	/*
	 * Add file in email form
	 */
	if (GETPOST('addfile'))
	{
		$trackid = GETPOST('trackid','aZ09');
		
	    require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	
		// Set tmp user directory
		$vardir=$conf->user->dir_output."/".$user->id;
		$upload_dir_tmp = $vardir.'/temp';             // TODO Add $keytoavoidconflict in upload_dir path
	
		dol_add_file_process($upload_dir_tmp, 0, 0, 'addedfile', '', null, $trackid);
		$action='presend';
	}
	
	/*
	 * Remove file in email form
	 */
	if (! empty($_POST['removedfile']) && empty($_POST['removAll']))
	{
		$trackid = GETPOST('trackid','aZ09');
	    
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	
		// Set tmp user directory
		$vardir=$conf->user->dir_output."/".$user->id;
		$upload_dir_tmp = $vardir.'/temp';             // TODO Add $keytoavoidconflict in upload_dir path
	
		// TODO Delete only files that was uploaded from email form. This can be addressed by adding the trackid into the temp path then changing donotdeletefile to 2 instead of 1 to say "delete only if into temp dir"
		// GETPOST('removedfile','alpha') is position of file into $_SESSION["listofpaths"...] array.
		dol_remove_file_process(GETPOST('removedfile','alpha'), 0, 1, $trackid);   // We do not delete because if file is the official PDF of doc, we don't want to remove it physically
		$action='presend';
	}
	
	/*
	 * Remove all files in email form
	 */
	if (GETPOST('removAll'))
	{
		$trackid = GETPOST('trackid','aZ09');
		
	    $listofpaths=array();
		$listofnames=array();
		$listofmimes=array();
	    $keytoavoidconflict = empty($trackid)?'':'-'.$trackid;
		if (! empty($_SESSION["listofpaths".$keytoavoidconflict])) $listofpaths=explode(';',$_SESSION["listofpaths".$keytoavoidconflict]);
		if (! empty($_SESSION["listofnames".$keytoavoidconflict])) $listofnames=explode(';',$_SESSION["listofnames".$keytoavoidconflict]);
		if (! empty($_SESSION["listofmimes".$keytoavoidconflict])) $listofmimes=explode(';',$_SESSION["listofmimes".$keytoavoidconflict]);
	
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
		$formmail = new FormMail($db);
		$formmail->trackid = $trackid;
		
		foreach($listofpaths as $key => $value)
		{
			$pathtodelete = $value;
			$filetodelete = $listofnames[$key];
			$result = dol_delete_file($pathtodelete,1); // Delete uploded Files
	
			$langs->load("other");
			setEventMessages($langs->trans("FileWasRemoved",$filetodelete), null, 'mesgs');
	
			$formmail->remove_attached_files($key); // Update Session
		}
	}
	
	/*
	 * Send mail
	 */
	if (($action == 'send' || $action == 'relance') && ! $_POST['addfile'] && ! $_POST['removAll'] && ! $_POST['removedfile'] && ! $_POST['cancel'] && !$_POST['modelselected'])
	{
		$trackid = GETPOST('trackid','aZ09');
		$subject='';$actionmsg='';$actionmsg2='';
		
	    if (! empty($conf->dolimail->enabled)) $langs->load("dolimail@dolimail");
		$langs->load('mails');
	
		$result = 1;	    
    	$sendtosocid=0;
    	$thirdparty = $mysoc;

		if ($object->id > 0)
		{
			if (trim($_POST['sendto']))
			{
				// Recipient is provided into free text
				$sendto = trim($_POST['sendto']);
				$sendtoid = 0;
			}
			elseif ($_POST['receiver'] != '-1')
			{
				// Recipient was provided from combo list
				if ($_POST['receiver'] == 'thirdparty') // Id of third party
				{
					$sendto = $thirdparty->email;
					$sendtoid = 0;
				}
				else	// Id du contact
				{
					$sendto = $thirdparty->contact_get_property((int) $_POST['receiver'],'email');
					$sendtoid = $_POST['receiver'];
				}
			}
			if (trim($_POST['sendtocc']))
			{
				$sendtocc = trim($_POST['sendtocc']);
			}
			elseif ($_POST['receivercc'] != '-1')
			{
				// Recipient was provided from combo list
				if ($_POST['receivercc'] == 'thirdparty')	// Id of third party
				{
					$sendtocc = $thirdparty->email;
				}
				else	// Id du contact
				{
					$sendtocc = $thirdparty->contact_get_property((int) $_POST['receivercc'],'email');
				}
			}
	
			if (dol_strlen($sendto))
			{
				$langs->load("commercial");
	
				$from = $_POST['fromname'] . ' <' . $_POST['frommail'] .'>';
				$replyto = $_POST['replytoname']. ' <' . $_POST['replytomail'].'>';
				$message = $_POST['message'];
				$sendtobcc= GETPOST('sendtoccc');
				if ($mode == 'emailfromproposal') $sendtobcc .= (empty($conf->global->MAIN_MAIL_AUTOCOPY_PROPOSAL_TO) ? '' : (($sendtobcc?", ":"").$conf->global->MAIN_MAIL_AUTOCOPY_PROPOSAL_TO));
				if ($mode == 'emailfromorder')    $sendtobcc .= (empty($conf->global->MAIN_MAIL_AUTOCOPY_ORDER_TO) ? '' : (($sendtobcc?", ":"").$conf->global->MAIN_MAIL_AUTOCOPY_ORDER_TO));
				if ($mode == 'emailfrominvoice')  $sendtobcc .= (empty($conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO) ? '' : (($sendtobcc?", ":"").$conf->global->MAIN_MAIL_AUTOCOPY_INVOICE_TO));
				if ($mode == 'emailfromsupplierproposal') $sendtobcc .= (empty($conf->global->MAIN_MAIL_AUTOCOPY_SUPPLIER_PROPOSAL_TO) ? '' : (($sendtobcc?", ":"").$conf->global->MAIN_MAIL_AUTOCOPY_SUPPLIER_PROPOSAL_TO));
				if ($mode == 'emailfromsupplierorder')    $sendtobcc .= (empty($conf->global->MAIN_MAIL_AUTOCOPY_SUPPLIER_ORDER_TO) ? '' : (($sendtobcc?", ":"").$conf->global->MAIN_MAIL_AUTOCOPY_SUPPLIER_ORDER_TO));
				if ($mode == 'emailfromsupplierinvoice')  $sendtobcc .= (empty($conf->global->MAIN_MAIL_AUTOCOPY_SUPPLIER_INVOICE_TO) ? '' : (($sendtobcc?", ":"").$conf->global->MAIN_MAIL_AUTOCOPY_SUPPLIER_INVOICE_TO));
					
				$deliveryreceipt = $_POST['deliveryreceipt'];
	
				if ($action == 'send' || $action == 'relance')
				{
					if (dol_strlen($_POST['subject'])) $subject = $_POST['subject'];
					$actionmsg2=$langs->transnoentities('MailSentBy').' '.$from.' '.$langs->transnoentities('To').' '.$sendto;
					if ($message)
					{
						$actionmsg=$langs->transnoentities('MailSentBy').' '.$from.' '.$langs->transnoentities('To').' '.$sendto;
						if ($sendtocc) $actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('Bcc') . ": " . $sendtocc);
						$actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('MailTopic') . ": " . $subject);
						$actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('TextUsedInTheMessageBody') . ":");
						$actionmsg = dol_concatdesc($actionmsg, $message);
					}
				}
	
				// Create form object
				include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
				$formmail = new FormMail($db);
				$formmail->trackid = $trackid;      // $trackid must be defined
	            
				$attachedfiles=$formmail->get_attached_files();
				$filepath = $attachedfiles['paths'];
				$filename = $attachedfiles['names'];
				$mimetype = $attachedfiles['mimes'];
	
	
				// Feature to push mail sent into Sent folder
				if (! empty($conf->dolimail->enabled))
				{
					$mailfromid = explode("#", $_POST['frommail'],3);	// $_POST['frommail'] = 'aaa#Sent# <aaa@aaa.com>'	// TODO Use a better way to define Sent dir.
					if (count($mailfromid)==0) $from = $_POST['fromname'] . ' <' . $_POST['frommail'] .'>';
					else
					{
						$mbid = $mailfromid[1];
	
						/*IMAP Postbox*/
						$mailboxconfig = new IMAP($db);
						$mailboxconfig->fetch($mbid);
						if ($mailboxconfig->mailbox_imap_host) $ref=$mailboxconfig->get_ref();
	
						$mailboxconfig->folder_id=$mailboxconfig->mailbox_imap_outbox;
						$mailboxconfig->userfolder_fetch();
	
						if ($mailboxconfig->mailbox_save_sent_mails == 1)
						{
	
							$folder=str_replace($ref, '', $mailboxconfig->folder_cache_key);
							if (!$folder) $folder = "Sent";	// Default Sent folder
	
							$mailboxconfig->mbox = imap_open($mailboxconfig->get_connector_url().$folder, $mailboxconfig->mailbox_imap_login, $mailboxconfig->mailbox_imap_password);
							if (FALSE === $mailboxconfig->mbox)
							{
								$info = FALSE;
								$err = $langs->trans('Error3_Imap_Connection_Error');
								setEventMessages($err,$mailboxconfig->element, null, 'errors');
							}
							else
							{
								$mailboxconfig->mailboxid=$_POST['frommail'];
								$mailboxconfig->foldername=$folder;
								$from = $mailfromid[0] . $mailfromid[2];
								$imap=1;
							}
	
						}
					}
				}
	
				// Send mailInvitation
				require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
				$mailfile = new CMailFile($subject,$sendto,$from,$message,$filepath,$mimetype,$filename,$sendtocc,$sendtobcc,$deliveryreceipt,-1,'','',$trackid);
				if ($mailfile->error)
				{
					setEventMessage($mailfile->error, 'errors');
					$action='presend';
				}
				else
				{
					$result=$mailfile->sendfile();
					
					if ($result)
					{
						$error=0;
	
						// FIXME This must be moved into a trigger for action $trigger_name
						if (! empty($conf->dolimail->enabled))
						{
							$mid = (GETPOST('mid','int') ? GETPOST('mid','int') : 0);	// Original mail id is set ?
							if ($mid)
							{
								// set imap flag answered if it is an answered mail
								$dolimail=new DoliMail($db);
								$dolimail->id = $mid;
								$res=$dolimail->set_prop($user, 'answered',1);
					  		}
							if ($imap==1)
							{
								// write mail to IMAP Server
								$movemail = $mailboxconfig->putMail($subject,$sendto,$from,$message,$filepath,$mimetype,$filename,$sendtocc,$folder,$deliveryreceipt,$mailfile);
								if ($movemail) setEventMessages($langs->trans("MailMovedToImapFolder",$folder), null, 'mesgs');
								else setEventMessages($langs->trans("MailMovedToImapFolder_Warning",$folder), null, 'warnings');
					 	 	}
					 	}
	
						// Initialisation of datas
						$object->socid			= $sendtosocid;	// To link to a company
						$object->sendtoid		= $sendtoid;	// To link to a contact/address
						$object->actiontypecode	= $actiontypecode;
						$object->actionmsg		= $actionmsg;  // Long text
						$object->actionmsg2		= $actionmsg2; // Short text
						$object->trackid        = $trackid;
						$object->fk_element		= $object->id;
						$object->elementtype	= $object->element;
	
						// Call of triggers
						include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
						$interface=new Interfaces($db);
						$result=$interface->run_triggers($trigger_name,$object,$user,$langs,$conf);
						if ($result < 0) {
							$error++; $errors=$interface->errors;
						}
						// End call of triggers
	
						if ($error)
						{
							dol_print_error($db);
						}
						else
						{
							// Redirect here
							// This avoid sending mail twice if going out and then back to page
							$mesg=$langs->trans('MailSuccessfulySent',$mailfile->getValidAddress($from,2),$mailfile->getValidAddress($sendto,2));
							setEventMessages($mesg, null, 'mesgs');
							if ($conf->dolimail->enabled) header('Location: '.$_SERVER["PHP_SELF"].'?'.($paramname?$paramname:'id').'='.$object->id.'&'.($paramname2?$paramname2:'mid').'='.$parm2val);
							else header('Location: '.$_SERVER["PHP_SELF"].'?'.($paramname?$paramname:'id').'='.$object->id);
							exit;
						}
					}
					else
					{
						$langs->load("other");
						$mesg='<div class="error">';
						if ($mailfile->error)
						{
							$mesg.=$langs->trans('ErrorFailedToSendMail',$from,$sendto);
							$mesg.='<br>'.$mailfile->error;
						}
						else
						{
							$mesg.='No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
						}
						$mesg.='</div>';
	
						setEventMessages($mesg, null, 'warnings');
						$action = 'presend';
					}
				}
			}
			else
			{
				$langs->load("errors");
				setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("MailTo")), null, 'warnings');
				dol_syslog('Try to send email with no recipiend defined', LOG_WARNING);
				$action = 'presend';
			}
		}
		else
		{
			$langs->load("other");
			setEventMessages($langs->trans('ErrorFailedToReadEntity',$object->element), null, 'errors');
			dol_syslog('Failed to read data of object id='.$object->id.' element='.$object->element);
			$action = 'presend';
		}
	
	}
	
	
	
}
	
function _card(&$PDOdb,&$object,$action) {
	global $langs, $user, $conf, $db;
	
	$fk_user_author = $object->author->id;
	$admin_right = ($fk_user_author == $user->id);
	
	llxHeader('',$langs->trans('Invitations'));
	
    $head = actions_prepare_head($object);
    dol_fiche_head($head, 'invitation', $langs->trans('Action'), 0, 'action');
	_header($object);
	
	$form = new Form($db);
	
	if($admin_right) {
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
	}
	
	$allow_bill = _list($PDOdb, $object);
	
	if(!$admin_right) {
		null;
	}
	else if ($action == 'presend')
	{
	
		$ref = dol_sanitizeFileName($object->ref);
		include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		// Define output language
		$outputlangs = $langs;

		print '<div class="clearboth"></div>';
		print '<br>';
		print load_fiche_titre($langs->trans('SendInvitationByMail'));

		dol_fiche_head('');

		// Create form object
		include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
		$formmail = new FormMail($db);
		$formmail->param['langsmodels']=(empty($newlang)?$langs->defaultlang:$newlang);
		$formmail->fromtype = 'user';
		$formmail->fromid = $user->id;
		$formmail->fromname = $user->getFullName($langs);
		$formmail->frommail = $user->email;
		$formmail->trackid='invitation'.$object->id;
		if (! empty($conf->global->MAIN_EMAIL_ADD_TRACK_ID) && ($conf->global->MAIN_EMAIL_ADD_TRACK_ID & 2))	// If bit 2 is set
		{
			include DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
			$formmail->frommail=dolAddEmailTrackId($formmail->frommail, 'pro'.$object->id);
		}
		$formmail->withfrom = 1;
		
		$formmail->withto = GETPOST("sendto") ? GETPOST("sendto") : $user->email;
		$formmail->withtocc = GETPOST("sendtocc") ? GETPOST("sendtocc") : array();
		
		$TInvitation =TInvitation::getPending($PDOdb, $object->id,true);
		$TMailInvite=array();
		foreach($TInvitation as &$inv) {
			if(!empty($inv->user->email))$TMailInvite[] = $inv->user->email;
		}
		
		
		$formmail->withtoccc = implode(',',$TMailInvite);
		
		$formmail->withtopic = $object->label;
		
		$formmail->withfile = 2;
		$formmail->withbody = 1;
		$formmail->withdeliveryreceipt = 1;
		$formmail->withcancel = 1;

		// Tableau des substitutions
		$formmail->setSubstitFromObject($object);
		$formmail->substit['__PROPREF__'] = $object->ref; // For backward compatibility

		// Find the good contact adress
		$custcontact = '';
		$contactarr = array();
		$contactarr = $object->liste_contact(- 1, 'external');

		if (is_array($contactarr) && count($contactarr) > 0) {
			foreach ($contactarr as $contact) {
				if ($contact ['libelle'] == $langs->trans('TypeContact_propal_external_CUSTOMER')) {	// TODO Use code and not label
					$contactstatic = new Contact($db);
					$contactstatic->fetch($contact ['id']);
					$custcontact = $contactstatic->getFullName($langs, 1);
				}
			}

			if (! empty($custcontact)) {
				$formmail->substit['__CONTACTCIVNAME__'] = $custcontact;
			}
		}
		$link_event = dol_buildpath('/invitation/invitation.php?fk_action='.$object->id,1);

		if (! empty($conf->global->FCKEDITOR_ENABLE_MAIL))$formmail->withbody = $object->note."<br />". $langs->trans('ViewInvitation').' <a href="'.$link_event.'">'.$link_event.'</a>';
		else $formmail->withbody = $object->note."\n\n". $langs->trans('ViewInvitation').' '.$link_event;

		// Tableau des parametres complementaires
		$formmail->param['action'] = 'send';
		$formmail->param['models'] = '';
		$formmail->param['models_id']=GETPOST('modelmailselected','int');
		$formmail->param['id'] = $object->id;
		$formmail->param['returnurl'] = $_SERVER["PHP_SELF"] . '?fk_action=' . $object->id;
		// Init list of files
		if (GETPOST("mode") == 'init') {
			$formmail->clear_attached_files();
			$formmail->add_attached_files($file, basename($file), dol_mimetype($file));
		}

		print $formmail->get_form();

	}
	else{
		echo '<div class="tabsAction">
			<a class="butActionDelete" href="?fk_action='.$object->id.'&action=remove_pending">'.$langs->trans('RemoveAllUserInvitationPending').'</a>
			<a class="butAction" href="?fk_action='.$object->id.'&action=presend">'.$langs->trans('SendInvitationTouser').'</a>';
			
		if($allow_bill)echo '<a class="butAction" href="?fk_action='.$object->id.'&action=create_bill">'.$langs->trans('CreateBillConfirmed').'</a>
			<a class="butAction" href="?fk_action='.$object->id.'&action=create_bill&type=present">'.$langs->trans('CreateBillPresent').'</a>';
		echo '</div>';
		
		
	}
	
	
	dol_fiche_end();
	
	llxFooter();
	
}
	
function _list(&$PDOdb, $object){
	global $conf,$user,$db,$langs,$form;
	$fk_user_author = $object->author->id;
	$admin_right = ($fk_user_author == $user->id);
	
	$PDOdb=new TPDOdb;
	
	$Tinvitation = TInvitation::getAllForAction($PDOdb, $object->id);
	
	if(empty($Tinvitation)) return false;
	
	$formCore=new TFormCore;
	
	echo '<br /><table class="border" width="100%">';
	echo '<tr class="entete"><td>'.$langs->trans('User').'</td><td>'.$langs->trans('Statut').'</td><td>'.$langs->trans('AnswerDate').'</td><td>'.$langs->trans('Note').'</td><td>&nbsp;</td></tr>';
	
	$allow_bill = false;
	
	dol_include_once('/core/class/html.formfile.class.php');
	$formfile = new FormFile($db);
	
	foreach($Tinvitation as &$inv) {
		
		$u=new User($db);
		$u->fetch($inv->fk_user);
		
		if($u->socid>0)$allow_bill = true;
		
		echo '<tr><td>';
		
		echo ($admin_right ? $u->getNomUrl(1) : $u->getFullName($langs));
		if (($inv->fk_facture>0) && ($admin_right || $inv->fk_user == $user->id) ){
			echo '<a href="'.dol_buildpath('/compta/facture.php?facid='.$inv->fk_facture,1).'">'.img_picto('','object_bill').'</a>';			
		}
		
		echo '</td>
			<td>'.$inv->libStatut(true);
		
			if($admin_right || $inv->fk_user == $user->id) {
		
				if($inv->statut!=0 && $admin_right) echo ' <a href="javascript:setStatutInvitation('.$inv->getId().',0)">'.img_picto($langs->trans('SetStatutInvitation0'), 'stcomm0.png').'</a>';
				if($inv->statut!=1) echo ' <a href="javascript:setStatutInvitation('.$inv->getId().',1)">'.img_picto($langs->trans('SetStatutInvitation1'), 'stcomm2.png').'</a>';
				if($inv->statut!=2) echo ' <a href="javascript:setStatutInvitation('.$inv->getId().',2)">'.img_picto($langs->trans('SetStatutInvitation2'), 'stcomm1.png').'</a>';
				if($inv->statut!=3 && $admin_right) echo ' <a href="javascript:setStatutInvitation('.$inv->getId().',3)">'.img_picto($langs->trans('SetStatutInvitation3'), 'warning.png').'</a>';
				if($inv->statut!=4 && $admin_right) echo ' <a href="javascript:setStatutInvitation('.$inv->getId().',4)">'.img_picto($langs->trans('SetStatutInvitation4'), 'stcomm3.png').'</a>';
					
			}
			
		echo '</td>
			<td>'.$inv->get_date('date_validation').'</td>
			<td>'.( $inv->fk_user == $user->id ? $formCore->texte('', 'answer_'.$inv->getId(), $inv->answer, 30, 255) : $inv->answer).'</td>
			<td>'.($admin_right ? '<a href="?fk_action='.$object->id.'&action=deleteinvitation&id='.$inv->getId().'">'.img_delete().'</a>' : '') .'</td>
		</tr>';
	}
	
	?>
	<script>
		function setStatutInvitation(fk_invitation, statut) {
			
			var url = "?fk_action=<?php echo $object->id ?>&id=" +fk_invitation+"&action=setStatut&statut="+statut;
			if($("#answer_"+fk_invitation).length>0)url+="&answer="+encodeURIComponent($("#answer_"+fk_invitation).val());
			
			document.location.href=url;
		}
		
		
	</script>
	<?php
	
	echo '</table>';
	
	return $allow_bill;
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

	echo '<tr><td class="tdtop">'.$langs->trans("Product").'</td><td colspan="3">';
	dol_include_once('/core/class/extrafields.class.php');
    $extrafields=new ExtraFields($db);
	$extrafields->fetch_name_optionals_label('actioncomm');
	echo $extrafields->showInputField('fk_product', $object->array_options['options_fk_product']);
	
	?>
	<script type="text/javascript">
		$('#options_fk_product').change(function() {
			var fk_product = $(this).val();
			
			$.ajax({
				url:'<?php echo dol_buildpath('/invitation/invitation.php',1) ?>'
				,data:{
					action:'set-product'
					,fk_product:fk_product
					,fk_action:<?php echo $object->id ?>
				}
			});
		});
	</script>
	<?php
	
	echo '</td></tr>';
	
	echo '</table>';
	
	
}
