<?php	
/*******************************************************************************
Go Search Engine - Caching
****************************************************************************//**

G_Cache can be used for caching things in a database table.

Usage:

	$ob = new G_Cache(..., 2*G_Cache::DAYS);

	if( ($data=$ob->lookup($key))===false )
	{
		// create data from scratch ...
		
		// add data to cache
		$ob->insert($key, $data, );
	}
	
	// do whatever to do with $data

The tables themselves should be created in the normal setup process; cache 
tables are not created automatically.

@author Björn Petersen

*******************************************************************************/

class G_Cache
{
	const HOURS = 3600;
	const DAYS  = 86400;
	private $db;
	private $autoCleanupDone;
	
	
	/***********************************************************************//**
	Create a Cache Table object.  The cache will be written to the given table
	in the given database.  
	
	$validSeconds specifies the desired lifetime of inserted objects.  For 
	specifying these values, G_Cache::HOURS and G_Cache::DAYS may help as
	multipliers.
	
	The table must exists and must have the fields f_key, f_value, f_time
	(indexes are recommended for f_key and for f_time) 
	***************************************************************************/
	function __construct(G_Db $db, $tableName, $validSeconds)
	{
		$this->db				= $db;
		$this->tableName		= $tableName;
		$this->autoCleanupDone	= false;
		$this->validSeconds		= $validSeconds;
	}
	
	
	/***********************************************************************//**
	Insert a value defined by a key.  If key key already exists, the value is 
	updated.
	
	Moreover, as this function needs write process, we will delete all expired
	keys here (for performance reasons, we do this only one time per script
	and cache table).
	***************************************************************************/	
	function insert($key, $value)
	{	
		// delete any data
		if( !$this->autoCleanupDone )
		{
			$this->cleanup();
			$this->autoCleanupDone = true;
		}
		
		// add/update the record
		$this->db->addOrUpdateRecord($this->tableName, 
			array('f_key'=>$this->shortenKey($key)), 
			array('f_value'=>$value, 'f_time'=>time()+$this->validSeconds));
	}

	
	/***********************************************************************//**
	Lookup a value defined by a key.  The function returns the stored value or
	`false` if the key cannot be found in the cache.
	
	Only keys newer than $validSeconds are returned!  Older keys are deleted
	on the next insert() (to use writing processes only if needed)
	***************************************************************************/
	function lookup($key)
	{
		$this->db->sql->query("SELECT f_value FROM $this->tableName WHERE f_key=" . $this->db->sql->quote($this->shortenKey($key)) . " AND f_time>".time());
		if( $this->db->sql->next_record() ) {
			return $this->db->sql->fs('f_value');
		}
		return false;
	}
	
	
	/***********************************************************************//**
	Delete all old entries from the cache.  There is no real need to call this 
	function directly; cleanup is also done automatically on insert()
	**************************************************************************/
	function cleanup()
	{
		$this->db->sql->exec("DELETE FROM $this->tableName WHERE f_time<" . time());
	}


	/***********************************************************************//**
	For "normal" keys, we use the keys as given.  However, for very long
	keys (>255 characters) we have to shorten them so that they will work eg.
	with MySQL.
	***************************************************************************/	
	private function shortenKey($key)
	{
		$len = strlen($key);
		if( $len > 255 )
		{
			// Shorten _very_ long keys to <prefix><md5-of-middle-part><postfix> -
			// otherwise, eg. MySQL truncates to 255 characters which may lead to lots of duplicate entries.
			return substr($key, 0, 111) . md5(substr($key, 111, $len-111-112)) . substr($key, -112);
		}
		else
		{
			return $key;
		}
	}
	


};
