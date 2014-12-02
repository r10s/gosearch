<?php 
/*******************************************************************************
Go Search Engine - Help
****************************************************************************//**

Render a simple HTML helping page

We do not support user language files; the program ist subject to change and 
such customization will make updated documentations much harder. 
 
We think of the whole system as a backend, not as a eg. wordpress frontend 
that should be customized.
 
However, we should allow one or more user about pages, but no replacements  
for basic files by design.

@author Björn Petersen

*******************************************************************************/

class Action_Help extends G_Html
{
	private function getHtmlFilename($page)
	{
		$lang = G_Local::langId();
		return G_DOCUMENT_ROOT . '/program/lang/' . $lang . '/' . $page . '.html';
	}	

	private function render_html_snippet($page)
	{
		
		// load HTML file
		$file_name = $this->getHtmlFilename($page); 
		if( !@file_exists($file_name) )
		{ 
			return false;
		}
		
		$html_code = @file_get_contents($file_name);
		return $html_code;
	}

	function handleRequest()
	{
		$this->hasSearchAccessOrDie();

		// check help ID
		$page = $_GET['p']? $_GET['p'] : 'help';

		if( !preg_match('/^[a-z0-9]+$/', $page) )
		{
			$this->msgAndDie(404, 'bad id');
		}
	
		if( ($html_code = $this->render_html_snippet($page))===false ) 
		{
			$this->msgAndDie(404, 'file not found');
		}

		// render page
		echo $this->renderHtmlStart(array('title'=>G_Local::_('menu_help')));
			echo $html_code . "\n";
		echo $this->renderHtmlEnd();
	}
};

