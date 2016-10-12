<?php

class TInvitation extends TObjetStd {

    function __construct() {
        $this->set_table(MAIN_DB_PREFIX.'invitation');
        $this->add_champs('fk_action,fk_user',array('type'=>'integer','index'=>true));
        $this->add_champs('statut', 'type=entier;');
        $this->add_champs('answer', array('type'=>'string','index'=>true));
		$this->add_champs('date_validation','type=date;');
       
        $this->_init_vars();

        $this->start();

		global $langs;

		$this->TStatut=array(
			0=>$langs->trans('PendingInvitation')
			,1=>$langs->trans('InvitationConfirmed')
			,2=>$langs->trans('InvitationWontCome')
			,3=>$langs->trans('InvitationWasntPresent')
			,4=>$langs->trans('InvitationPresent')
		);

	}
	
	function libStatut() {
		
		$r =  $this->TStatut[$this->statut];
		
		return $r;
	}
	
	function loadByUserAction(&$PDOdb, $fk_user,$fk_action) {
		
		$PDOdb->Execute("SELECT rowid FROM ".$this->get_table()." WHERE fk_action=".$fk_action." AND fk_user=".$fk_user);
		if($obj = $PDOdb->Get_line()) {
			return $this->load($PDOdb, $obj->rowid);
		}
		return false;
	}
	
	static function addUser(&$PDOdb,$fk_action, &$TUser, $default_statut= 0) {
		
		foreach($TUser as $fk_user) {
			
			$i=new TInvitation;
			
			if(!$i->loadByUserAction($PDOdb, $fk_user,$fk_action)) {
				$i->fk_user = $fk_user;
				$i->fk_action = $fk_action ;
				$i->statut = $default_statut;
				
				$i->save($PDOdb);
				
			}
			
			
		}
		
	}
	
	static function removePending(&$PDOdb, $fk_action) {
		$PDOdb->Execute("DELETE FROM ".MAIN_DB_PREFIX."invitation WHERE fk_action=".$fk_action." AND statut=0");
	}
	
	static function getAllForAction(&$PDOdb, $fk_action) {
		
		$Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."invitation WHERE fk_action=".$fk_action);
		$TInvitation=array();
		foreach($Tab as $row) {
			
			$i=new TInvitation;
			$i->load($PDOdb, $row->rowid);
			
			$TInvitation[] = $i;
		}
		
		return $TInvitation;
		
	}
	
}