<?php
/*******************************************************************************
Go Search Engine - Logout
****************************************************************************//**

Perform a logout.

@author Björn Petersen

*******************************************************************************/

class Action_Logout extends G_Html
{
	function handleRequest()
	{
		G_Session::destroy();
		
		$this->removeAuthCookie();
		
		if( G_Registry::iniRead('login_required', G_Html::LOGIN_REQUIRED_DEFAULT) ) {
			G_Html::redirect('?a=login');
		}
		else {
			G_Html::redirect('?q=');
		}
	}
};
