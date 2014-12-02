<?php
/*******************************************************************************
Go Search Engine - Debugging stuff
****************************************************************************//**

Debugging stuff.

This file is only included if G_DEBUG ist defined to 1 in config.php.

NB: Even if useful in some cases, it is an bad idea to define the debug state
by the URL (sth. like &debug=1) - we may give too many information and we may
open some security holes by allowing this.  If you want to debug a system, it 
should be possibe for you to change config.php.

@author Björn Petersen

*******************************************************************************/


if( !defined('G_DEBUG') || constant('G_DEBUG')==0 ) {
	die('debug.php should only be included if G_DEBUG is defined.');
}


// we do not want to use $_REQUEST - to make this sure, reset it to an empty array (some functions rely on $_REQUEST being set and an array)
$_REQUEST = array();


// set error reporting to E_ALL - this includes E_NOTICE which is disabled by default
// NB: If the program gets distributed, for security purposes, we should switch error reporting completely off on non-debug systems.
error_reporting(E_ALL);


// E_NOTICE errors are handled by our own error handler:
// - it is very useful to get informed when using undefined _variables_ - imagine typos, renaming of variables etc.
// - but getting informed about undefinex index/offset is too much IMHO
set_error_handler('my_error_handler', E_NOTICE|E_USER_NOTICE);
function my_error_handler($errno, $errstr, $errfile, $errline, $errcontext) 
{
	if( error_reporting() ) { // if error reporting is switched off temporary manually, do not print anything
		if( 
			substr($errstr, 0, 16) != 'Undefined index:'	// Undefined index: we get these notices when accessing undefined keys in associative arrays, eg. $arr['unused']
		 && substr($errstr, 0, 17) != 'Undefined offset:'	// Undefined offset: we get these notices when accessing undefined index in normal arrays, eg. $arr[12345]
		 )
		{
			echo '<div style="border:2px solid red; color:red; font-weight:normal; padding: 1px 6px 2px;">';
				echo '<b>Error ' . $errno . ': ' . $errstr . '</b> in ' . $errfile . ' on line ' . $errline;
				echo '<br /><small>errcontext: ' . htmlspecialchars(print_r($errcontext, true)) . '</small>';
			echo '</div>';
		}
	}
}


// enable assert() (should be actice by default, however)
// (if the assert routine does not display the line of code: this seems to be a bug; I cannot catch this with ASSERT_CALLBACK)
assert_options(ASSERT_ACTIVE, 1);


