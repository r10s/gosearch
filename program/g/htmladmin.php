<?php 
/*******************************************************************************
Go Search Engine - Admin
****************************************************************************//**

HTML base class for all admin pages

@author Björn Petersen

*******************************************************************************/

class G_HtmlAdmin extends G_Html
{
	function __construct()
	{
		parent::__construct();
	}
	
	protected function renderHtmlStart($param = '')
	{
		if( !is_array($param) ) $param = array();

		// create admin submenu
		$a = strval($_GET['a']);
		$submenu[] = array('url'=>'?a=adminindex',			'descr'=>G_Local::_('admin_index'),			'sel'=>$a=='adminindex');
		$submenu[] = array('url'=>'?a=adminhosts',			'descr'=>G_Local::_('admin_hosts'),			'sel'=>$a=='adminhosts');
		$submenu[] = array('url'=>'?a=adminusers',			'descr'=>G_Local::_('admin_users_title'),	'sel'=>$a=='adminusers');
		$submenu[] = array('url'=>'?a=adminetc',			'descr'=>G_Local::_('admin_etc'),			'sel'=>$a=='adminetc');
		
		// remember the current sub-page (not: testing page) to show it when the just clicks on "Admin" in the main menu
		if( substr($a, 0, 4)!='test' ) {
			G_Session::set('last_admin_action', $a);
		}
		
		// prepare parameters
		$param['title'] = G_Local::_('menu_admin').'/'.$param['title'];
		$param['submenu'] = $submenu;

		// render page start
		return parent::renderHtmlStart($param);
	}
	
	protected function renderHtmlEnd($param = '')
	{
		// render page end
		return parent::renderHtmlEnd($param);
	}
};

