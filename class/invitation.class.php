<?php

class TInvitation extends TObjetStd {

    function __construct() {
        $this->set_table(MAIN_DB_PREFIX.'invitation');
        $this->add_champs('fk_action,fk_user,statut,fk_facture',array('type'=>'integer','index'=>true));
        $this->add_champs('answer', array('type'=>'string'));
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

		$this->user=new stdClass;
		$this->user=new stdClass;
		
		$this->date_validation = 0;
	}
	function setStatut($PDOdb, $statut, $answer = '') {
		
		global $user,$db,$langs,$conf;
		
		$this->date_validation = ($statut != 0 ? time() : 0);
		$this->answer = $answer;
		$this->statut = $statut;
		$this->save($PDOdb);
		
		if($this->load_action()>0) {
			$this->action->fetch_userassigned();
			
			if(($this->statut == 0 || $this->statut == 2 || $this->statut == 3)
				&& !empty($this->action->userassigned[$this->fk_user])) {
				
				unset($this->action->userassigned[$this->fk_user]);
					
			}
			elseif(empty($this->action->userassigned[$this->fk_user])) {
				$this->action->userassigned[$this->fk_user] = array('id'=>$this->fk_user, 'mandatory'=>1, 'transparency'=>0);
			}
			
			$this->action->update($user);
		}
		
		
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
	
	function load_action() {
		global $db,$user,$conf,$langs;
		dol_include_once('/comm/action/class/actioncomm.class.php');
		$this->action=new ActionComm($db);
		$res = $this->action->fetch( $this->fk_action );
		$this->action->fetch_optionals();
		
		return $res;
		
	}
	
	function load_user() {
		global $db,$conf,$langs;
		
		$this->user=new User($db);
		return $this->user->fetch($this->fk_user);
		
	}
	
	function createBill(&$PDOdb) {
		
		global $db,$conf,$langs,$user;
		
		dol_include_once('/compta/facture/class/facture.class.php');
		dol_include_once('/product/class/product.class.php');
		
		$this->load_user();
		$this->load_action();
		
		if($this->user->socid>0 && $this->action->array_options['options_fk_product']>0) {
			
			$product=new Product($db);
			if($product->fetch( $this->action->array_options['options_fk_product'] )<=0) return -11;
			
			$original_entity = $conf->entity;
				
			$facture=new Facture($db);
			$facture->date = time();
			$facture->socid = $this->user->socid;
			
			$conf->entity = $this->user->entity;
			$res = $facture->create($user);
			$conf->entity = $original_entity;
			
			if($res>0) {

				$facture->addline($this->action->label, $product->price, 1, $product->tva_tx, 0,0, $product->id);
				$facture->validate($user);

				$this->fk_facture = $facture->id;
				$this->save($PDOdb);
				
				return $facture->id;	
			}
			else{
				
				return $res;
				
			}		
			
		}
		else{
			return -111;
		}
		
		
		
		
	}
	
	static function createBills(&$PDOdb, $fk_action, $type) {
		global $langs;
		$sql="SELECT rowid FROM ".MAIN_DB_PREFIX."invitation WHERE fk_action=".$fk_action;
		$sql.=" AND statut IN (".($type=='present' ? '4' : '3,1,4').")";
		
		$Tab = $PDOdb->ExecuteAsArray($sql);
		
		$r='';
		
		foreach($Tab as $row) {
			$i=new TInvitation;
			$i->load($PDOdb, $row->rowid);
			
			/*if($i->fk_facture>0) {
				null; // already billed
			}
			else*/ if($i->createBill($PDOdb)>0) {
				$r.=$langs->trans('BillCreatedForInvitation', $i->user->login);
			}
			else{
				$r.=$langs->trans('BillCreatedForInvitationError', $i->user->login);
			}
			
		}
		
		
		return $r;
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
	
	static function getPending(&$PDOdb, $fk_action, $loadUser=false) {
		$Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."invitation WHERE fk_action=".$fk_action." AND statut=0");
		
		$TInvitation=array();
		foreach($Tab as $row) {
			$i=new TInvitation;
			$i->load($PDOdb, $row->rowid);
			
			if($loadUser) {
				$i->load_user();
			}
			
			$TInvitation[] = $i;
			
		}
		return $TInvitation;		
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