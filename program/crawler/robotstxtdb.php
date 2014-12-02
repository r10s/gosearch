<?php
/*******************************************************************************
Go Search Engine - Handling robots.txt
****************************************************************************//**

see Crawler_RobotsTxtFile for more details about robots.txt

@author Björn Petersen

*******************************************************************************/

class Crawler_RobotsTxtDb
{
	private $cache;
	private $userAgent;
	private $objects;
	
	const LOAD_FROM_DISK = 0x01;
	const LOAD_FROM_HTTP = 0x03;
	
	function __construct(G_Db $db)
	{
		assert( (Crawler_RobotsTxtDb::LOAD_FROM_HTTP & Crawler_RobotsTxtDb::LOAD_FROM_DISK) == Crawler_RobotsTxtDb::LOAD_FROM_DISK );
		
		$this->cache = new G_Cache($db, 't_robotstxtcache', 2*G_Cache::DAYS);
		$this->userAgent = '';
		$this->objects = '';
	}
	
	
	/***********************************************************************//**
	Set the user agent, the rules should be taken from.  For "any" user agent,
	use "*"
	***************************************************************************/
	function setUserAgent($userAgent)
	{
		$this->userAgent = $userAgent;
	}
	

	/***********************************************************************//**
	urlAllowed() checks if the URL is allowed for robots. 
	
	For this purpose:
	
	- it checks the already loaded robots objects (always, super fast)
	
	- it checks its own database (if LOAD_FROM_DISK flag is given, for many 
	  links this may result in lots of i/o with, maybe, low benefit, so this
	  option should only be used when crawling an explicit URL)
	  
	- finally, if needed, it loads the corresponding robots.txt with an 
	  HTTP-request (if LOAD_FROM_HTTP flag is given, as this is very slow it 
	  should only be used when we know we have a "real" URL, eg. after redirects 
	  are followed)	

	If the information cannot be found, we assume the URL to be allowed.
	***************************************************************************/		
	function urlAllowed(G_Url $url, $flags)
	{
		assert( $this->userAgent != '' );
		
		// get the key
		$keyUrl = clone $url;
		$keyUrl->change('/robots.txt');
		$key = $keyUrl->getAbs() . '#v3'; // if anything in the stored data changes, change this version number and the cache gets updated
		
		// load robots.txt to internal object, if not yet done
		if( !isset($this->objects[$key]) ) 
		{
			if( !($flags & Crawler_RobotsTxtDb::LOAD_FROM_DISK) ) {
				return true; // super-fast, not even a database lookup
			}
			
			if( ($ser=$this->cache->lookup($key))!==false ) 
			{	
				$this->objects[$key] = new Crawler_RobotsTxtFile($this->userAgent);
				$this->objects[$key]->setFromSerialized($ser);
			}
			else
			{
				if( !($flags & Crawler_RobotsTxtDb::LOAD_FROM_HTTP) ) {
					return true; // no deep check, no data available: we check later 
				}
					
				$this->objects[$key] = new Crawler_RobotsTxtFile($this->userAgent);
				$this->objects[$key]->setFromUrl($keyUrl); // this is the slowest statment as this requires a full HTTP-request
				
				$this->cache->insert($key, $this->objects[$key]->getSerialized());
			}
		}
		
		// check the url
		return $this->objects[$key]->urlAllowed($url);
	}	
};
