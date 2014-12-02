<?php
/*******************************************************************************
Go Search Engine - Imprint
****************************************************************************//**

Render the Imprint

@author Björn Petersen

*******************************************************************************/

class Action_Imprint extends G_Html
{
	function handleRequest()
	{
		// render page
		$title_html = G_Local::_('imprint');
		
		echo $this->renderHtmlStart(array('title'=>$title_html));
		
			echo G_Html::renderH1($title_html);
			
			$reg = G_Db::db('registry');
			echo G_Markdown::ascii2html( $reg->readValue('t_text', array('f_key'=>'imprint'), 'f_text') );
			
		echo $this->renderHtmlEnd();
	}
};

