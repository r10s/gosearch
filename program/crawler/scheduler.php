<?php
/*******************************************************************************
Go Search Engine - Crawler Scheduler
****************************************************************************//**

Crawler_Scheduler is used to find out the next URL to crawl.

TOOD: Build a scheduler, 
- do not crawl the same URL/Domain over and over
- if the ok/err quotient gets too small (eg. < 1.5), prefer another Domain
- allow setting some max. values: pagesPerDomain, pagesPerLinkedDomain etc.
  CSS-like-Syntax idea for this: 
	domain.com 			{ pages:1000; } 
	domain.com > *		{ pages:100; }
	domain.com > * > *	{ pages:10; }
	* 					{ pages:1; nofollow; }

TODO: we should wait some (10?) seconds between loading stuff from the same 
host ... 
		
NB: the condition `f_state=0 OR (f_state=6 AND f_sparam<".time().")` is very 
slow:
- 4 s for a 50 MB entry with ~200K URLs (collected in less than 24 hours),
- so, most is consumed to take the next URL ...
- there is an index on f_state, however, this does not help :-( maybe we should 
  do sth. like MIN(f_time), if the index can help with that
Solution: we just do a `ORDER BY rowid` which takes advantage from the index

@author Björn Petersen

*******************************************************************************/

class Crawler_Scheduler
{
	private $db;
	
	function __construct(G_Db $db)
	{
		$this->db = $db;
	}

	/***********************************************************************//**
	Returns the next URL to crawls plus some information about it
	***************************************************************************/	
	function getNextUrl()
	{	
		$ret = array();
																												//	  was: ORDER BY f_time - but this is _way_ slower, see above
		$this->db->sql->query("SELECT f_url, f_state FROM t_waiting WHERE f_state=0 OR (f_state=6 AND f_sparam<".time().") ORDER BY rowid LIMIT 1;");
		if( !$this->db->sql->next_record() ) {
			$ret['err'] = 'err_nothingiswaiting'; return $ret; // error
		}
		
		$ret['state'] = $this->db->sql->fs('f_state');
		$ret['url']   = $this->db->sql->fs('f_url');		
		
		// We mark the URL for retrying/deletion (depending on its state) - the final, correct state is written after parsing.
		// So, if the system crashes on parsing an URL, it is not returned over and over from this function.
		// (crashes may happen eg. on some RegEx, see the {1,}-Bug)
		$tempState = $ret['state']==0? 6 : 9;
		$tempTime = time() + ($ret['state']==0?Crawler_Base::RETRY_DAYS : Crawler_Base::DELETE_DAYS)*24*60*60;
		$this->db->sql->exec("UPDATE t_waiting SET f_state=$tempState, f_sparam=$tempTime WHERE f_url=".$this->db->sql->quote($ret['url']));
		
		return $ret;
	}
};
