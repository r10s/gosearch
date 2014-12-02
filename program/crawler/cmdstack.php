<?php
/*******************************************************************************
Go Search Engine - Handle crawling commands
****************************************************************************//**

@author Björn Petersen

*******************************************************************************/


class Crawler_CmdStack
{
	private $err;
	const FILE_NAME_PREFIX = 'crawler.cmd.';
	
	function push($cmd)
	{
		$this->err = '';
		
		$file = G_DATA_ROOT . '/' . Crawler_CmdStack::FILE_NAME_PREFIX . strtr(sprintf('%0.3f', microtime(true)), array('.'=>'', ','=>''));
		$handle = @fopen($file, 'w');
		if( !$handle ) {
			$this->err = 'err_open';
			return;
		}
		
		if( @fwrite($handle, serialize($cmd)) === false ) {
			$this->err = 'err_write';
			return;
		}
		
		@fclose($handle);
	}
	
	function pop()
	{
		// get all commands
		$commands = array();
		$handle = @opendir(G_DATA_ROOT);
		if( !$handle ) {
			$this->err = 'err_opendir';
			return array();
		}

		while( $entry_name = readdir($handle) ) 
		{
			if( substr($entry_name, 0, strlen(Crawler_CmdStack::FILE_NAME_PREFIX)) == Crawler_CmdStack::FILE_NAME_PREFIX ) {
				$commands[] = $entry_name;
			}
		}
		@closedir($handle);
		
		if( sizeof($commands) == 0 ) {
			return array(); // success, no commands waiting
		}
		
		// sort, the oldes is now the first element
		sort($commands);

		// read the file
		$file_name =  G_DATA_ROOT . '/' . $commands[0];
		$handle = @fopen($file_name, 'r');
		if( !$handle ) {
			$this->err = 'err_open';
			return array();
		}
		$content = fread($handle, 1024*1024 /*read up to 1 MB*/);
		@fclose($handle);
		
		// delete the file - do this before checking the data as otherwise we may get the same command over and over
		if( !@unlink($file_name) ) {
			$this->err = 'err_delete';
			return array();
		}
		
		// check the command
		$cmd = @unserialize($content);
		if( !is_array($cmd) ) {
			$this->err = 'err_data';
			return array();
		}
		
		// success
		return $cmd;
	}
	
	function getErr()
	{
		return $this->err;
	}
	

};
