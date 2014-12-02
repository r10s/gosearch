<?php 
/*******************************************************************************
Go Search Engine - Framework
****************************************************************************//**

This file is included by index.php as the "framework" and contains global 
definitions (for easy updates, we keep index.php very small)

Stuff contained in this file should not be HTML specific, as we also generate
files other than HTML.

@author Björn Petersen

*******************************************************************************/

 
// PHP 5.0.0 needed for __construct(), public, private, protected, microtime($get_as_float), exceptions
// PHP 5.1.2 needed for spl_autoload_register()
// not (yet) needed: 5.3.0 for str_getcsv(), __DIR__, anonymous functions, namespace, system-independet crypt()
// take care on PHP 5.4.x: The default_charset changed to UTF-8 here! This may result in suspicious errors eg. when posting formulars
define('G_MIN_PHP', '5.1.2');
if( version_compare(PHP_VERSION, G_MIN_PHP, '<') ) { die('PHP version too old, at least ' . G_MIN_PHP . ' required.'); }


// some static defines (G_DOCUMENT_ROOT is already defined in index.php)
define('G_PROGRAM_FULL_NAME',	'Go Search Engine'); 
define('G_PROGRAM_SHORT_NAME',	'Go'); 
define('G_PROGRAM_ID',			'go');			// should be only a-z, no special characters, no numers
define('G_USER_AGENT',			'gobotbeta');	// when out of beta, we'll change this to `gobot`
define('G_VERSION',				0.13);			// written to the database files, must be a floating point number so that larger/small comparisons works

// load optional config.php
if( @file_exists(G_DOCUMENT_ROOT . '/config/config.php') ) {
	require_once(G_DOCUMENT_ROOT . '/config/config.php'); // we really want the errors posted by require_once(), @include_once() or @require_once() would be silent and errors will be hard to find
}
if( !defined('G_DATA_ROOT') ) { 
	define('G_DATA_ROOT', G_DOCUMENT_ROOT . '/data'); // no trailing slash!
}


// enter debug mode? by default, G_DEBUG is not defined and assert() and error reporting will be disabled for security reasons
if( defined('G_DEBUG') && constant('G_DEBUG') ) {
	require_once(G_DOCUMENT_ROOT . '/program/g/debug.php');
}
else {
	assert_options(ASSERT_ACTIVE, 0);
	error_reporting(0);
}


// load classes as Scope_Name from /program/scope/name.php
spl_autoload_register('g_autoload');
function g_classname2filename($a)
{
	return G_DOCUMENT_ROOT . '/program/' . str_replace('_', '/', strtolower($a)) . '.php'; 
}
function g_autoload($classname)
{
	$f = g_classname2filename($classname);
	if( @file_exists($f) ) { require_once($f); }
}


// for backward compatibility (PHP < 5.4.0) automatic stripslashes() if get_magic_quotes_gpc is enabled
if( function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() ) { 
	G_Stripslashes::stripAll(); 
}


// lock/unlock the database for _write_ access (reading is always possible) by a simple lock file.
// the lock file contains the max. time for the lock; this is needed to avoid zombie tasks which never unlock the database.
define('G_DB_LOCK_FILE', G_DATA_ROOT . '/crawler.lock'); // we lock directly in /data as it is easier to notice during development
function g_is_db_locked()
{
	if( !@file_exists(G_DB_LOCK_FILE) ) { 
		return false; // db is not locked
	}
	if( ($handle = @fopen(G_DB_LOCK_FILE, 'r')) !== false )  {
		$force_restart_time = intval(@fread($handle, 32));
		@fclose($handle);
		if( time() > $force_restart_time ) {
			@unlink(G_DB_LOCK_FILE); 
			G_Log::log('crawler', 'zombie lock recovered');
			return false; // db is no longer locked
		}
	}	
	return true; // db is locked
}
function g_lock_db($valid_until)
{
	if( ($handle = @fopen(G_DB_LOCK_FILE, 'w')) !== false ) {
		if( @fwrite($handle, strval($valid_until)) !== false ) {	
			@fclose($handle);
			$GLOBALS['db_locally_locked'] = true;
			return true; // success
		}
		@fclose($handle);
	}
	return false; // error
}
function g_unlock_db() 
{
	@unlink(G_DB_LOCK_FILE); 
}


// main entry point
$classname = 'Action_Search';
if( $_GET['a']!='' )
{
	$classname = 'Action_' . ucfirst($_GET['a']);
	if( !file_exists(g_classname2filename($classname)) )
	{
		$classname = 'Action_Error404';
	}
}

$ob = new $classname;
$ob->handleRequest();


