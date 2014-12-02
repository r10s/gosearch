<?php
/*******************************************************************************
Simple SQLite wrapper
****************************************************************************//**

Usage:

	$db = new G_SQLITE_CLASS;
	if( $db->open(file) )
	{
		$result = $db->query("SELECT a, b FROM c WHERE d;");
		while( $db->next_record() ) 
		{
			$field = $db->fs('a');
			$field = $db->fs('b');
		}
		$db->close();
	}

As we're using PDO, there is no need to call stripslashes() for the fields -
however, this is a problem as a f() function would not be compatible to MySQL,
where we use stripslashes(f()) ...

Therefore, we implement the new function fs(): This function always returns 
the strings correctly without slashes.

NB: [*] inserting multiple rows by only one sqlite statement is possible since 
3.7.11, see http://www.sqlite.org/changes.html#version_3_7_11
However, today, 05.12.2013, this is not really widely spread: my wamp-system 
and domainfactory have 3.7.7, all-inkl. has 3.7.9


NB: The different slash functions:
	
	Normal String:		' \			<-- if you call stripslashes() on this, the backslashes would be away ...
	SQLite::quote()		'' \		
	addslashes()		\' \\

NB: PDO and sqlite are enabled by default since PHP 5.1.0
	
@author Björn Petersen
	
*******************************************************************************/

class G_SQLITE_CLASS 
{
	private $filename;
	public $dbHandle;
	private $error_str; // only used if dbHandle ist unset

	
	function __construct()
	{
		$this->dbHandle = false;
	}
	function __destruct()
	{
		$this->close();
	}

	
	/***************************************************************************
	open/close database
	***************************************************************************/

	function open($filename)
	{
		if( $this->dbHandle ) die('there is already a file assigned to this sqlite object.');
		
		// file already opened? (we use the same handle in this case)
		if( $GLOBALS['g_sqlite_handles'][$filename]['usage'] >= 1 )
		{
			$GLOBALS['g_sqlite_handles'][$filename]['usage']++;
			$this->dbHandle = $GLOBALS['g_sqlite_handles'][$filename]['handle'];
			$this->filename = $filename;
			return true; 
		}
		
		// check, if pdo_sqlite is available
		$drivers = PDO::getAvailableDrivers();
		if( !in_array('sqlite', $drivers) ) {
			@dl('pdo_sqlite.so');
			$drivers = PDO::getAvailableDrivers();
			if( !in_array('sqlite', $drivers) ) {
				$this->error_str = 'pdo_sqlite.so not available';
				return false;
			}
		}
		
		// try to open the file
		try
		{
			$this->dbHandle = new PDO('sqlite:'.$filename);
		}
		catch(Exception $e)
		{
			$this->dbHandle = false;
			$this->error_str = $e->getMessage();
		}
		
		if( !$this->dbHandle )
		{
			return false;
		}

		// file successfully opened
		$GLOBALS['g_sqlite_handles'][$filename]['handle'] = $this->dbHandle;
		$GLOBALS['g_sqlite_handles'][$filename]['usage' ] = 1;
		$this->filename = $filename;
		return true;
	}
		
	function close()
	{
		if( $this->dbHandle )
		{
			if( $this->dbHandle != $GLOBALS['g_sqlite_handles'][$this->filename]['handle'] ) die('bad sqlite handle.');
			
			$GLOBALS['g_sqlite_handles'][$this->filename]['usage']--;
			if( $GLOBALS['g_sqlite_handles'][$this->filename]['usage'] == 0 )
			{
				//$this->dbHandle->close(); -- does not exist
				unset($GLOBALS['g_sqlite_handles'][$this->filename]);
			}
			$this->dbHandle = false;
		}
	}


	/***************************************************************************
	query
	***************************************************************************/
	
	static $benchmarks;
	
	// exec() is simelar to query, but does not return an result set but the number of affected rows
	function exec($query)
	{
		$start = microtime(true);
			$ret = $this->dbHandle->exec($query);
		G_SQLITE_CLASS::$benchmarks[] = array('exec', $query, microtime(true)-$start);
		
		if( $ret === false ) {
			trigger_error($this->get_last_error(), E_USER_NOTICE); // error may be suppressed by @
		}
		return $ret;
	}
	
	function prepare($query)
	{
		return $this->dbHandle->prepare($query);
	}
	
	// query database
	private $resultHandle;
	private $currRecord;
	function query($query)
	{
		$start = microtime(true);
			$this->resultHandle = $this->dbHandle->query($query);
		G_SQLITE_CLASS::$benchmarks[] = array('query', $query, microtime(true)-$start);
		
		if( !$this->resultHandle ) {
			trigger_error($this->get_last_error(), E_USER_NOTICE); // error may be suppressed by @
		}
		return $this->resultHandle !== false;
	}
	
	function next_record()
	{
		if( !$this->resultHandle )
			return false;

		$this->currRecord = $this->resultHandle->fetch(PDO::FETCH_ASSOC);		
		return $this->currRecord; // may be false!
	}
	
	function affected_rows() // for a SELECT statement, the return value is undefined!
	{
		if( !$this->resultHandle )
			return 0;
		return $this->resultHandle->rowCount();
	}
	
	function fs($fieldname) // return the given field, no additional stripslashes() or sth. like that needed!
	{
		return $this->currRecord[$fieldname];
	}
	
	// handle transactions - this is WAY faster than using autocommit!
	function beginTransaction()
	{
		$this->dbHandle->beginTransaction();
	}
	
	function commit()
	{
		$this->dbHandle->commit();
	}
	
	function roll_back()
	{
		$this->dbHandle->rollBack();
	}
	

	function insert_id()
	{
		return $this->dbHandle->lastInsertId();
	}	

	// deprecated
	function begin_transaction()  { $this->beginTransaction(); } 
	
	
	/***************************************************************************
	Tools
	***************************************************************************/

	function quote($str)
	{
		// quote() should be used for fields in query() (instead of addslashes())
		// note, that quote() also adds the quotes to the string!
		return $this->dbHandle->quote($str);
	}
	
	function get_last_error()
	{
		$ret = '';
		if( $this->dbHandle ) {
			$code = $this->dbHandle->errorInfo ();
			if( $code[1] ) {
				$ret = sprintf('%s (%d)', $code[2], $code[1]);
			}
		}
		else {
			$ret = $this->error_str; // error from open()
		}
		return 'SQLite error: ' . ($ret==''? 'unknown error' : $ret);
	}

	function table_exists($table)
	{
		$this->query("PRAGMA table_info($table)");
		return $this->next_record()? true : false;
	}
	
	function column_exists($table, $col)
	{
		$this->query("PRAGMA table_info($table)");
		while( ($record=$this->next_record())!==false ) {
			if( $record['name'] == $col ) {
				return true;
			}
		}
		return false;
	} 		
};

G_SQLITE_CLASS::$benchmarks = array();