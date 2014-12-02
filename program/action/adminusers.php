<?php
/*******************************************************************************
Go Search Engine - Admin
****************************************************************************//**

User Settings

@author Björn Petersen

*******************************************************************************/

class Action_Adminusers extends G_HtmlAdmin
{
	/***********************************************************************//**
	Called if the user opens the page ?a=adminusers
	***************************************************************************/
	public function handleRequest()
	{
		// check for admin access
		$this->hasAdminAccessOrDie();

		// get user information
		$this->reg = G_Db::db('registry');
		
		// see what to do ...
		switch( $_GET['p'] )
		{
			case 'edit': case 'add':	$this->handleEditRequest();		break;
			default:					$this->handleOverviewRequest();	break;
		}
	}

	
	/***********************************************************************//**
	Check, if a given username exists. For logging in, we match case-sensitive
	(although we store the case, we do not allow two user names which differ
	only in upper/lowercase).
	***************************************************************************/
	private function usernameExists($username)
	{
		$this->reg->sql->query("SELECT * FROM t_user WHERE f_username LIKE " . $this->reg->sql->quote($username));
		return $this->reg->sql->next_record()? true : false;
	}
	
	private function handleEditRequest()
	{	
		$p = $_GET['p'];
		$editusername = $_GET['user'];
		
		$form = new G_Form(array(
			'action'			=> '?a=adminusers&p=' . $p . ($p=='edit'? '&user='.urlencode($editusername) : ''),
			'cancel'			=> true,
			'delete'			=> $p=='edit'? 'admin_deluser' : false,
			'input' 			=> array(
				'newusername'	=> array(
										'value'=>$editusername, 
										'required'=>($p!='edit'), 
										'type'=>($p=='edit'?'readonly':'text'), 
										'right'=>'<span class="shy">'.G_Tools::onHost().'</span>',
										'validate'=>'username',
										'label'=>'input_username',
										),
				'admin'			=> array('type'=>'checkbox'),
				'pw' 			=> array(
										'required'=>($p!='edit'), 
										'label'=>($p=='edit'?'change_pw':'input_pw'),
										),
			)
		));

		if( $p=='edit' ) {
			$h1 = G_Local::_('admin_edituser');	
			$title = G_Local::_('admin_users_title').'/'.htmlspecialchars($editusername);
		}
		else {
			$h1 = G_Local::_('admin_adduser'); 
			$title = $h1; 
		}
		
		// see what to do with the form
		$load_from_db = false;
		if( $form->shallCancel() )
		{
			// cancel
			G_Html::redirect('?a=adminusers');
		}
		else if( $form->isOk() )
		{
			if( $p=='add' )
			{
				// add a user
				$newusername = $form->param['input']['newusername']['value'];
				if( $this->usernameExists($newusername) ) {
					$form->addError(G_Local::_('login_userexists', '<i>'.htmlspecialchars($newusername).'</i>'));
				}
				else if( !$this->reg->addRecord('t_user', array(
					'f_username'	=> $newusername,
					'f_admin'		=> $form->param['input']['admin']['value'],
					'f_pw'			=> crypt($form->param['input']['pw']['value']),
					'f_authtoken'	=> Action_Profile::createAuthToken(),
				)) ) {
					$this->msgAndDie('Cannot add user: '.$this->reg->getLastError());
				}
				else {
					G_Html::redirect('?a=adminusers');
				}
			}
			else if( $p=='edit' )
			{
				// edit a user: edit password
				if( $form->param['input']['pw']['value']!='' )	{ 
					$update['f_pw'] = crypt($form->param['input']['pw']['value']);
					$update['f_authtoken'] = Action_Profile::createAuthToken();
				}
				
				// edit a user: edit admin state
				if( $form->param['input']['admin']['value'] ) {
					$update['f_admin'] = 1;
				}
				else if( !$form->param['input']['admin']['value'] ) {
					if( $editusername == G_Session::get('username') ) {
						$form->addError(G_Local::_('admin_cannoteditself'));
					}
					else {
						$update['f_admin'] = 0;
					}
				}
				
				// edit a user: save!
				if( !$form->hasErrors() ) {
					if( !$this->reg->updateRecord('t_user', array('f_username'=>$editusername), $update) ) {
						$this->msgAndDie('Cannot edit user: '.$this->reg->getLastError());
					}
					else {
						G_Html::redirect('?a=adminusers');
					}
				}
			}
		}
		else if( $form->shallDelete() )
		{
			if( $editusername == G_Session::get('username') ) {
				$form->addError(G_Local::_('admin_cannoteditself'));
				$load_from_db  = true;
			}
			else {
				$this->reg->deleteRecord('t_user', array('f_username'=>$editusername));
				G_Html::redirect('?a=adminusers');
			}
		}
		else
		{
			// load form from database
			if( $p=='edit' ) {
				$load_from_db = true;
			}
		}
		
		if( $load_from_db ) {
			if( !($record = $this->reg->readRecord('t_user', array('f_username'=>$editusername))) ) {	
				$this->msgAndDie('Cannot read user: '.$this->reg->getLastError());
			}
			$form->param['input']['admin']['value'] = $record['f_admin']? 1 : 0;
		}
		
		echo $this->renderHtmlStart(array('title'=>$title));
			echo G_Html::renderH1($h1);
			echo $form->render();
		echo $this->renderHtmlEnd();
	}
	
	
	/***********************************************************************//**
	Handle the user settings formular.
	***************************************************************************/
	public function handleOverviewRequest()
	{
		// gather user names
		$html_admin = '';
		$html_normal = '';
		
		$admin_cnt = 0;
		$normal_cnt = 0; $add_norm = true;
		$this->reg->sql->query("SELECT * FROM t_user ORDER BY f_username;");
		while( ($user=$this->reg->sql->next_record()) )
		{
			$html_user = G_Html::renderA('?a=adminusers&p=edit&user='.urlencode($user['f_username']), htmlspecialchars($user['f_username']));
			if( $user['f_admin'] ) {
				$admin_cnt++;
				$html_admin .= ($html_admin? ', ' : '') . $html_user;
			}
			else {
				$normal_cnt++;
				if( $add_norm ) {
					$html_normal .= ($html_normal? ', ' : '') . $html_user;
				}
			}
		}
		
		// handle settings
		$form = new G_Form(array(
			'action'			=> '?a=adminusers',
			'ok'				=> 'button_apply', // by convention: OK=save and close dialog; Apply=save, but stay in dialog
			'input' 			=> array(
				'login_required'=> array('type'=>'select', 'value'=>G_Registry::iniRead('login_required', G_Html::LOGIN_REQUIRED_DEFAULT), 'label'=>'menu_login', 
																					'options'=>array( /*numbers in quotes: force associative array*/
																						'0'=>'admin_loginnotreq',
																						'1'=>'admin_userloginreq', 
																						//'2'=>'admin_adminloginreq' -- we do not support this by default; this may be a bad user experience, disturbing users simply should be deleted 
																					)),
			)
		));

		if( $form->isOk() )
		{
			G_Registry::iniWrite('login_required',	$form->param['input']['login_required']['value']);
			// a "settings saved" message may be confusing, as the message stays on the screen even on subsequent (then, unsaved) edits.
			// so the message should diappear in this case ...
			// ... however, we just rely on slow internet connections, so that the user really will get the information sth. was submitted to the server ;-)
		}
		
		// render page
		echo $this->renderHtmlStart(array('title'=>G_Local::_('admin_users_title')));

			echo G_Html::renderH1(G_Local::_cnt('admin_admin_s', $admin_cnt));
			
			echo G_Html::renderP($html_admin);
			
			echo G_Html::renderH1(G_Local::_cnt('admin_normuser_s', $normal_cnt));
			
			if( $html_normal != '' ) {
				echo G_Html::renderP($html_normal);
			}
			echo G_Html::renderP(G_Html::renderA('?a=adminusers&p=add', G_Local::_('admin_adduser').'...'));

			echo G_Html::renderH1(G_Local::_('settings'));
			echo $form->render();
			
		echo $this->renderHtmlEnd();
	}

};
