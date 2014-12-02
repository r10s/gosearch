<?php
/*******************************************************************************
Go Search Engine - Inter Process Communication
****************************************************************************//**

Currently, we do inter process communication by simple files write/read.  There
may be better approaches, however, they may not always available in all PHP
environments.

Usage on receiver's site:
	
	$ob = new G_IPC();
	if( ($id=$ob->getId()) !== '' )
	{
		// broadcast the id now ... wait a little time ...
		
		while( ($msgs=$ob->rcvMessages())!==false )
		{
			...
		}
	}
	
Usage on the sender's site:

	$ob = new G_IPC($id); 
	if( $ob->ok() ) // $id may come from $_GET['id']
	{
		$msg = array('name'=>..., 'value'=>...);
		$ob->sendMessage($msg);
	}

@author Björn Petersen

*******************************************************************************/

class G_IPC
{
	private $err = '';
	private $id = '';
	const DEL_SECONDS = 300;

	
	/***********************************************************************//**
	Create a new interprocess object.  If an ID is given, the object is created
	to communicate with this ID.  If no ID is given, a new ID is created.
	
	To check, if everything is just fine, use ok()
	***************************************************************************/	
	function __construct($id = '')
	{	
		$this->ipcdir = G_DATA_ROOT . '/ipc'; // no trailing slash
		
		if( $id == '' ) 
		{
			// create a new ID
			// -----------------------------------------------------------------
			
			$this->id = $this->randHash();
			
			// create directory, if not yet done
			if( !@is_dir($this->ipcdir) ) { 
				if( !@mkdir($this->ipcdir) ) {
					$this->err = 'err_createdir (' . $this->ipcdir . ')';
					return;
				}
			}
			
			// create the base file
			$filename = $this->getFilename('ready');
			$handle = @fopen($filename, 'w');
			if( $handle ) {
				@fwrite($handle, 'ready');
				@fflush($handle); // is this needed? I got some err_badid and assumed G_IPC($id) failed because the .ready file was not completely written ...
				@fclose($handle);
				//echo 'file_exists: ' . file_exists($filename);
			}			
			else {	
				$this->err = 'err_write (' . $filename . ')';
				return;
			}
		}
		else
		{
			// use an existing ID
			// -----------------------------------------------------------------
			
			if( !preg_match('/^[a-fA-F0-9]{4,64}$/', $id) ) {
				$this->err = 'err_badid (syntax)';
				return;
			}
			
			$this->id = $id;
			/*
			$filename = $this->getFilename('ready');  --- why does this not work? even not with the fflush() from above?
			if( !@file_exists($filename) ) {
				$this->err = 'err_badid (ipc, ID ' . $this->id . ' not found)';
				return;
			}
			*/
			
		}
	}
	

	/***********************************************************************//**
	ok() returns true if there is no error, false otherwise.
	***************************************************************************/	
	function ok()
	{
		return ($this->err==='')? true : false;
	}

	
	/***********************************************************************//**
	Get the last error code as a string or an empty string if there is no error.
	***************************************************************************/	
	function getErr()
	{
		return $this->err;
	}
	
	/***********************************************************************//**
	Returns the ID in use.  On errors, an empty string is returned.
	***************************************************************************/
	function getId()
	{
		return $this->id; // may be empty to indicate errors
	}
	
	
	/***********************************************************************//**
	Sends a message to the listening proccess.  The message may be any variable
	that can be serialized.
	
	The function returns true/false on success/error.
	***************************************************************************/	
	function sendMessage($mixed)
	{
		$filename = $this->getFilename('msg');
		$handle = @fopen($filename, 'w');
		if( !$handle ) {
			$this->err = 'err_write (' . $filename . ')';
			return false;
		}
		
		@fwrite($handle, serialize($mixed));
		@fclose($handle);
		return true;
	}
	
	
	/***********************************************************************//**
	Reads a messages send by other processes.
	
	The function returns an array with the same variables as given to 
	sendMessage() before; if there are no messages, an empty array is returned.
	On errors, the function returns false.
	***************************************************************************/	
	function rcvMessages()
	{
		if( !$this->ok() ) return false;
		
		// open the IPC directory
		$handle = @opendir($this->ipcdir);
		if( !$handle ) {
			$this->err = 'err_opendir';
			return false; // error
		}

		// scan the IPC directory
		$msgs = array();
		$to_read = array();
		$to_del = array();
		$justnow = time();
		while( ($entry_name=readdir($handle))!==false ) 
		{
			$entry_name_parts = explode('-', str_replace('.', '-', $entry_name)); // 0=id, 1=timestamp, 2=rand, 3=ext
			if( $entry_name_parts[0] == $this->id && $entry_name_parts[3] == 'msg' ) {
				$to_read[] = $entry_name;
				$to_del[] = $entry_name;
			}
			else if( $entry_name_parts[1] < $justnow-G_IPC::DEL_SECONDS ) {
				$to_del[] = $entry_name;
			}
			
		}
		@closedir($handle);
		
		// read matching files
		foreach( $to_read as $entry_name ) {
			$handle = @fopen($this->ipcdir . '/' . $entry_name, 'r');
			if( $handle ) {
				$contents = fread($handle, 0x100000 /*read max. 1 MB*/);
				$msgs[] = unserialize($contents);
				fclose($handle);
			}
		}
		
		// delete old files
		foreach( $to_del as $entry_name ) {
			@unlink($this->ipcdir . '/' . $entry_name);
		}		
		
		// success
		return $msgs; 
	}
	
	private function randHash()
	{
		$hash = md5(mt_rand() /*random value*/ . __FILE__ /*add file+path as some additional information not known from external*/ );
		$hash = substr($hash, 0, 10);
		return $hash;
	}
	
	
	/***********************************************************************//**
	Files in the IPC directory:

	- `<G_DATA_ROOT>/ipc/<id>-<timestamp>-0.ready` - this file is created by the  
	  receiver to indicate an ID is valid.  The timestamp is needed for easier
	  deletion of old files

	- `<G_DATA_ROOT>/ipc/<id>-<timestamp>-<rand>.msg` - these files will hold 
	  queued messages	
	***************************************************************************/	
	private function getFilename($ext)
	{
		assert( $ext == 'ready' || $ext == 'msg' );
		
		$filename = $this->ipcdir . '/' . $this->id . '-' . time() . '-';
		if( $ext == 'ready' ) {	
			$filename .= '0';
		}
		else {
			$filename .= sprintf('%04x', mt_rand(0x1,0xFFFF));
		}
		$filename .= '.' . $ext;
		return $filename;
	}

};
