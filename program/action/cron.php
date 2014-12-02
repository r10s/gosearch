<?php
/*******************************************************************************
Go Search Engine - Cron Jobs
****************************************************************************//**

Handling cron jobs:

`?a=cron` should be called frequently to let websites being crawled, for 
updating the index and for other purposes.

Therefore the system adds a reference to this class at the bottom of each 
HTML page delivered. So, as long as the system is used by users, crawling 
etc. will take place well.
 
As an alternative, you can set `G_DISABLE_POOR_MANS_CRON` in config.php and
execute `?a=cron` from within a real cron job.

`?a=cron` takes care, there is only one cron job running and also terminates 
itself in time (eg. shared spaces only allow an execution time of 30 
seconds).
 
Parameters for this action:

- `ret=text` - return a line of text; if unset, we return an image to satisfy 
  the browser and to avoid errors, the action can then be used as 
  follows:
  `<img src="?a=cron&ret=img&rnd=..." ... />`
  
- `rnd=<num>` - any random number to make sure, the file is not cached.
  
Currently, our cron implementation is only used for crawling, if there
are other jobs to do, we have to differ here.  Maybe we should use a file 
with pending jobs that is checked here.

The Crawler Log format:

	<time>\t<status>\t<url>\t<msgOrAddParam>

@author Björn Petersen

*******************************************************************************/

class Action_Cron /*NOT based upon G_HTML*/
{
	const RESERVED_SECONDS		= 10;	// seconds before our timeout, we do not start another task
	const RESTART_AFTER_SECONDS = 120;	// seconds to wait to start another cron job if the old one failed

	const MIN_EXECUTION_TIME	= 30;
	const MAX_EXECUTION_TIME	= 3600;
	
	const CRAWLER_ACTIVE_DEFAULT	= 1;	// during development/beta, the crawler is not active by default;
											// later, we can change this to 1
	
	const CHECK_CMD_EVERY_SECONDS = 10; // check the commands on startup and then every N seconds
	
	const SMALL_INDEX = 999;	// an index with less than SMALL_INDEX urls is treated as small; we'll dance more often on these index
								// (this is true esp. after a fresh installation where the admin may want to test after crawling soon,
								// later, for performance reasons, we'll dance less often)
	
	private $lastCmdCheckTime = 0;
	
	/***********************************************************************//**
	Get the number of seconds, the cron job shall (and can) run.
	In common, we try to run at least 30 seconds, max. 1 hour before we start 
	over.
	***************************************************************************/	
	function getMaxExecutionSeconds()
	{
		// find out the number of seconds the user wants us to run; 
		// if allowed by the server, we try to change this.
		$wanted_seconds = defined('G_OVERWRITE_MAX_EXECUTION_TIME')? intval(constant('G_OVERWRITE_MAX_EXECUTION_TIME')) : 120;
		if( $wanted_seconds>0 && !@ini_get('safe_mode') )
		{
			@set_time_limit($wanted_seconds);
		}
		
		// as set_time_limit() (same as ini_set('max_execution_time')) may fail for various reasons, 
		// read the _real_ time limit and correct it to the range of 30 seconds...1 hour
		$max_execution_seconds = intval(ini_get('max_execution_time')); 
		if( $max_execution_seconds < 30   ) { $max_execution_seconds = MIN_EXECUTION_TIME;   }	
		if( $max_execution_seconds > 3600 ) { $max_execution_seconds = MAX_EXECUTION_TIME; }
		
		return $max_execution_seconds;
	}
	
	
	private function checkForCommands($ending_time)
	{
		$cmdstack = new Crawler_CmdStack();
		$cmdexecuter = false;
		while( 1 ) // exit by break
		{
			$cmd = $cmdstack->pop();
			if( sizeof($cmd) == 0 || $cmdstack->getErr() ) {
				break; // nothing to do / error
			}
			
			if( !is_object($cmdexecuter) ) {
				$cmdexecuter = new Crawler_CmdExecuter();
			}
			$cmdexecuter->execute($cmd);
			
			if( time() > $ending_time ) {
				break; // time over
			}
		}
		
		$this->lastCmdCheckTime = time();
		
		if( is_object($cmdexecuter) && $cmdexecuter->justDeactivated() ) {
			return false; // stop execution
		}
		
		return true; // continue
	}
	
	private function setStatus($url)
	{
		$file =  G_DATA_ROOT . '/crawler.status';
		$handle = @fopen($file, 'w');
		if( $handle ) {
			@fwrite($handle, $url);
			@fclose($handle);
		}
	}
	
	/***********************************************************************//**
	Called if the user (directly or indirectly) opens the page ?a=cron
	***************************************************************************/
	function handleRequest()
	{
		// set
		// - the normal ending time, leave a little security buffer before we're killed by UNIX
		// - a time to force starting over by another thread (if anything goes wrong in this one)
		$starting_time 			= time();
		$max_execution_seconds	= $this->getMaxExecutionSeconds();
		$ending_time			= $starting_time + $max_execution_seconds - Action_Cron::RESERVED_SECONDS;
		$force_restart_time 	= $starting_time + $max_execution_seconds + Action_Cron::RESTART_AFTER_SECONDS;
		
	
		// satisfy the caller with an real image (transparent 1x1 GIF) 
		// or with a line of text (a valid JavaScript remarks)
		
		ignore_user_abort(true); 
		header("Cache-Control: no-cache, must-revalidate");	// HTTP/1.1
		header("Pragma: no-cache");							// HTTP/1.0
		if( $_GET['ret'] == 'json' ) {
			$refresh = $max_execution_seconds; 
			if( $refresh > 20*60 ) $refresh = 20*60; // at least every 20 Minutes, check if there is anything to do
			$content = "{\"refresh\": $refresh}";
			header('Content-Type: application/json');
			header("Content-Length: ".strlen($content));
			header("Connection: close");
			echo $content; 
			flush();	// see remark below
		}
		else if( $_GET['ret'] == 'img' ) {
			header('Content-Type: image/gif');
			header("Content-Length: 43");
			header("Connection: close");
			echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
			flush();	// this flush() _and_ content-length _and_ closing the connection is needed to satisfy the browser and stop it from displaying the loading indicator	
						// for the document, everything is done
						// however, on the server, we're just starting ...
						// (NB: take care for forceFlush() - this function may also output some HTML!)
		}
		else {
			echo 'starting cron job, see the log files for details.';
		}
		
		// here comes the more interesing part:
		// really start the jobs 
		// ---------------------------------------------------------------------	

		// cron already running? if so, we do not start over but return at once
		if( g_is_db_locked() ) {
			G_Log::log('crawler', 'ok_alreadyrunning');
			return;
		}
		
		// if we go here, we can be sure, no other instance is running on the server:
		// lock the cron status and do what to do - but take care of the max. execution time!
		if( !g_lock_db($force_restart_time) ) { G_Log::log('crawler', "err_lockcreate\t" . G_DB_LOCK_FILE); return; }
		register_shutdown_function('g_unlock_db');

		$db_crawler = G_Db::db('crawler');
		
		$this->checkForCommands($ending_time);
		
		if( !G_Registry::iniRead('crawler_active', Action_Cron::CRAWLER_ACTIVE_DEFAULT) ) {
			G_Log::log('crawler', 'ok_crawlerdisabled');
			return;
		}	
		
		G_Log::log('crawler', "ok\tcrawling for ~" . $max_execution_seconds . " seconds");

		// shall we dance this round?
		$do_dance = false;
		$db_crawler->sql->query('SELECT COUNT(*) AS cnt FROM t_waiting WHERE f_state=1;');
		$db_crawler->sql->next_record();
		$waiting_for_dance = intval($db_crawler->sql->fs('cnt'));
		if( $waiting_for_dance > 5 ) {
			$db_index = G_Db::db('index');
			$db_index->sql->query('SELECT COUNT(*) AS cnt FROM t_url;');
			$db_index->sql->next_record();
			$total_in_index = intval($db_index->sql->fs('cnt'));
			if( $total_in_index < $this::SMALL_INDEX
			 || $waiting_for_dance > 500 ) {
				$do_dance = true;
			}
		}
		
		// our crawling loop
		$crawler = new Crawler_Base();
		while( 1 )
		{
			if( $do_dance ) {
				$crawler->dance($ending_time);
				$do_dance = false;
			}
			else {
				$pi = $crawler->step();
				$this->setStatus(is_object($pi['url'])? $pi['url']->getAbs() : '-');
				if( $pi['err'] == 'err_nothingiswaiting' ) {
					sleep(2); // sleep a moment, then try over (this seems to be better than a break; as this would recrate the cron-job over and over)
				}
			}
			
			if( time() > $ending_time ) {
				break; // time out
			}
			
			// check for commands
			if( time() > $this->lastCmdCheckTime + Action_Cron::CHECK_CMD_EVERY_SECONDS ) {
				if( !$this->checkForCommands($ending_time) ) {	
					break; // crawler just disabled, stop it now
				}
				
				if( time() > $ending_time ) {
					break; // time out
				}
			}

		}

	}
	

	
};



