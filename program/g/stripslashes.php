<?php
/*******************************************************************************
Go - Stripslashes
****************************************************************************//**

G_Stripslashes simply removes all slashes from the arrays mentioned below and
should be constructed only once.

As being standard in 2013, we do not expect any masked characters in the 
`$_POST`, `$_GET`, `$_REQUEST` or `$_COOKIE` arrays, so there is no need for 
calling the PHP function stripslashes() when accessing these values.

However, for historical reasons, some PHP installations may add slashes to these
arrays which _must_ be removed using stripslashes() then - in this case the
(deprecated) function get_magic_quotes_gpc() returns true.	

So, we should add the following lines to our framework:

	if( function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() ) 
	{	
		G_Stripslashes::stripAll();
	}

In general, you are encouraged to switch this flag off. You can do so eg. by
using the following line in .htaccess

	php_flag magic_quotes_gpc off

As an alternative, you can modify the belonging php.ini, however, on shared
hosts, there is not always access to it.

@author Björn Petersen

*******************************************************************************/

class G_Stripslashes
{
	static private $everythingStripped;

	/***********************************************************************//**
	Call stripslashes() for all values in the arrays `$_POST`, `$_GET`, 
	`$_REQUEST` and `$_COOKIE`.
	***************************************************************************/
	static function stripAll()
	{
		if( !function_exists('get_magic_quotes_gpc') || !get_magic_quotes_gpc() ) { die('get_magic_quotes_gpc not set - why do you use G_Stripslashes?'); }
		if( G_Stripslashes::$everythingStripped ) die('G_Stripslashes already started!');
		G_Stripslashes::$everythingStripped = true;

		G_Stripslashes::stripslashesArray($_POST); // array_map('stripslashes', ...) may be an alternative, however, this is not recursive!
		G_Stripslashes::stripslashesArray($_GET);
		G_Stripslashes::stripslashesArray($_REQUEST);
		G_Stripslashes::stripslashesArray($_COOKIE);
	}
	
    static private function stripslashesArray(&$arr) 
	{
		reset($arr);
		while( list($key, $value) = each($arr) )
		{
			if( is_array($value) )
			{
				G_Stripslashes::stripslashesArray($value);
			}
			else
			{
				$arr[$key] = stripslashes($value);
			}
		}
    }
};
