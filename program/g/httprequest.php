<?php
/*******************************************************************************
Go Search Engine - HTTP stuff
****************************************************************************//**

G_HttpRequeset can be used to send a single HTTP-Request

Usage:

	$ob = new G_HttpRequest();
	$ob->setUrl('http://domain.de/path');
	$pi = $ob->sendRequest();
 
$pi is now an array:

- url			- url used for request

- request_raw	- simple string with the header/post data used for request

- truncated		- only set, if maxBytes were exceeded

- err			- set on any error, esp. on timeout errors

- header		- the response header as an associative array:
 					- protocol	=>	HTTP/1.1
 					- code		=>  200
 					- ...
					
- content		- the content, already unzipped/unchunked,
				  ready to use

- ... more fields may be added by the caller as needed.
 
Error handling:

Errors starting with err_http* are - more or less - connection errors.
The caller may decide to try the request later again.

NB: Other methods for a http-request:	

- fsockopen()		- seems to be available most times
- http_get() 		- not always available, not on WAMP
- new HttpRequest()	- not always available, not on WAMP
- fopen()			- always available; unfortunately, for security reasons,
					  opening http-files is disabled most times
Some test pages

- http://www.microsoft.com/de-de/default.aspx - sends chunked+deflate content

Resources:
- http://www.jmarshall.com/easy/http/#postmethod
 
@author Björn Petersen

*******************************************************************************/

class G_HttpRequest
{
	const OK_200					= 200;
	const MULTIPLE_CHOICES_300		= 300; // destination _may_ be in location-field, original url can be delete
	const MOVED_PERMANENTLY_301		= 301;
	const FOUND_302					= 302; // destination in location field, often misused, so one should delete the original url
	const SEE_OTHER_303				= 303;
	const TEMPORARY_REDIRECT_307	= 307;
	const PERMANENT_REDIRECT_308	= 308;
	const NOT_FOUND_404				= 404;
	
	static private $s_host2ip;		// needed for getIp()
	static private $s_icWorking;	// internet connection working

	private $url 			= '';
	private $postData;
	private $userAgent 		= '';
	private $lang 			= '';
	private $timeoutSeconds = 30;
	private $sendOnly		= false;
	private $maxBytes;

	function __construct()
	{
		$this->postData = array();
		$this->maxBytes	= 2*1024*1024; // 2 MB (cmp. Wikipedia articles: Albert-Einstein 200K, Vereinigte Staaten 500K)
	}

	
	/***********************************************************************//**
	Set the URL to use on sendRequest() calls (the set URL ist _not_ cleared 
	after sendRequest() ist called)
	***************************************************************************/
	function setUrl(G_Url $url)
	{
		$this->url = clone $url;
	}
	
	
	/***********************************************************************//**
	Add post-data to use on sendRequest() calls (the post-data are _not_  
	cleared after sendRequest() ist called; to clear them, clearPostData())
	***************************************************************************/
	function addPostData($key, $value)
	{
		$this->postData[$key] = $value;
	}
	
	function clearPostData()
	{
		$this->postData = array();
	}

	
	/***********************************************************************//**
	Set the user-agent to use on sendRequest() calls (the set user-agent ist   
	_not_ cleared after sendRequest() ist called)
	***************************************************************************/	
	function setUserAgent($userAgent)
	{
		$this->userAgent = $userAgent;
	}

	
	/***********************************************************************//**
	Set the language to use on sendRequest() calls (the set language ist _not_  
	cleared after sendRequest() ist called)
	***************************************************************************/	
	function setLanguage($lang)
	{
		$this->lang = $lang;
	}

	
	/***********************************************************************//**
	Set the timeout to use on sendRequest() calls.
	Defaults to 30 seconds.
	***************************************************************************/
	function setTimeout($seconds)
	{
		$this->timeoutSeconds = $seconds;
	}
	
	
	/***********************************************************************//**
	Set the send-only-mode to use on sendRequest() calls.
	If send-only is enabled, sendRequest() does not wait for any data but 
	returns immediately after sending a request. This may be useful for 
	asynchronous communication.
	***************************************************************************/	
	function setSendOnly($sendOnly)
	{
		$this->sendOnly = $sendOnly;
	}

	
	/***********************************************************************//**
	Send the request
	***************************************************************************/
	function sendRequest()
	{
		assert( $this->userAgent!='' );
		assert( is_object($this->url) );
		
		// prepare page information object
		$pi = array('url'=>$this->url);
		
		// check url
		if( $this->url->error ) {
			$pi['err'] = $this->url->error; return $pi; // error
		}
		
		if( $this->url->scheme == 'http' ) 
		{
			if( ($fsock_arg=G_HttpRequest::getIp($this->url->host)) === false ) {
				$pi['err'] = 'err_httpip'; return $pi; // error - err_http* errors get a special handling
			}
	}
		else if( $this->url->scheme == 'https' ) 
		{
			if( !G_HttpRequest::httpsAvailable() ) {
				$pi['err'] = 'err_opensslnotavail'; return $pi; // error
			}
			$fsock_arg = 'ssl://' . $this->url->host;
		}
		else 
		{
			$pi['err'] = 'err_badprotocol'; return $pi; // error
		}
		
		if( $this->url->user!='' ) {
			$pi['err'] = 'err_urlauthnotsupported'; return $pi; // error
		}
		

		// build request header
		$requestType = sizeof($this->postData)? 'POST' : 'GET';
		
		$pi['request_raw'] =
				$requestType . ' ' . $this->url->getPathNParam() . " HTTP/1.0\r\n" // HTTP/1.0=no chunks, HTTP/1.1=chunks possible
			.	"Host: {$this->url->host}\r\n"
			.	"User-Agent: {$this->userAgent}\r\n";
		if( $this->lang != '' ) {
			$pi['request_raw'] .= "Accept-Language: {$this->lang}\r\n";
		}
		if( ($enc=G_HttpRequest::getAcceptableContentEncodings()) != '' ) {
			$pi['request_raw'] .= "Accept-Encoding: $enc\r\n";
		}
		$pi['request_raw'] .= "Connection: close\r\n";
		
		if( $requestType == 'POST' ) 
		{
			$postStr = '';
			foreach( $this->postData as $key=>$value ) {
				$postStr .= ($postStr==''? '' : '&') . $key . '=' . urlencode($value);
			}
			$pi['request_raw'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$pi['request_raw'] .= "Content-Length: ".strlen($postStr)."\r\n";
			$pi['request_raw'] .= "\r\n"; /*terminate the header by a blank line*/
			$pi['request_raw'] .= $postStr;
		}	
		else
		{
			$pi['request_raw'] .= "\r\n"; /*terminate the header by a blank line*/
		}

		
			// - - - - - - - - - - - SOCKET STUFF - - - - - - - - - - - //
				// open socket, fsockopen() defaults to blocking mode; set a timeout; send request header
				if( ($handle=@fsockopen($fsock_arg, $this->url->port, $errno, $errstr, $this->timeoutSeconds) ) === false ) {
					if( $errno == 10060 ) $errstr = sprintf('timeout, %d s', $this->timeoutSeconds);
					$pi['err'] = sprintf('err_httpsockopen (%d, %s)', $errno, trim($errstr)); return $pi; // error - err_http* errors get a special handling
				}
				@stream_set_timeout($handle, $this->timeoutSeconds);
				if( @fwrite($handle, $pi['request_raw']) === false ) {
					@fclose($handle); $pi['err'] = 'err_httpsockwrite'; return $pi; // error - err_http* errors get a special handling
				}
				
				// send only? if so, close the socket and we're done.
				if( $this->sendOnly ) {
					@fclose($handle);
					$pi['send_only'] = 1;
					return $pi;
				}
				
				// read data
				$all_data = '';
				while( !feof($handle)  )
				{
					if( ($curr=@fread($handle, 8192)) === false ) {
						@fclose($handle); $pi['err'] = 'err_httpsockread'; return $pi; // error - err_http* errors get a special handling
					}
					$all_data .= $curr;
					
					$meta = @stream_get_meta_data($handle);
					if( !is_array($meta) || $meta['timed_out'] ) {
						@fclose($handle); $pi['err'] = sprintf('err_httpsockread (timeout, %s s)', $this->timeoutSeconds); return $pi; // error - err_http* errors get a special handling
					}
					
					if( strlen($all_data) > $this->maxBytes ) {
						$pi['truncated'] = 1;
						break;
					}
				}
				
				// close socket
				@fclose($handle);
			// - - - - - - - - - - - /SOCKET STUFF - - - - - - - - - - - //

		// divide data into raw header lines and raw content
		if( ($p = strpos($all_data, "\r\n\r\n")) !== false ) {
			$lineend = "\r\n"; // windows, unix
		}
		else if( ($p = strpos($all_data, "\n\n")) !== false ) {
			$lineend = "\n"; // apple gives us these headers ...
		}
		else {
			$pi['err'] = 'err_header'; return $pi; // error
		}
		$pi['header_raw']	= substr($all_data, 0, $p);
		$content     		= substr($all_data, $p+strlen($lineend)*2);

		// create header as an associative array as key => value; from the first line, "HTTP/1.1 200 OK" we generate the keys "protocol" and "code"
		$pi['header'] = array();
		$lines = explode($lineend, $pi['header_raw']);
		for( $l = 0; $l < sizeof($lines); $l++ ) {
			$currline = $lines[$l];
			if( $l==0 ) {
				while( strpos($currline, '  ')!==false ) { $currline = str_replace('  ', ' ', $currline); }
				$currline = explode(' ', trim($currline));
				$pi['header'][ 'protocol' ] = $currline[0];
				$pi['header'][ 'code' ] = intval($currline[1]);
			}
			else if( ($p=strpos($currline, ":")) !== false ) {
				$pi['header'][ strtolower(trim(substr($currline, 0, $p))) ] = trim(substr($currline, $p+1));
			}			//		^^^ lower keys - the program relies on this!			^^^ original case for the values
		}
		
		// unchunking the body is not supported and should not be needed as we request HTTP/1.0 and not  HTTP/1.1
		// (if we implement this: what should be done first, unchunk or unzip/inflate?)
		$transfer_encoding = strtolower($pi['header']['transfer-encoding']);
		if( $transfer_encoding!='' )
		{
			$pi['err'] = sprintf('err_badtransferencoding (%s)', $transfer_encoding); return $pi; // error
		}
		
		// unzip/inflate the body
		$content_encoding = strtolower($pi['header']['content-encoding']);
		if( $content_encoding == 'deflate' )
		{
			$pi['content_bytes_compressed'] = strlen($content);
			
			if( !function_exists('gzinflate') ) {
				$pi['err'] = 'err_decompress (deflate, func missing)'; return $pi; // error
			}
			
			if( ($content=@gzinflate($content)) === false ) { 
				$pi['err'] = 'err_decompress (deflate, bad data)'; return $pi; // error
			}
		} 
		else if( $content_encoding == 'gzip' ) 
		{
			$pi['content_bytes_compressed'] = strlen($content);
			
			if( !function_exists('gzdecode') ) {
				$pi['err'] = 'err_decompress (gzip, func missing)'; return $pi; // error
			}
			
			if( ($content=@gzdecode($content)) === false ) {
				$pi['err'] = 'err_decompress (gzip, bad data)'; return $pi; // error
			}
		} 
		else if( $content_encoding != '' )
		{
			$pi['err'] = sprintf('err_badcontentencoding (%s)', $content_encoding); return $pi; // error
		}
		
		// success
		$pi['content_bytes'] = strlen($content);
		if( $pi['content_bytes'] > 0 ) {
			$pi['content'] = $content;
		}
		return $pi;
	}

	
	/***********************************************************************//**
	The function returns the acceptable content encodings in a comma-separated 
	list without spaces;  the return value may be directly used in the
	HTTP-header as `Accept-Encoding: ...`
	***************************************************************************/
	static function getAcceptableContentEncodings()
	{
		$ret = '';
		
		if( function_exists('gzdecode')  ) { 
			$ret .= ($ret? ',' : '') . 'gzip'; 
		} 
		
		// "deflate" makes problems eg. on http://t.co/I7GREI7bRT - however, the implementation seems to be okay, eg. on microsoft.de it is just fine ...
		// So, for the moment (03/2014) we just disable this encoding; most servers also support gzip
		//if( function_exists('gzinflate') ) { 
		//	$ret .= ($ret? ',' : '') . 'deflate'; 
		//} 
		
		return $ret;
	}
	
	
	/***********************************************************************//**
	Function checks if "https" is available; if needed and not yet done,
	it loads the required libraries for this purpose.
	***************************************************************************/
	static function httpsAvailable()
	{	
		// test for openssl
		if( !@extension_loaded('openssl') ) 
		{
			// library not loaded - try to load it now (dl() is disabled in many configurations, so sometime, we can get rid of this loading method)
			if( @function_exists('dl') ) {
				$prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
				@dl($prefix . 'openssl.' . PHP_SHLIB_SUFFIX);
			}

			// test again
			if( !@extension_loaded('openssl') ) {
				return false;
			}
		}
		
		// fine - https is supported!
		return true;
	}

	
	/***********************************************************************//**
	checks for an IP-Adress
	***************************************************************************/
	static function getIp($hostname)
	{
		if( !is_array(G_HttpRequest::$s_host2ip) ) { G_HttpRequest::$s_host2ip = array(); }
		if( !isset(G_HttpRequest::$s_host2ip[$hostname]) ) {
			G_HttpRequest::$s_host2ip[$hostname] = gethostbyname($hostname); // TODO: maybe we should cache them in crawler.sqlite
		}
		
		$ret = G_HttpRequest::$s_host2ip[$hostname];
		
		// gethostbyname() returns the unchanged string on errors, so we check the IP-Adresse
		// to contain only the characters 0-9a-f.:/ - this will match more than IP-Adresses, and also some hostnames, however, for most reasons this should be fine
		if( !preg_match('/^[0-9\.:\/a-f]+/i', $ret) ) {
			return false;
		}
			
		return $ret;
	}
	
	
	/***********************************************************************//**
	check if the internet connection itself is okay - otherwise we would eg. 
	delete our complete database as we get errors for every page!
	***************************************************************************/
	static function isInternetConnectionWorking()
	{	
		if( !isset(G_HttpRequest::$s_icWorking) )
		{
			G_HttpRequest::$s_icWorking = false;
			$urls_to_try = array('http://www.google.com', 'http://www.microsoft.com', 'http://www.apple.com', 'http://www.amazon.com');
			shuffle($urls_to_try);
			foreach( $urls_to_try as $url ) {
				$test = new G_HttpRequest();
				$test->setUserAgent(G_USER_AGENT);
				$test->setUrl(new G_Url($url));
				$pi = $test->sendRequest();
				if( substr($pi['err'], 0, 8) != 'err_http' ) {
					G_HttpRequest::$s_icWorking = true; // is up and working
				}
			}
		}
		
		return G_HttpRequest::$s_icWorking;
	}
	
};
