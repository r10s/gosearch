<?php 
/*******************************************************************************
Go Search Engine - The Crawler
****************************************************************************//**

Our crawler.  The crawler is interruptable at any time; all status information 
are stored in the database.

Some notes:

- **Strict**: Whereever there can be any doubt in the meaning of tags or
  URLs, we follow the strict interpretation:
  
	- Eg. `<a href="www.link.de">` on the page www.foo.de/bar/
	  will result in `http://www.foo.de/bar/www.link.de` - not in 
	  `http://www.link.de` 
	- Eg. for the status codes: 404 means "Not Found" - it does not matter how
	  much text is coming up; 
	  
  Otherwise, if we begin to interpret the results, we would run into much larger 
  problems.

- We support **Redirects** of type Header 300, 301, 302, 303, 307, 
  308, <link rel="canonical" href="..."> and <meta http-equiv="refresh" content="..." />.
  
	- a meta-refresh is only executed if there is no canonical statement 
	  (otherwise, we're already at the desired destination).
  
	- The index will always contain the destination URL and content,
	  not the way to it
  
    - NB: We _must_ follow the canonical statements as they may lead to
	  completely different pages, eg. assume a index?page1 and index?page1 both 
	  leading to index?all containing the content of both pages (see the 
	  example at http://googlewebmastercentral.blogspot.de/2013/04/5-common-mistakes-with-relcanonical.html )
  
- **Frames** are treated as deprecated are not supported actively.  However, 
  as we ignore `<frameset>`, `<frame>`, `<noframes>` and `<iframe>` completely, 
  the remaining HTML-code may give us the link to the content or the content 
  itself. 

- **Spaces** in tags: see http://www.w3.org/TR/REC-xml/#sec-starttags :
  While `<a >` and `</a  >` are okay, `< a >` for `< /a >` or `< / a >` are not 
  allowed.  However, as we want to get rid of some stuff, we allow spaces if 
  they do not conflict with sth. else.

- **Parameters** in URLs are currently treated as any other URL part; maybe we
  should change this to avoid infinite loops (imagine a "next page" link) and 
  maybe spam.  However, we would also need a more generic approach as using
  mod_rewrite, the parameters can also be in the path just a click away.
 
- **Images** - when we have an image search; images will be used if:
	- width is set and min. 200

	- alt is set, however, only the first 10 words go to the index (avoid spam  
	  by hidden content)
	
	- only the two largest images are taken from a page
 
@author Björn Petersen

*******************************************************************************/

class Crawler_Base
{
	const RETRY_DAYS	= 28; // if a re-crawl fails, we try again in about 1 month before we delete the URL from the index
	const DELETE_DAYS	= 28; // we delay deletion from t_waiting for some days; this avoids crawling the same errors and redirects over and over
	
	public $disallowed_ext;
	public $allowed_prot;
	public $lang_obj;
	public $robotstxtObj;
	public $schedulerObj;
	
	
	/***********************************************************************//**
	Construct a Crawler_Base obect. After construction, URLs can be crawled 
	using crawlUrl().
	***************************************************************************/
	function __construct()
	{
		$this->disallowed_ext	= G_Tools::str2hash('avi, gif, jpg,jpe,jpeg, mp3,mp4,mpg,mpeg, ogg,ogv, pdf, png, svg, xml');
		$this->allowed_prot		= G_Tools::str2hash('http');
		$this->lang_obj			= new G_Lang(G_Registry::iniRead('crawler_lang', ''));
		
		$db = G_Db::db('crawler');
		
		$this->robotstxtObj	= new Crawler_RobotsTxtDb($db);
		$this->robotstxtObj->setUserAgent(G_USER_AGENT);
		
		$this->schedulerObj = new Crawler_Scheduler($db);
	}

	
	/***************************************************************************
	TODO
	***************************************************************************/
	private function isSoft404(&$pi)
	{
		return false;
	}

	

	/***********************************************************************//**
	Crawl a single URL
	
	Get every required and possible information about an URL.
	As also used for testing, crawlUrl() does not save or modidy anything - 
	this should be done by the caller.
	
	@param G_Url $url	the URL to crawl
	@return array $pi	array with information about the crawled URL
	***************************************************************************/
	function crawlUrl(G_Url $url__)
	{
		$curr_url = clone $url__; // do not modify the given object
		
		$benchmark_http  = new G_BENCHMARK;
		$benchmark_parse = new G_BENCHMARK;
		$benchmark_http->start();
		
		// follow supported redirects, see http://de.wikipedia.org/wiki/HTTP-Statuscode
		$http_request = new G_HttpRequest();
		$http_request->setUserAgent(G_USER_AGENT);
		$http_request->setLanguage($this->lang_obj->getAsAcceptLanguage());
		$redirects = array();
		while( 1 )
		{
			// rough checking for robots.txt - this should be done, before one byte of the URL is loaded
			// after all recirects, we do a "real" check and also load the robots.txt from via HTTP
			if( !$this->robotstxtObj->urlAllowed($curr_url, Crawler_RobotsTxtDb::LOAD_FROM_DISK) ) {
				return array('url'=>$curr_url, 
					'err'=>'err_norobots', 'err_obj'=>'robots.txt');
			}
			
			// load the URL ... 
			$http_request->setUrl($curr_url);
						
			$pi = $http_request->sendRequest();
			
			$pi['benchmark']['http']  = $benchmark_http;
			$pi['benchmark']['parse'] = $benchmark_parse;
			$pi['redirects'] = $redirects;
			if( $pi['err'] ) {
				return $pi; // error - should we delete the document in this case?
			}
			
			// check the HTTP-error-code
			$code = $pi['header']['code'];
			if( $code == G_HttpRequest::MULTIPLE_CHOICES_300
			 || $code == G_HttpRequest::MOVED_PERMANENTLY_301 
			 || $code == G_HttpRequest::FOUND_302
			 || $code == G_HttpRequest::SEE_OTHER_303 // for 304, see below
			 || $code == G_HttpRequest::TEMPORARY_REDIRECT_307
			 || $code == G_HttpRequest::PERMANENT_REDIRECT_308 )
			{
				// got a redirect ...
				$redirects[] = $curr_url->getAbs();
				if( sizeof($redirects) > 8 ) {
					$pi['err'] = 'err_toomanyredirects'; return $pi; // error
				}
				else {
					$temp = $pi['header']['location']; // start over with new URL ...
					if( $temp == '' ) {
						$pi['err'] = 'err_badlocation'; return $pi; // error
					}
					else if( !$curr_url->change($temp) ) {
						$pi['err'] = $curr_url->error; return $pi; // error
					}
				}
			}
			else if( $code != G_HttpRequest::OK_200 ) // for any status but "200 OK" we delete the url and return an error.
			{
				// got an error
				$pi['err'] = 'err_badstatus'; $pi['err_obj'] = $code; return $pi; // error
			}
			else
			{
				// got content, parse it ...
				$benchmark_http->stop();
				$benchmark_parse->start();
				
				// find out the parser to use; parse document
				G_Tools::splitContentType($pi['header']['content-type'], $ct, $ctparam);
				$pi['charset'] = $ctparam['charset']!=''? $ctparam['charset'] : 'ISO-8859-1'; // default, see w3c.org
				$pi['lang'] = $pi['header']['content-language']!=''? $pi['header']['content-language'] : '';
				if( $ct == 'text/html' ) {
					$parser = new Crawler_ParseHtml();
					$parser->parseDocument($this, $pi);
				}
				else {
					$pi['err'] = 'err_badcontenttype'; $pi['err_obj'] = $ct;
				}
				
				if( $pi['err'] ) {
					// ... error
					return $pi; 
				}
				else if( $pi['refresh'] ) {
					// ... another redirect as <meta http-equiv="refresh" ... >
					$redirects[] = $curr_url->getAbs();
					if( sizeof($redirects) > 8 ) {
						$pi['err'] = 'err_toomanyredirects'; return $pi; // error
					}
					else if( !$curr_url->change($pi['refresh']) ) {
						$pi['err'] = $curr_url->error; return $pi; // error
					}
					$benchmark_parse->stop();
					$benchmark_http->start();
				}
				else {
					// ... content parsed and fine so far; continue out of loop
					break;
				}
			}
		}
		
		// after we followed all redirects etc. do a "deep" robots.txt check; this implies loading the file from the server, if needed
		if( !$this->robotstxtObj->urlAllowed($pi['url'], Crawler_RobotsTxtDb::LOAD_FROM_HTTP) ) {
			return array('err'=>'err_norobots', 'err_obj'=>'robots.txt');
		}
			
		// check content for some specials
		if( $this->isSoft404($pi) ) {
			if( !$pi['err'] ) { $pi['err'] = 'err_soft404'; } return $pi;
		}
		
		// check the language
		if( !$pi['lang'] ) {
			$temp = $this->lang_obj->guessLangFromText($pi['body']);
			if( $temp['complete_guess'] ) {
				$pi['lang'] = $temp['lang'];
				$pi['lang_guessed'] = true;
				if( $pi['lang']=='' ) { $pi['err'] = 'err_lang'; $pi['err_obj'] = 'language unknown but known uninteresting'; return $pi; }
			}
		}
		
		if( $pi['lang'] ) { // lang may really still be empty if 'complete_guess' above is false
			if( !$this->lang_obj->isMatchingLang($pi['lang']) ) {
				$pi['err'] = 'err_lang'; return $pi;
			}
		}		
		
		// check for duplicates
		$pi['bodyhash'] = G_String::vocabularyHash($pi['body']);
		
		// sucess
		$benchmark_parse->stop();
		return $pi;
	}

	
	
	/***********************************************************************//**
	The function crawls the next waiting URL from crawler.sqlite, if any.
	The caller must encapsulated a call to this function by a lock! (we do not 
	to this here to allow performant loops).
	 
	@return array $pi	Information about the crawled page; mainly for 
						information or for debugging or display purposes.
						On errors $pi['err'] is set.
						If there is nothing to do, $pi['err'] is set to 
						`err_nothingiswaiting`.
	***************************************************************************/
	function step()
	{
		if( !$GLOBALS['db_locally_locked'] ) die('db not locked!');
		
		// get next URL - while the real (redirected, corrected) url will be in $pi['url'], $step['url'] is needed to update the table later
		$step = $this->schedulerObj->getNextUrl();
		if( $step['err'] ) {
			return $step; // error, most times this will be `err_nothingiswaiting`
		}

		// prepare all information about the crawled URL, on the next dance() this gets executed
		$now = time();
		$pi = $this->crawlUrl(new G_Url($step['url']));
		
		// success on crawling? check for duplicates!
		$db_crawler = G_Db::db('crawler');
		if( !$pi['err'] && $pi['body'] && !$pi['noindex'] ) {
			$db_crawler->sql->query("SELECT f_url FROM t_waiting WHERE f_bodyhash=".$db_crawler->sql->quote($pi['bodyhash']));
			if( $db_crawler->sql->next_record() ) {
				$pi['err'] = 'err_duplicate ('.$db_crawler->sql->fs('f_url').')';
			}
		}
		
		// deep result inspection
		$data = array();
		$data['to_delete'] = $pi['redirects']; // errors or not: delete all redirects
		if( $pi['err'] )
		{
			if(
			    substr($pi['err'], 0, 8) == 'err_http'		// list recoverable errors here, things that may change if we try again in 2 weeks or so.
															// if in doubt, add the error to the list and let the system try over.
			 ) 
			{
				// here we go for errors that may be temporary server faults etc.
				if( !G_HttpRequest::isInternetConnectionWorking() ) {
					global $g_logged_internet_not_working;
					if( !isset($g_logged_internet_not_working) ) {
						$g_logged_internet_not_working = true;
						G_Log::log('crawler', 'err_internetnotworking ('. $pi['err'] . ")\t" . $step['url']);
						sleep(1);
					}
					return $pi; // done, just try again when the internet connection is working again
				}
				
				if( $step['state'] != 6 ) {
					$retry_time = time() + Crawler_Base::RETRY_DAYS*24*60*60;
					$db_crawler->sql->exec("UPDATE t_waiting SET f_time=$now, f_state=6, f_sparam=$retry_time WHERE f_url=".$db_crawler->sql->quote($step['url']));
					G_Log::log('crawler', $pi['err'] . "\t" . $step['url'] . " --DELAYED--");
					return $pi; // done, retry again in a couple of days
				}
			}

			$data['to_delete'][] = $pi['url']->getAbs();
			G_Log::log('crawler',  $pi['err'] . "\t" . $step['url']);
		}
		else if( $pi['body']=='' || $pi['noindex'] )
		{
			// links will be added, but the url's words will be deleted/not added to the index
			$data['to_delete'][] = $pi['url']->getAbs();
			G_Log::log('crawler', ($pi['noindex']? "ok_noindex\t" : "ok_nobody\t") . $step['url']); 
		} 
		else
		{
			// success on crawling
			$data['url']	= $pi['url']->getAbs();
			$data['lang']	= $pi['lang'];
			$data['title']	= $pi['title'];
			$data['body']	= $pi['body'];
			$data['img']	= is_array($pi['img'])? implode(' ', $pi['img']) : '';
			
			G_Log::log('crawler', "ok\t" . $step['url']); // verbose information ...
		}

		// find out the links to add by skipping the ones already waiting or already in the index
		$pi['benchmark']['db'] = new G_BENCHMARK;
		$pi['benchmark']['db']->start();

		$links = array();
		if( is_array($pi['links']) ) {
			foreach( $pi['links'] as $url_ob ) {
				$links[$url_ob->getAbs()] = 1;
			}
		}
		
		$db_crawler->sql->query("SELECT f_url FROM t_waiting WHERE f_url IN (".$db_crawler->quoteKeys($links).")");
		while( $db_crawler->sql->next_record() ) {
			unset($links[$db_crawler->sql->fs('f_url')]);
		}

		if( sizeof($links) ) {
			$db_index = G_Db::db('index'); // reading only, no closing needed
			$db_index->sql->query("SELECT f_url FROM t_url WHERE f_url IN (".$db_crawler->quoteKeys($links).")");
			while( $db_index->sql->next_record() ) {
				unset($links[$db_index->sql->fs('f_url')]);
			}
		}
					
		// remember the add and/or delete job for the next dance()
		$db_crawler->sql->beginTransaction();
			
			foreach( $links as $url=>$value ) {
				$db_crawler->sql->exec("INSERT INTO t_waiting (f_url, f_time) VALUES (".$db_crawler->sql->quote($url).", $now)"); // see [*] in sqlite.php for remark about multiple inserts
			}
		
			$db_crawler->sql->exec("UPDATE t_waiting SET f_state=1, 
														 f_time=$now,
														 f_data=".$db_crawler->sql->quote(serialize($data)).", 
														 f_bodyhash=".$db_crawler->sql->quote($pi['bodyhash'])." 
												   WHERE f_url=".$db_crawler->sql->quote($step['url']));
			
		$db_crawler->sql->commit();
		
		$pi['benchmark']['db']->stop();
		return $pi;
	}
	
	
	
	/***********************************************************************//**
	Function moves all pending data from crawler.sqlite to index.sqlite
	The caller must encapsulated a call to this function by a lock! (we do not
	to this here to allow performant loops and/or combinations with step()).
	
	Speed: about 20 URL/s on T530/Wamp with a fresh index, later up to 
	50-200 URL/s
	
	@param none		
	@return boolean	true on success, false on errors
	***************************************************************************/
	function dance($ending_time = 0)
	{
		if( $ending_time == 0 ) $ending_time = time() + 20; // run for 20 seconds
		if( !$GLOBALS['db_locally_locked'] ) die('db not locked!');

		$di = array();
		$di['benchmark']['crawler_read'] = new G_BENCHMARK;
		$di['benchmark']['index_write']  = new G_BENCHMARK;

		G_Log::log('crawler', "ok\tdance started");
		
		$deletion_tried = false;
		$total_cnt = 0;
		while( 1 ) 
		{
			// apply a number of records to the index
			$di['benchmark']['crawler_read']->start();
			$applied = array();
			$db_crawler = G_Db::db('crawler');
			$db_index   = G_Db::db('index');
			$db_index->sql->beginTransaction();	// for 100 URLs it takes on T530/Wamp 30 seconds if we use a URL-based transaction and only 5 seconds for a 100-URLs-based transaction!
												// larget amounts of URLs have no larger effect - 200 URLs take 10 seconds - but have the disadvantage of failures and memory usage
				$db_crawler->sql->query("SELECT f_url, f_time, f_data FROM t_waiting WHERE f_state=1 ORDER BY f_time LIMIT 100;");
				while( $db_crawler->sql->next_record() ) 
				{
					$url = $db_crawler->sql->fs('f_url'); // may be different from $data['url'] (redirects ...)
					$time_crawled = $db_crawler->sql->fs('f_time');
					$data = unserialize($db_crawler->sql->fs('f_data'));
					if( is_array($data) ) {
						$di['benchmark']['crawler_read']->stop();
							$di['benchmark']['index_write']->start();
								$this->applyInIndex($db_index, $data, $time_crawled);
								$total_cnt++;
							$di['benchmark']['index_write']->stop();
						$di['benchmark']['crawler_read']->start();
					}
					
					$applied[$url] = 1;
					$di['applied_urls'][] = $url;
					
					if( time() > ($ending_time-1)/*leave one second for delete*/ ) {
						break;
					}
				}
				
			$db_index->sql->commit();
			
			// remove the record from the database - even on errors, otherwise we won't continue to the loop ...
			if( sizeof($applied) ) {
				// direct approach would be... $db_crawler->sql->query("DELETE FROM t_waiting WHERE f_url IN (".$db_crawler->quoteKeys($applied).")");
				// ... however, in this case we would try over the same URLs several times (errors, redirects)
				// instead, we mark the URL for deltion (f_state=9) and delete it in some days ...
				$del_time = time() + Crawler_Base::DELETE_DAYS*24*60*60;
				$db_crawler->sql->query("UPDATE t_waiting SET f_state=9, f_sparam=$del_time, f_data=''  WHERE f_url IN (".$db_crawler->quoteKeys($applied).")");
				if( !$deletion_tried ) {
					// ... and, some days later, we're here and delete the record physically
					$db_crawler->sql->query("DELETE FROM t_waiting WHERE f_state=9 AND f_sparam<".time());
					$deletion_tried = true;
				}
			}
			
			if( sizeof($applied)==0 || time() > ($ending_time-1) ) {
				break;
			}			
		}
		
		// done, so far, close the modified files
		G_Db::dbClose('index'); 
		$di['benchmark']['crawler_read']->stop();
		$di['benchmark']['index_write']->stop();
		G_Log::log('crawler', "ok\tdanced with ".$total_cnt.' urls');
		
		return $di;
	}
	
	
	/***********************************************************************//**
	Function add a single record to the index
	***************************************************************************/
	private function applyInIndex(&$db_index, &$data, $time_crawled)
	{
		// remove stuff from the index, eg. redirects or errors
		if( is_array($data['to_delete']) )
		{
			foreach( $data['to_delete'] as $url_to_delete ) 
			{
				$db_index->sql->query("SELECT f_id FROM t_url WHERE f_url=".$db_index->sql->quote($url_to_delete));
				if( $db_index->sql->next_record() ) {
					$url_id = $db_index->sql->fs('f_id');
					$db_index->sql->exec("DELETE FROM t_url WHERE f_id=$url_id");
					$db_index->sql->exec("DELETE FROM t_fulltext WHERE docid=$url_id");
					// TODO/TOCHECK: maybe, from time to time, we should cleanup the word table and the  host table (if f_followlinks=0 (for no user settings))
				}
			}
		}
	
		if( $data['url'] ) 
		{
			// add the host record, if it does not exist
			$url_obj = new G_Url($data['url']);
			if( trim($url_obj->host) == '' ) { 
				G_Log::log('crawler', "err_badhostname\t".$data['url']);
				return false; // error
			}
			$db_index->sql->query("SELECT f_id FROM t_host WHERE f_host=".$db_index->sql->quote($url_obj->host));
			if( $db_index->sql->next_record() ) {
				$host_id = $db_index->sql->fs('f_id');
			}
			else {
				$db_index->sql->exec("INSERT INTO t_host (f_host) VALUES (".$db_index->sql->quote($url_obj->host).");");
				$host_id = $db_index->sql->insert_id(); 
				if( $host_id <= 0 ) { 
					G_Log::log('crawler', "err_cannotaddhost\t".$url_obj->host);
					return false; // error
				}
			}
			
			// get the language ID
			$lang_id = G_Lang::str2id($data['lang']);
			
			// add the URL record, if it does not exist - if it does exist, remove old words from the index
			$quoted_title = $db_index->sql->quote(G_TokenizeHelper::asciify($data['title']));
			$quoted_body  = $db_index->sql->quote(G_TokenizeHelper::asciify($data['body']));
			$quoted_img   = $db_index->sql->quote($data['img']);
			$db_index->sql->query("SELECT f_id FROM t_url WHERE f_url=".$db_index->sql->quote($data['url']));
			if( $db_index->sql->next_record() ) {
				$url_id = $db_index->sql->fs('f_id');
				$db_index->sql->exec("UPDATE t_url SET f_time=$time_crawled, f_img=$quoted_img WHERE f_id=$url_id;");
				$db_index->sql->exec("UPDATE t_fulltext SET f_title=$quoted_title, f_body=$quoted_body WHERE docid=$url_id;");
			}
			else {
				$db_index->sql->exec("INSERT INTO t_url (f_url, f_img, f_hostid, f_langid, f_time) VALUES (".$db_index->sql->quote($data['url']).", $quoted_img, $host_id, $lang_id, $time_crawled);");
				$url_id = $db_index->sql->insert_id(); 
				if( $url_id <= 0 ) { 
					G_Log::log('crawler', "err_dance\t".$data['url']);
					return false; // error
				}
				$db_index->sql->exec("INSERT INTO t_fulltext(docid, f_title, f_body) VALUES($url_id, $quoted_title, $quoted_body);");
			}
			
		}

		return true;
	}
	

};


