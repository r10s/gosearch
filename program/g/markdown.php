<?php 
/*******************************************************************************
Go Search Engine - Formatting strings
****************************************************************************//**

Various string formatting functions.

@author Björn Petersen

*******************************************************************************/

class G_Markdown
{
	static function ascii2html($str)
	{
		// strip all "\r", convert HTML-entities
		$str = str_replace("\r", "", $str);
		$str = htmlspecialchars($str);

		// convert hyperlinks
		$str = preg_replace('/(https?:\/\/[^\s\<\>&\[\]]+)/', '<a href="$0">$0</a>', $str);
		
		// convert more than three lineends to two
		$str = trim($str);
		while( strpos($str, "\n\n\n")!== false ) {
			$str = str_replace("\n\n\n", "\n\n", $str);
		}
		
		// replace two lineends by "</p><p>" and one lineend by "<br />"
		$str = str_replace("\n\n", '</p><p>', $str);
		$str = str_replace("\n", '<br />', $str);
		
		// done, surround everything by a paragraph
		return '<p>' . $str . '</p>';
	}

};

