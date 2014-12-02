<?php
/*******************************************************************************
Go Search Engine - Database access
****************************************************************************//**

Database access.

@author Björn Petersen

*******************************************************************************/

class G_Db
{
	static private $s_dbs;

	
	/***********************************************************************//**
	get a reference to access databases (or an error string if $die_on_errors 
	is set to false) 
	***************************************************************************/
	static function db($db_name, $die_on_errors = true)
	{
		if( !G_Db::$s_dbs[$db_name] )
		{
			$db = new G_Db();
			if( !$db->connect($db_name) )
			{
				if( $die_on_errors ) {
					G_Log::logAndDie('cirtical', 'cannot access ' . $db_name . ': ' . $db->getLastError());
				}
				else {
					return false; // no logging, directory may be unwritable
				}
			}
			G_Db::$s_dbs[$db_name] =& $db; 
		}
		return G_Db::$s_dbs[$db_name];
	}
	
	static function dbClose($db_name)
	{
		if( G_Db::$s_dbs[$db_name] ) {
			G_Db::$s_dbs[$db_name]->sql->close();
			unset(G_Db::$s_dbs[$db_name]);
		}
	}


	public $sql;
	private $db_name;
	private $readonly;


	
	private function connect($db_name, $readonly = false)
	{
		$this->db_name  = $db_name;
		
		require_once(G_DOCUMENT_ROOT . '/program/lib/sql/sqlite.inc.php');
		$this->sql 		= new G_SQLITE_CLASS();
		
		$file_name		= G_DATA_ROOT . '/'.$this->db_name.'.sqlite';
		
		$setup_needed = false;
		if( !file_exists($file_name) ) {
			$setup_needed = true;
		}

		if( !$this->sql->open($file_name) )
		{
			return false;
		}

		if( $db_name == 'index' || $db_name == 'crawler' )
		{
			$this->sql->exec("PRAGMA synchronous=OFF;");
			$this->sql->exec("PRAGMA cache_size=10000;");
		}
		
		$this->readonly = $setup_needed? false : $readonly;
		if( $this->readonly ) 
		{
			$this->sql->exec("PRAGMA query_only;"); // query_only available since sqlite 3.8 - however it does not harm to define it for older versions
		}
		
		if( $setup_needed ) {
			$ob = new G_DbSetup();
			$ob->databaseSetup();
		}
		
		return true;
	}
	
	function getDbName()
	{
		return $this->db_name;
	}
	
	function recordCnt($table)
	{
		$this->sql->query("SELECT COUNT(*) AS cnt FROM $table;");
		if( $this->sql->next_record() )
			return $this->sql->fs('cnt');
		return 0;
	}
	
	private function createList($fields, $sep = ', ')
	{
		$ret = '';
		foreach( $fields as $field_name => $value ) {
			$ret .= $ret==''? '' : $sep;
			$ret .= $field_name . '=' . $this->sql->quote($value);
		}
		return $ret;
	}
	
	function readRecord($table, $select)
	{
		$this->sql->query("SELECT * FROM $table WHERE ".$this->createList($select, ' AND '));
		$ret = $this->sql->next_record(); // may be false!
		if( $ret === false ) {
			$this->error_temp = "record {$table}::".$this->createList($select, ' AND ')." does not exist.";
		}
		return $ret;
	}
	
	function readValue($table, $select, $ret_field, $default = '')
	{
		if( ($record=$this->readRecord($table, $select)) !== false ) {
			return $record[$ret_field];
		}
		return $default;
	}
	
	function addRecord($table, $fields)
	{
		if( sizeof($fields)==0 ) { $this->error_temp ='no fields'; return false; }

		$keys   = '';
		$values = '';
		foreach( $fields as $key => $value ) {
			$keys .= ($keys==''? '' : ', ') . $key;
			$values .=  ($values==''? '' : ', ') . $this->sql->quote($value);
		}
			
		$sql = "INSERT INTO $table ($keys) VALUES ($values);";
		if( !$this->sql->query($sql) ) {
			return false;
		}
		return true;
	}
	
	
	/***********************************************************************//**
	the function returns false on errors or if the record does not exist 
	***************************************************************************/
	function updateRecord($table, $select, $fields)
	{
		$sql = "UPDATE $table SET " . $this->createList($fields) . " WHERE " . $this->createList($select, ' AND ');
		if( $this->sql->exec($sql) != 1 ) {
			$this->error_temp = "record {$table}::".$this->createList($select, ' AND ')." does not exist.";
			return false;
		}
		
		return true;
	}

	
	function addOrUpdateRecord($table, $select, $fields)
	{
		if( $this->updateRecord($table, $select, $fields) ) {
			return true; // success
		}
		else {
			$fields = array_merge($select, $fields); // the latter will overwrite the previous ones
			if( $this->addRecord($table, $fields) ) {
				return true; // success
			}
		}
		
		return false; // error
	}
	
	function deleteRecord($table, $select)
	{
		$sql = "DELETE FROM $table WHERE ".$this->createList($select);
		if( !$this->sql->query($sql) ) {
			return false;
		}
		return true;
	}
	

	function getLastError()
	{
		$ret = 'err_db (' . ($this->error_temp? $this->error_temp : $this->sql->get_last_error()) . ')';
		$this->error_temp = '';
		return $ret; // the returned error is NOT html!
	}
	

	/***********************************************************************//**
	Function takes a hash key=>value and builds a comma separated string of all
	quoted keys.
	***************************************************************************/	
	function quoteKeys($arr)
	{
		$ret = '';
		foreach( $arr as $key=>$value ) {
			$ret .= ($ret?', ':'') . $this->sql->quote($key);
		}
		return $ret;
	}
};


