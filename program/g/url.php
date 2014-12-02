<?php 
/*******************************************************************************
Go Search Engine - URL functions
****************************************************************************//**

A class for handling an URL; the URL class can handle any type of URL - if
some types should not be possible, this should be done by the caller.

@author Björn Petersen	

*******************************************************************************/

class G_Url
{		
	public $error;
	public $scheme;
	public $user;
	public $pass;
	public $host;
	public $port;
	public $path;
	public $param;
	
	function __construct($url = '')
	{
		if( $url == '' ) {
			$this->clear();
		}
		else {
			$this->setAbs($url);
		}
	}
	
	
	/***********************************************************************//**
	Clear all internal values, also used by the constructor.
	***************************************************************************/
	public function clear()
	{
		$this->error 			= 'err_unset';
		$this->scheme			= '';
		$this->user				= '';
		$this->pass				= '';
		$this->host				= '';
		$this->port				= '';
		$this->path				= '';
		$this->param			= '';
	}
	
	
	/***********************************************************************//** 
	Function takes a full-qualified URL as 
	`http://user:pw@www.host.com:port/path/?param#fragment`
	
	Protocol and port are optional; if left out, `http` is assumed as the 
	protocol and 80 or 443 are assumed as the ports.
	***************************************************************************/
	public function setAbs($url)
	{
		$this->clear();

		if( !preg_match("#^[a-z]+://#i", $url) ) {
			$url = 'http://' . $url;
		}
		
		if( !$this->checkUrlCharacters($url) ) {
			$this->error = 'err_badurlchars'; 
			return false; // error
		}
		
		if( ($parts = @parse_url($url)) === false ) {
			$this->error = 'err_badurl'; 
			return false; // error
		}
		
		if( $parts['host'] == '' ) {
			$this->error = 'err_badhost'; 
			return false; // error
		}
		
		if( $parts['path'] == '' ) {
			$parts['path'] = '/'; // empty path is equal to the abs. path / by definition
		}
		
		if( !$parts['port'] ) {
			$parts['port'] = $this->getDefaultPort($parts['scheme']);
			if( !$parts['port'] ) {
				$this->error = 'err_badport';	return false;
			}
		}
		
		// success
		$this->error	= '';
		$this->user		= $parts['user'];
		$this->pass		= $parts['pass'];
		$this->scheme	= $parts['scheme'];
		$this->host		= $parts['host'];
		$this->port		= $parts['port'];
		$this->path		= $parts['path'];
		$this->param	= $parts['query'];
		return true;
	}
	
	function setParam($param)
	{
		assert( strpos($param, '?') === false );
		$this->param = $param;
	}
	
	
	/***********************************************************************//**
	Return the fully-qualified, absolute URL.  
	Ports are only added if non-standard, the scheme is always added.
	***************************************************************************/
	function getAbs()
	{
		if( $this->error ) {
			return 'http://error/'.urlencode($this->error);
		}

		$ret = $this->scheme . '://';
		if( $this->user!='' ) {
			$ret .= $this->user;
			if( $this->pass != '' ) {
				$ret .= ':' . $this->pass;
			}
			$ret .= '@';
		}
		$ret .= $this->host;
		if( $this->getDefaultPort($this->scheme) != $this->port ) {
			$ret .= ':' . $this->port;
		}
		$ret .= $this->getPathNParam();
		return $ret;
	}
	
	
	/***********************************************************************//** 
	Function returns the absolute path (including the starting slash) and
	and parameters.
	***************************************************************************/
	function getPathNParam()
	{
		$ret = $this->path;
		if( $this->param ) {
			$ret .= '?' . $this->param;
		}
		return $ret;
	}
	
	
	/***********************************************************************//** 
	Rough check, if a string consists only out of valid url characters 
	(urlencoded URL by RFC).  Rhis function does not check if the scheme is 
	valid
	***************************************************************************/
	static function checkUrlCharacters($string)
	{ 
		if( !preg_match("/^[a-z0-9\/.&=?%-_.!~*#'()]+$/i", $string) ) 
			return false;
			
		return true;
	}
	
	
	/***********************************************************************//** 
	The url to change to is a fully qualified URL starting with the scheme
	(we assume URL changes as `www.domain.de` to be a simple path!
	
	If the url to changed does not start with a scheme, we assume it to be 
	relative and apply it to the previous settigs.
	***************************************************************************/
	public function change($url_change)
	{
		if( preg_match("#^[a-z]+://#i", $url_change) ) {
			return $this->setAbs($url_change);
		}
		
		// $url_change may also begin with // - this indicated an absolute url, incl. domain, with the same scheme
		if( substr($url_change, 0, 2)=='//' ) {
			return $this->setAbs($this->scheme . ':' .  $url_change);
		}
		
		// remove multiple / from path, 
		while( strpos($url_change, '//') !== false )  { $url_change = str_replace('//', '/', $url_change);  }
		
		// remove fragment from path (we do not use fragments, and this may speed up some tests below)
		if( ($p=strpos($url_change, '#')) !== false ) { 
			$url_change = substr($url_change, 0, $p); 
		}
		
		// remove parameters from path and save them in $this->param
		$this->param = '';
		if( ($p=strpos($this->path, '?'))!==false ) {
			$this->param = substr($this->path, $p+1);
			$this->path = substr($this->path, 0, $p);
		}
		
		if( $url_change == '' )
		{
			// no path to change
			return true;
		}
		else if( substr($url_change, 0, 1) == '/' ) 
		{
			// change the path to another absolute path on the same host
			$this->path = $url_change;
		}
		else
		{
			// remove file from path (if any)
			// ( link.html on domain.com/foo/bar  becomes domain.com/foo/link.html
			//   link.html on domain.com/foo/bar/ becomes domain.com/foo/bar/link.html )
			if( substr($this->path, -1) != '/' ) {
				$this->path = G_Url::dirname($this->path);
			}
			
			$loop_protect = 0;
			while( substr($url_change, 0, 2) == './' 
				|| substr($url_change, 0, 3) == '../' ) 
			{
				if( substr($url_change, 0, 2) == './' ) {
					$url_change = substr($url_change, 2);
				}
				else if( substr($url_change, 0, 3) == '../' ) {
					$url_change = substr($url_change, 3);
					$this->path = G_Url::dirname($this->path);
				}
				$loop_protect++;
				if( $loop_protect > 8 ) {
					$this->error = 'err_toomanyupmarks';
					return false;				
				}
			}
			
			if( substr($this->path, -1) != '/' ) {
				$this->path .= '/';
			}
				
			$this->path .= $url_change;
		}
		
		// success
		return true;
	}


	/***********************************************************************//**
	The function returns the default port for a given scheme (protocol).
	
	The function can also be used to test if a given scheme (protocol) is 
	supported - if not, the function returns 0.
	***************************************************************************/
	private function getDefaultPort($scheme)
	{
		switch( $scheme )
		{
			case 'http':	return 80;
			case 'https':	return 443;
		}
		
		return 0; // error/port not found
	}


	/***********************************************************************//** 
	Same as PHP's dirname() but with exclusive slash usage;
	`/foo/bar`  becomes		 `/foo/` and
	`/foo/bar/` also becomes `/foo/`
	***************************************************************************/
	static function dirname($path)
	{
		if( substr($path, -1) == '/' ) {
			$path = substr($path, 0, -1);
		}
		
		if( ($p = strrpos($path, '/')) !== false ) {
			$path = substr($path, 0, $p);
		}
		
		return $path;
	}
	
	
	/*************************************************************************** 
	Function gets the second level domain and the top level domain from the 
	host (the function strips subdomains).
	If the host has no second level domain or of the host is an IP-Adress, an 
	empty string is returned.
	***************************************************************************/
	/*
	function get2ndLevelDomain()
	{
		$parts = explode('.', $this->host);
		if( sizeof($parts) < 2 ) {
			return ''; // error - the host is eg. "localhost" 
		}
		
		$ret = $parts[sizeof($parts)-2] . '.' . $parts[sizeof($parts)-1];
		if( preg_match('/^[0-9]/', $ret) ) {
			return ''; // the second level domain begins with a number - this is not allowed
		}

		// success
		return $ret;
	}
	*/
	

	/***********************************************************************//**
	Brings the URL in a more readable form, this may result in lack of 
	information!
	
	NB: Trailing slashes are only reoved if placed directly after the domain -
	otherwise, they make a difference - `foo.com/bar/` is a directory while 
	`foo.com/bar` is a file. 
	***************************************************************************/	
	function getHumanReadable($maxchars = 80)
	{
		$human = $this->getAbs();
		
		// remove protocol http:// - leave https:// ftp:// etc.
		if( $this->scheme == 'http' ) { 
			$human = substr($human, 7);
		}
		
		// remove trailing slashes, if placed directly after the domain
		if( $this->path == '' && $this->param == '' ) {
			$human = substr($human, 0, -1);
		}
	
		// shorten URLs that have too many characters
		if( strlen($human)>$maxchars )
		{
			$half = intval($maxchars/2)-1;
			return substr($human, 0, $half) . '..' . substr($human, -$half);
		}
		
		return $human;
	}
};




