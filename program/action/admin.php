<?php
/*******************************************************************************
Go Search Engine - Admin
****************************************************************************//**

The parameter ?a=admin invokes Action_Admin which will forward to the
last opened admin page.

The last action is stored in the session as 'last_admin_action' by  
G_HtmlAdmin::renderHtmlStart() (which should not be called here - 
otherwise the information gets overwritten)

@author Björn Petersen

*******************************************************************************/

class Action_Admin extends G_HtmlAdmin
{
	/***********************************************************************//**
	Called if the user opens the page `?a=admin`
	***************************************************************************/
	public function handleRequest()
	{
		$this->hasAdminAccessOrDie();
		
		// forward to the last admin page used
		$last_admin_page = G_Session::get('last_admin_action');
		$classname = 'Action_'.ucfirst($last_admin_page);
		$filename = g_classname2filename($classname);
		if( !@file_exists($filename) || $last_admin_page=='admin'/*no recursion!*/ ) {
			$last_admin_page = 'adminindex';
		}
		G_Html::redirect('?a='.$last_admin_page);
	}
};
