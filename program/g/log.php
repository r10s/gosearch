<?php
/*******************************************************************************
Go Search Engine - Logging
****************************************************************************//**

The classs G_Log provides some easy-to-use logging functions that work without
dependendies to other parts of the program excelt G_DATA_ROOT.

Usage:

	G_Log::log('message');
	
Or:
	
	G_Log::log('mysection', 'message');

In the first example, the message is prepended by a timestamp and is written 
to the file `/data/log/<year>-<month>-critical.txt`

In the second example, the file `/data/log/<year>-<month>-mysection.txt` is
used.
	
@author Björn Petersen

*******************************************************************************/

class G_Log
{
	static private $s_id;
	
	/***********************************************************************//**
	Log a message to a given section (file).  The section as the first parameter
	can be left out; in this case the section `critical` is used.
	
	@param $section the section (file) to log to; if left out, the section 
					`critical` is used
	@param $msg 	the message to write; it will be prepended by a timestamp 
					before written to the file defined by $section
	@returns		nothing
	***************************************************************************/
	static function log($section, $msg = '')
	{
		if( $msg == '' ) { $msg = $section; $section = 'critical'; }

		if( !isset(G_Log::$s_id) ) {
			G_Log::$s_id = sprintf('%04X', mt_rand(0x0, 0xFFFF));
		}

		// create the log directory
		$logdir = G_DATA_ROOT.'/log';
		if( !@is_dir($logdir) ) { 
			if( !@mkdir($logdir) ) {
				die('Cannot create log directory '.$logdir.' - please make sure, '.G_DATA_ROOT.' exists and is writable. Message I wanted to log: '.$section.': '.$msg);
			}
		}

		// open the logging file
		$logfile = $logdir . '/' . strftime('%Y-%m-%d') . '-' . $section . '.log';
		$handle = @fopen($logfile, 'a');
		if( !$handle )
		{
			if( !@file_exists() ) {
				die('Cannot create log file '.$logfile.' - please make sure, '.$logdir.' is writable. Message I wanted to log: '.$section.': '.$msg);
				exit();																		// ^^^ no need to ask for existance - we'll create it, if unexistant above
			}
		}
		
		// append the line to the logging file and close it
		$line = strftime("%Y-%m-%d %H:%M:%S") . "\t$" . G_Log::$s_id . "\t";
		if( $section == 'security' ) $line .= $_SERVER['REMOTE_ADDR'] . "\t";
		$line .= strtr($msg, "\n\r", '__') . "\n";
		@fwrite($handle, $line);
		@fclose($handle);
	}
	
	static function logAndDie($section, $msg = '')
	{
		if( $msg == '' ) { $msg = $section; $section = 'critical'; }
		G_Log::log($section, $msg);
		die($msg);
	}

};
