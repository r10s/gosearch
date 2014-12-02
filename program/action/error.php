<?php
/*******************************************************************************
Go Search Engine - Error Handling
****************************************************************************//**

Error Handling

Any errors that may occur by the normal usage of the search engine should be
handled by G_Html::msg_or_die() (we bound this to the HTML class, as it
is only useful there).

Logic, programming, installation, missing-function and non.html errors can be
handled by a simple die() - they will normally not occur during the normal
search engine usage.
However, for installation or missing-function-errors, whereever possible and
not too costly, a more detailed error page would be welcome. 

@author Björn Petersen

*******************************************************************************/

class Action_Error extends G_Html
{
	function __construct($msg_or_code = '', $add_msg = '')
	{
		$this->msg_or_code = $msg_or_code;
		$this->add_msg = $add_msg;
		parent::__construct();
	}
	
	function handleRequest()
	{
		// get error title and text from   err_<code> = "short error title|a longer error description."
		$title_html = G_Local::_('err');
		$text_html = htmlspecialchars($this->msg_or_code);
		if( ($str=G_Local::_default('err_' . $text_html, '')) != '' ) {
			if( strpos($str, '|') ) {
				list($title_html, $text_html) = explode('|', $str);
			}
			else {
				$text_html = $str;
			}
		}
		
		// add additional message in light gray
		if( $this->add_msg ) {
			$text_html .= ' <span class="shy">[' . htmlspecialchars($this->add_msg) . ']</span>';
		}
		
		// render the error page
		switch( $this->msg_or_code ) {
			case 404: header('HTTP/1.1 404 Not Found'); break;
			case 401: header('HTTP/1.1 401 Unauthorized'); break;
		}
	
		echo $this->renderHtmlStart(array('title'=>G_Local::_('err')));
		
			echo G_Html::renderH1($title_html);
			echo G_Html::renderP($text_html);
		
		echo $this->renderHtmlEnd();
	}
};
