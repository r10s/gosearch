<?php
/*******************************************************************************
Go Search Engine - User Profiles
****************************************************************************//**

User Profiles

@author Björn Petersen

*******************************************************************************/

class Action_Profile extends G_Html
{
	/***********************************************************************//**
	The function creates a large, hexadecimal number that may be used as an 
	auth token eg. in cookies.
	***************************************************************************/
	static function createAuthToken()
	{
		// we use different sources to avoid problems if one has a security lack ...
		return sha1(mt_rand().mt_rand().rand().rand().time());
	}

	public function handleRequest()
	{
		$this->hasUserAccessOrDie();

		$reg = G_Db::db('registry');
		$msgTop = '';
		
		// create form
		$form = new G_Form(array(
			'action'		=>	'?a=profile', 
			'ok'			=>	'change_pw',
			'autocomplete'	=>	'off',
			'input' 		=>	array(
				'old_pw'	=>	array('type'=>'password', 'required'=>true),
				'pw1' 		=>	array('type'=>'password', 'label'=>'input_new_pw', 'required'=>true),
				'pw2'		=>	array('type'=>'password', 'label'=>'input_repeat_pw', 'required'=>true),
			)
		));

		
		// save form to database?
		if( $_GET['p'] == 'logoutotherdev' )
		{
			// TODO: Currently, we only change the f_authtoken; this avoids using saved logins in the go_auth cookie.
			// however, maybe we should also terminate existing sessions.
			$msgTop = G_Local::_('profile_otherloggedout');
			if( $reg->sql->exec(
							"UPDATE t_user
								SET	f_authtoken=".$reg->sql->quote(Action_Profile::createAuthToken())."
							  WHERE f_username=".$reg->sql->quote(G_Session::get('username'))) != 1 )
			{
				$this->msgAndDie('Cannot update user.');
			}
		}
		else if( $form->isOk() )
		{
			if( ($record = $reg->readRecord('t_user', array('f_username'=>G_Session::get('username'))))===false ) { $this->msgAndDie('cannot read user.'); }
			
			$db_pw = $record['f_pw'];
			$old_pw   = $form->param['input']['old_pw']['value'];
			$new_pw   = $form->param['input']['pw1']['value'];
			if( crypt($old_pw, $db_pw) == $db_pw  )
			{
				if( $reg->sql->exec(
								"UPDATE t_user
									SET f_pw=".$reg->sql->quote(crypt($new_pw)).",
										f_authtoken=".$reg->sql->quote(Action_Profile::createAuthToken())."
								  WHERE f_username=".$reg->sql->quote(G_Session::get('username'))) != 1 )
				{
					$this->msgAndDie('Cannot update user.');
				}
				
				echo $this->renderHtmlStart(array('title'=>G_Local::_('menu_profile')));
					echo G_Html::renderH1(G_Local::_('change_pw'));
					echo G_Html::renderP(G_Local::_('profile_pw_changed'));
				echo $this->renderHtmlEnd();
				exit();
			}
			else
			{
				$form->addError(G_Local::_('login_badcombination'));
			}
		}
		
		// render page		
		echo $this->renderHtmlStart(array('title'=>G_Local::_('menu_profile')));
		
			echo G_Html::renderH1(G_Local::_('menu_profile'));
			
			if( $msgTop != '' )
			{
				echo G_Html::renderP($msgTop, 'class="err"');
			}
			
			echo '<table class="blank">';
				echo '<tr><td>' . G_Local::_('input_username') . ':</td><td>' . G_Session::get('username').'<span class="shy">'.G_Tools::onHost().'</span>' . '</td></tr>';
				echo '<tr><td>' . G_Local::_('input_admin')    . ':</td><td>' . G_Local::_(G_Session::get('user_is_admin')?'yes':'no')  . '</td></tr>' ;
			echo '</table>';
			
			echo G_Html::renderP(G_Html::renderA('?a=profile&p=logoutotherdev', G_Local::_('profile_logoutother').'...'));
			
			echo G_Html::renderH1(G_Local::_('change_pw'));
			
			echo $form->render();	

			/* not yet needed ...
			echo G_Html::renderH1(G_Local::_('profile_imextitle'));

			echo '<p>';
				echo G_Html::renderA('?a=profile&p=export', G_Local::_('profile_export').'...');
			echo '</p>';
			echo '<p>';
				echo G_Html::renderA('?a=profile&p=import', G_Local::_('profile_import').'...');
			echo '</p>';
			echo '<p>';
				echo G_Html::renderA('?a=profile&p=delete', G_Local::_('profile_delete').'...');
			echo '</p>';
			*/
			
		echo $this->renderHtmlEnd();
	}
};
