<?php 
/*******************************************************************************
Go Search Engine - Handling robots.txt
****************************************************************************//**

File format of robots.txt

	# comment
	User-agent: my-name 
	Disallow:   /

	User-agent: * # another comment
	Disallow:   /path

- the user-agent `*` matches all user-agents, however, other wildcards are not
  allowed in the user agent directive (eg. `User-agent: Goog*` is not allowed)
  
- At least one (Disallow) statement should be present in a record, otherwise the
  User-agent-record is assumed to be allowed everything.  
  
- The Disallow parameter is the _beginning_ of the disallowed path; we also 
  support the simple wildcard `*` end the line-end anchor `$`.

- An empty (left out) disallow parameter indicates, that all URLs are allowed.
  
- Sitemap statements are user-agent independent!

- We also support `Allow:` which are parsed only if we found a matching disallow
  rule (some documentation says, it should be always written _before_ a
  corresponding disallow, this is not true at least for
  http://www.google.com/robots.txt :
  
	Disallow: /catalogs
	Allow:    /catalogs/about

  Other resources say, if there are conflicting Disallow/Allow statements, the 
  _most specific_ (the longest string) wins.  Conflichts: Google says, sth. like 
  `Disallow: /page  Allow: /*.htm` is just undefined for the URL `/page.html`)

  Finally, we leave it simple and let allow win, if in doubt.
	
There are other interesting extensions eg. `Host`, `Clean-param:`, `Sitemap:`
and `Crawl-delay:` (see http://help.yandex.com/webmaster/controlling-robot/robots-txt.xml ),
however, they seem not to be very spreaded and `Host` and `Clean-param:` are
covered by the canonical tag.

NB: Performance: We put all disallow- and allow rules together into a single
RegEx as `#(^/path1|^/p2)#` (Of course we convert eg. `*` to `.*` or  `.` to 
`\.` to be RegEx-conform.).  This is _way_ faster than doing several RegEx!  Eg.
Wikipedias robots.txt it is handled about 100 times faster when checking all
links in a larger article! (During the optimization we also found out that  
word-begin-matching using `preg_match()` is ~2 times faster than a 
`substr($urlPath, 0, strlen($pattern)) == $pattern`.)

@author Björn Petersen

*******************************************************************************/

class Crawler_RobotsTxtFile
{
	private $userAgent = '';	
	private $rules;
	private static $s_wildcard2regexTr;
	
	function __construct($userAgent = '')
	{
		// wildcard2regexTr is used by the private function ruleMatch() to transform a wildcard with `*` and `$` to a regular expression
		if( !is_array(Crawler_RobotsTxtFile::$s_wildcard2regexTr) ) {
			Crawler_RobotsTxtFile::$s_wildcard2regexTr = array(
				  "\\"=> "\\\\" 
				, "^" => "\\^" // leave the `$` sign as is
				, "." => "\\." 
				, "|" => "\\|" 
				, "?" => "\\?" 
				, "*" => ".*" 
				, "+" => "\\+" 
				, "#" => "\\#" 
				, "(" => "\\(" 
				, ")" => "\\)"
				, "[" => "\\[" // escaping `]` may be an error!
				, "{" => "\\{" // escaping `}` may be an error!
			);
		}
		$this->userAgent = $userAgent;
	}
	
	/***********************************************************************//**
	setFromUrl() and setFromStr() take a robots.txt definition (either by URL
	or by string) and parse them to the $rules array as follows:
		
		$rules = array(
			'user-agent' => array(
				<name> => array(
					'allow' => array(<rules> ...)
					'disallow => array(<rules> ...)
				);
			),
		);
		
	***************************************************************************/
	function setFromUrl(G_Url $url)
	{
		assert( $this->userAgent!='' );
		
		$ob = new G_HttpRequest(); 
		$ob->setUserAgent($this->userAgent);
		$ob->setUrl($url);
		$pi = $ob->sendRequest();
		if( $pi['err'] || $pi['header']['code']!=G_HttpRequest::OK_200) {
			$this->setFromStr(''); // error, this is quite normal if there is no robots.txt; init to allow everything
			return false;
		}
		return $this->setFromStr($pi['content']); // robots.txt loaded successfully
	}

	function setFromStr($lines)
	{
		// init
		$this->rules = array('user-agent'=>array());
		$curr_user_agent = '';
		
		// go through all lines, collect the rules
		$collect = array();
		$lines = explode("\n", $lines);
		foreach( $lines as $line ) 
		{
			// remove any comments from line
			if( ($p=strpos($line, '#')) !== false ) { 
				$line = substr($line, 0, $p); 
			}
			
			// split line into command: param
			if( ($p=strpos($line, ':')) === false ) { 
				continue; // no double-point: continue with next line
			} 
			
			$command = strtolower(trim(substr($line, 0, $p))); 
			$param   = trim(substr($line, $p+1));
			
			// collect the rules
			switch( $command )
			{
				case 'user-agent':	
					$curr_user_agent = strtolower($param);	// the last User-agent always received the following rules
					$collect[$curr_user_agent]['disallow'] = array(); // make sure, we init the user-agent disallow list - an empty disallow list may be important, if more specific user-agents disallow stuff
					break;
				
				case 'disallow':
				case 'allow':
					if( $param!='' // an empty `Disallow:` means, everything is allowed (we've handled this above); an empty `Allow:` is ignored as everything is allowed by default
					 && $curr_user_agent != '' ) { 
						$param = '^' . strtr($param, Crawler_RobotsTxtFile::$s_wildcard2regexTr); 
						$collect[$curr_user_agent][$command][$param] = 1; 
						// NB: Maybe we should convert non-url characters to %NN - 
						// unfortunately, this is not simple possible using urlencode() as this would encode too much.  
						// However, for the moment, we rely on the webmaster to set up the paths properly, and, unicode in the regular expressions seem to to hurt.
					}
					break;
			}
		}
		
		// combine all rules to one regex pre user-agent
		foreach( $collect as $curr_user_agent=>$rules ) {
			if( sizeof($rules['disallow']) ) {
				$this->rules['user-agent'][$curr_user_agent]['disallow'] = '#(' . implode('|', array_keys($rules['disallow'])) . ')#';
			}
			
			if( sizeof($rules['allow']) ) {
				$this->rules['user-agent'][$curr_user_agent]['allow'] = '#(' . implode('|', array_keys($rules['allow'])) . ')#';
			}
		}

		// success
		return true;
	}
	
	
	/***********************************************************************//**
	setFromSerialized() imports the rules, getSerialized() exports then.
	***************************************************************************/
	function setFromSerialized($rules)
	{
		$this->rules = unserialize($rules);
	}
	function getSerialized()
	{
		return serialize($this->rules);
	}

	
	/***********************************************************************//**
	Set the user agent, the rules should be taken from.
	For "any" user agent, use "*"
	***************************************************************************/
	function setUserAgent($userAgent)
	{
		$this->userAgent = $userAgent;
	}

	
	/***********************************************************************//**
	urlAllowed() checks if the given URL is allowed by the loaded rules (it does
	not check the domain of $url - use Crawler_RobotsTxtDb::urlAllowed() for 
	this purpose)
	***************************************************************************/
	function urlAllowed(G_Url $urlObj)
	{
		assert( $this->userAgent!='' );
		
		$urlPath = $urlObj->getPathNParam();
		
		// check explicit user agent, if any
		if( $this->userAgent != '*' )
		{
			switch( $this->urlAllowedByUA($urlPath, $this->userAgent) )
			{
				case Crawler_RobotsTxtFile::DISALLOW:	return false;
				case Crawler_RobotsTxtFile::ALLOW: 		return true;
				// in case of UA_NOT_FOUND, just continue
			}
		}
		
		// check generic user agent
		switch( $this->urlAllowedByUA($urlPath, '*') )
		{
			case Crawler_RobotsTxtFile::DISALLOW:	return false;
			default: /* ALLOWED/UA_NOT_FOUND */		return true;
		}
	}
	
	const ALLOW = 1;		// return value form urlAllowedByUA(): UA found and URL allowed
	const DISALLOW = 2;	// return value from urlAllowedByUA(): UA found but URL disallowed
	const UA_NOT_FOUND = 3;	// return value from urlAllowedByUA(): UA not found, URL allowed/disallowed cannot be decided here
	private function urlAllowedByUA($urlPath, $userAgent)
	{
		// we match the user agent case-insensitive
		$userAgent = strtolower($userAgent);
		
		// correct the $userAgent, if needed
		if( !is_array($this->rules['user-agent'][$userAgent]) ) {
			return Crawler_RobotsTxtFile::UA_NOT_FOUND;
		}
		
		// check the rules
		if( $this->rules['user-agent'][$userAgent]['disallow'] == '' )
		{
			// no disallow rules at all - by default, everything is allowed
			return Crawler_RobotsTxtFile::ALLOW; 
		}
		
		if( @preg_match($this->rules['user-agent'][$userAgent]['disallow'], $urlPath) ) 
		{
			// a disallow rule matches ...
			if( $this->rules['user-agent'][$userAgent]['allow'] != '' )
			{
				// ... check if there is an allow rule that overwrites the disallow rule
				if( @preg_match($this->rules['user-agent'][$userAgent]['allow'], $urlPath) )
				{ 
					return Crawler_RobotsTxtFile::ALLOW;					
				}
			}
			
			return Crawler_RobotsTxtFile::DISALLOW; // disallow matches, no allow rules 
		}
		
		// no disallow rule matches
		return Crawler_RobotsTxtFile::ALLOW; 
	}
};

