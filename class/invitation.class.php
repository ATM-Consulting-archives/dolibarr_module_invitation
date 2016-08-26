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
			,1=>$langs->trans('Confirmed')
			,2=>$langs->trans('WontCome')
			,3=>$langs->trans('WasntPresent')
			,4=>$langs->trans('Present')
		);

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