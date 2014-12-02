<?php
/*******************************************************************************
Go Search Engine - Login Handling
****************************************************************************//**

Login Handling

@author Björn Petersen

*******************************************************************************/

class Action_Login extends G_Html
{
	function handleRequest()
	{
		$reg = G_Db::db('registry');

		// create form
		$init_username = isset($_GET['username'])? $_GET['username'] : $_POST['username']; // forms are always POSTed, but we allow user name initialisation via GET
		$form = new G_Form(array(
			'action'		=>	'?a=login',
			'input' 		=>	array(
				'username'	=>	array('value'=>$init_username, 'required'=>true, 'validate'=>'usernmae', 'right'=>G_Tools::onHost()), 
				'pw'		=>	array('required'=>true, 'type'=>'password'),
				'remember'	=>	array('value'=>$_COOKIE[$this->authCookieName]? 1 : 0, 'type'=>'checkbox'),
			)				//					^^^ after a normal logout, the auth cookie is deleted and the state is longer available here! However, it is useful eg. after password changes
		));
		
		// form submitted? check the password
		if( $form->isOk() )
		{
			$bad_username = false;
			$bad_pw = false;
			
			$entered_name = $form->param['input']['username']['value'];
			$entered_pw   = $form->param['input']['pw']['value'];
			
			if( ($record = $reg->readRecord('t_user', array('f_username'=>$entered_name)))!==false )
			{
				$db_pw = $record['f_pw'];
				if( crypt($entered_pw, $db_pw) == $db_pw  )
				{
					$this->setSessionUser($entered_name, $record['f_admin']);
					if( $form->param['input']['remember']['value'] )
					{
						setcookie($this->authCookieName, $entered_name /*name*/ .':'. $record['f_authtoken'], time()+60*60*24*365*2 /*valid for 2 years*/);
					}
					else
					{
						$this->removeAuthCookie();
					}
					
					G_Html::redirect('?q=');
				}
				else
				{
					$bad_pw = true;
					G_Log::log('security', 'login failed: bad password entered by: '.$entered_name);
				}
			}
			else
			{
				$bad_username = true;
				G_Log::log('security', 'login failed: bad username: '.$entered_name);
			}
			
			if( $bad_username || $bad_pw ) {
				$form->addError(G_Local::_('login_badcombination'));
				$this->securityDelay();
			}
		}
		
		// render page		
		echo $this->renderHtmlStart(array('title'=>G_Local::_('menu_login')));
			echo G_Html::renderH1(G_Local::_('menu_login'));
			echo $form->render();
			echo G_Html::renderP(G_Local::_('login_cookiehints', '<i>'.htmlspecialchars(G_Session::cookieName()).'</i>', '<i>'.htmlspecialchars($this->authCookieName).'</i>'));
			echo G_Html::renderP(G_Local::_('login_lostcreatehints'));
		echo $this->renderHtmlEnd();
	}
};
