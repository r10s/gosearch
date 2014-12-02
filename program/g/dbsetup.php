<?php
/*******************************************************************************
Go Search Engine - Database Setup
****************************************************************************//**

G_DbSetup create or update the databases.

@author Björn Petersen

*******************************************************************************/

class G_DbSetup
{
	private $updatedDatabases = 0;
	

	/***********************************************************************//**
	isDataDirWritable() function checks, if the data directory is
	writable and if writable subdirectories can be create there.
	
	@param none
	@returns true/false
	***************************************************************************/
	public function isDataDirWritable()
	{
		$magic			= time().mt_rand(10000,50000);
		$testdir		= G_DATA_ROOT."/testdir$magic";
		$testfile[0]	= G_DATA_ROOT."/testfile$magic";
		$testfile[1]	= "$testdir/testfile";
		
		// create a subdirectory
		if( !@mkdir($testdir) ) {
			return false;
		}
		
		// write two files (on in the subdirectory, one directly in data)
		for( $i = 0; $i < 2; $i++ )
		{
			$fh = @fopen($testfile[$i], 'w'); 
			if( !$fh ) { 
				return false;
			}	
			if( @fwrite($fh, $i.'-'.$magic) === false ) {
				return false;
			}
			if( !@fclose($fh) ) {
				return false;
			}
		}
		
		// read the file contents
		for( $i = 0; $i < 2; $i++ )
		{
			$fh = @fopen($testfile[$i], 'r'); 
			if( !$fh ) { 
				return false;
			}	
			if( ($test=@fread($fh, 32)) === false ) {
				return false;
			}
			if( $test != $i.'-'.$magic ) {
				return false;
			}
			if( !@fclose($fh) ) {
				return false;
			}
		}
		
		// delete the files
		for( $i = 0; $i < 2; $i++ )
		{
			if( !@unlink($testfile[$i]) ) {
				return false;
			}
			if( @file_exists($testfile[$i]) ) {
				return false;
			}
		}
		
		// delete the directory
		if( !@rmdir($testdir) ) {
			return false;
		}
		if( @file_exists($testdir) ) {
			return false;
		}
		
		// success
		return true;
	}
	
	private function dbExecOrDie($db, $sql)
	{
		// simply dies on errors
		if( !@$db->sql->query($sql) )
		{
			G_Log::logAndDie('Initialization failed - is the database file writeable? Is it corrupted? [sql: ' . $sql . ', error: ' . $db->sql->get_last_error() . ']');
		}
	}

	private function updateNeeded($db, $soll_db_version)
	{
		$this->last_soll_db_version = $soll_db_version;
		
		if( !$db->sql->table_exists('t_ini') )
		{
			$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS t_ini (
						f_key TEXT UNIQUE, 
						f_value TEXT
					);");
			$db->addRecord('t_ini', array('f_key'=>'db_name', 'f_value'=>$db->getDbName()));
			return 2; // table just created - update needed
		}
		else
		{
			if( ($record=$db->readRecord('t_ini', array('f_key'=>'db_version'))) === false )
			{
				return 1; // version does not exist - update needed
			}
			
			if( floatval($record['f_value']) < $soll_db_version ) 
			{
				return 1; // version too old - upate needed
			}
		}
		
		return 0; // no update needed
	}
	
	private function updateDone($db)
	{
		$db->addOrUpdateRecord('t_ini', array('f_key'=>'db_version'), array('f_value'=>$this->last_soll_db_version));
		$this->updatedDatabases++;
	}
	
	private function createCacheTable($db, $table_name)
	{
		assert( substr($table_name, 0, 2)=='t_' && substr($table_name, -5)=='cache' );
		
		$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS $table_name (
					f_key TEXT UNIQUE, 
					f_value TEXT,
					f_time TEXT"	// time, the record will expire
			.	");");
		$this->dbExecOrDie($db, "CREATE INDEX IF NOT EXISTS i_0 ON $table_name (f_time);");
	}
	
	
	static $inSetup = false;
	function databaseSetup()
	{
		// avoid recursion - G_DbSetup::databaseSetup() is called from G_Db::db(); the latter is also used here ...
		if( G_DbSetup::$inSetup ) return;
		G_DbSetup::$inSetup = true;
		
		if( $this->updatedDatabases ) die('databaseSetup already called!');

		// setup maps cache
		////////////////////////////////////////////////////////////////////////
				
		$db = G_Db::db('maps');
		if( $this->updateNeeded($db, 1) )
		{
			$this->createCacheTable($db, 't_geocodecache');
			
			$this->updateDone($db);
		}
	
		// setup crawler
		////////////////////////////////////////////////////////////////////////

		//$init_crawler_ini = false;
		$db = G_Db::db('crawler');
		$db->sql->exec("PRAGMA page_size=4096;"); // must be done before the first table is created: as we cache 10000 pages, we use up to 40 MB memory
		if( ($state=$this->updateNeeded($db, 2)) ) // if you change this version number, also alter G_VERSION
		{
			//if( $state == 2 ) {
			//	$init_crawler_ini = true; // this will copy some registry keys ...
			//}
			$this->createCacheTable($db, 't_robotstxtcache');
			
			$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS t_waiting (
				f_id INTEGER PRIMARY KEY,
				f_url TEXT UNIQUE,
				f_state INTEGER DEFAULT 0,"			// 0=waiting,  1=crawled/waitingToApplyToIndex,  6=error/retry on f_timeout,  9=keep record until f_time
			.  "f_sparam INTEGER DEFAULT 0,"		// state parameters: the time to retry (f_state=6) or to delete the record (f_state=9)
			.  "f_time INTEGER DEFAULT 0,"			// the time of the last action (adding/crawling/errors)
			.  "f_data TEXT,"						// serialized data, this will be applied to the index on next dance()
			.  "f_bodyhash TEXT"					// receives the value from vocabularyHash()
			. ");");
			
			$this->dbExecOrDie($db, "CREATE INDEX IF NOT EXISTS i_0 ON t_waiting (f_state);");
			$this->dbExecOrDie($db, "CREATE INDEX IF NOT EXISTS i_0 ON t_waiting (f_bodyhash);");
			
			$this->updateDone($db);
		}
		
		// setup index
		////////////////////////////////////////////////////////////////////////

		$db = G_Db::db('index');
		$db->sql->exec("PRAGMA page_size=4096;"); //  must be done before the first table is created: as we cache 10000 pages, we use up to 40 MB memory
		if( $this->updateNeeded($db, 1) ) // if you change this version number, also alter G_VERSION
		{
			$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS t_url (
				f_id INTEGER PRIMARY KEY, 
				f_url TEXT,
				f_img TEXT," // space separated list of images in the same order as the IMG-word-position bits (so in each of the 28 blocks there can be only one image)
			.  "f_hostid INTEGER,
				f_langid INTEGER,
				f_time INTEGER DEFAULT 0" // time, the URL was crawled the last time
			.");");
			$this->dbExecOrDie($db, "CREATE INDEX IF NOT EXISTS i_0 ON t_url (f_url);");

			// Create fulltext belonging to t_url (as fulltext tables do not allow normal indices as needed for f_url, we use a separate table).
			// The fulltext table has the implicit column `t_fulltext.docid`; we make sure, this is always equal to `t_url.f_id`
			//
			// We do _not_ explicit columns for title/keywords; this is because:
			// - it allows us to weight keywords in the title more than in the body (and sort out pages _only_ have the keywords in the title)
			// - the snippet() function only works on one column - however, this is no problem as we show the title separatly anyway 
			// - using one field for all would show double information lines
			//
			// Unfortunately, the unicode61 is currently not spreaded (2/2014); it if becomes available, we'll give it a try here.
			// 
			if( !$db->sql->table_exists('t_fulltext') ) {
				$fts = 'fts3';
				$db->sql->query("SELECT sqlite_version() AS ver;"); $db->sql->next_record(); $sqlite_version = $db->sql->fs('ver');
				if( version_compare($sqlite_version, '3.7.4',  '>=') ) {
					$fts = 'fts4';	// FTS4 is an extension to FTS3 and is available since sqlite 3.7.4; the greatest benefit of FTS4 is, that it is faster.
									// although we could remove some information from the fts4 index (about 1%-10%), currently (2/2014) we won't do this
									// as we do not know which features we'll need in the future.  So we take the "best" FTS we can get on the system.
				}
				$this->dbExecOrDie($db, "CREATE VIRTUAL TABLE t_fulltext USING $fts(f_title, f_body, tokenize=simple);");
			}
		
			// table: host (=domains =sites)
			//  with: f_followlinks: 
			//	-	don't follow 
			//	-	follow internal  
			//	-	follow direct external *pages* 				if a.com links to b.com/foo => b.com/foo is added, b.com/bar is not (TODO: we should allow this mode also for the last level N below, maybe use a #bit for this this)
			//	-	follow external *hosts* up to level N		level 1: if a.com links to b.com => all hosts in b.com are added, links from b.com are ignored
			//													level 2: if a.com links to b.com and b.com links to c.com => all hosts in b.com/c.com are added, links from c.com are ignored
			$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS t_host (
				f_id INTEGER PRIMARY KEY, 
				f_host TEXT,
				f_followlinks INTEGER DEFAULT 0
			);");
			
			$this->updateDone($db);
		}
		
		// setup registry 
		// (should be last as only G_VERSION is checked from outside and we 
		// won't catch partial updates this way)
		////////////////////////////////////////////////////////////////////////

		$db = G_Db::db('registry');
		if( $this->updateNeeded($db, 2) ) // if you change this version number, also alter G_VERSION
		{
			// init default language for the crawler - later, on crawling, maybe we do have the information we have now.
			$langobj = new G_Lang; 
			$langobj->set($_SERVER['HTTP_ACCEPT_LANGUAGE']);
			$db->addOrUpdateRecord('t_ini', array('f_key'=>'crawler_lang'), array('f_value'=>$langobj->getAsAcceptLanguage()));
			
			// create some addional tables
			$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS t_text (" // table: text (used eg. for imprint)
				.  "f_key TEXT UNIQUE, 
					f_text TEXT
				);");
				
			$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS t_user (" 
				.  "f_id INTEGER PRIMARY KEY, "
				.  "f_username TEXT UNIQUE, " // UNIQUE implies an INDEX
				.  "f_pw TEXT, 
					f_authtoken TEXT, 
					f_email TEXT, 
					f_admin INTEGER
				);");
				
			$this->dbExecOrDie($db, "CREATE TABLE IF NOT EXISTS t_peer (" 
				.  "f_id INTEGER PRIMARY KEY, "
				.  "f_url TEXT UNIQUE, " 		// UNIQUE implies an INDEX
				.  "f_state INTEGER DEFAULT 0"	// 1=active
				. ");");
				
			$this->updateDone($db);
		}
		
		/*
		if( $init_crawler_ini )
		{
			$db_crawler = G_Db::db('crawler');
			
			$db_crawler->addOrUpdateRecord('t_ini', array('f_key'=>'crawler_active'), array('f_value'=>$db->readValue('t_ini', array('f_key'=>'crawler_active'), 'f_value', Action_Cron::CRAWLER_ACTIVE_DEFAULT)));
			$db_crawler->addOrUpdateRecord('t_ini', array('f_key'=>'crawler_lang'), array('f_value'=>$db->readValue('t_ini', array('f_key'=>'crawler_lang'), 'f_value', 'en')));
		}
		*/
		
		if( floatval($db->readValue('t_ini', array('f_key'=>'g_version'), 'f_value', 0)) < G_VERSION )
		{
			$db->addOrUpdateRecord('t_ini', array('f_key'=>'g_version'), array('f_value'=>G_VERSION));
		}
		
		if( $this->updatedDatabases )
		{
			G_Log::log('critical', sprintf('%d databases updated to program version %s', $this->updatedDatabases, G_VERSION));
		}
		
		G_DbSetup::$inSetup = false;
	}
};
