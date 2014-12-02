<?php 
/*******************************************************************************
Go Search Engine - Parse a HTML document
****************************************************************************//**

The class Crawler_ParseHtml is used to parse an HTML document. 
Used by Crawler_Base.

Usage:

	$ob = new Crawler_ParseHtml();
	$ob->parseDocument($crawler, &$pi);
 
with $pi:

- in: `$pi['header']`			- header

- in: `$pi['content']`			- content, raw, not yet recoded to UTF-8

- in/out: `$pi['charset']`		- character set assumed by the header, may be 
								  modified by parseDocument()
								  
- in/out: `$pi['lang']`			- language set assumed by the header, may be 
								  modified by parseDocument()

- out: `$pi['refresh']`			- if set the caller should redirect to this url

- out: `$pi['links']`			- an array of links 

- out: `$pi['discarded_links']`	- an array of discarded links

- out: `$pi['nofollow']`		- set if nofollow is enabled, links are empty 
								  when set

- out: `$pi['noindex']`			- set if noindex is enabled, links may be set

- out: `$pi['err']`				- set on errors

- out: `$pi['body']`			- the raw text, encoded as UTF-8, not set if 
								  noindex is enabled

Supported standards
-------------------

- `<meta name="robots" ... />` - We respect `noindex`, `nofollow` and 
  `noarchive`.  `nosnippet` is not supported as we think snippets really make 
  sense; so if we found this attribute on a page, the page is simply not 
  indexed - and, of course, no nippet will be shown.

- `<link rel="canonical" ... />`

- `<base href="..." />`

No a real HTML-standard, but supported anyway:

- `robots.txt` with the commands `User-agent:`,`Disallow:` and `Allow` and with
  the support of `*` and `$`. 


@author Björn Petersen

*******************************************************************************/ 

class Crawler_ParseHtml
{
	const MAX_CRAWL_URL_LEN = 128;

	private $crawler;
	
	function __construct()
	{
		$this->clean_href = array('"'=>'', "'"=>'');
		
		// ENT_HTML401 is only defined in PHP 5.4.0 and we support PHP >= 5.1.2
		if( !defined('ENT_COMPAT') ) { die('WTF?'); } // make sure, the ENT_* constants can be checked using the defined()-function
		if( !defined('ENT_HTML401') ) { define('ENT_HTML401', 0); }
	}
	
	private function parseMeta($matches)
	{
		$tag = strtolower($matches[1]);
		$param = array(); // note, that there is a rough filter at [*]
		if( preg_match_all('/([a-z\-]+)\s*=\s*("[^"]+"|[^\s]+)/si', $matches[2], $temp) ) {
			for( $i = 0; $i < sizeof($temp[1]); $i++ ) {
				$param[ strtolower($temp[1][$i]) ] = str_replace('"', '', $temp[2][$i]);;
			}
		}
		
		if( $tag == 'meta' )
		{
			if( strtolower($param['http-equiv'])=='refresh' ) 
			{
				// <meta http-equiv="refresh" content="<timeout>; <url>" />
				$content = html_entity_decode($param['content'], ENT_COMPAT | ENT_HTML401, 'UTF-8');
				G_Tools::splitContentType($content, $seconds, $param);
				if( $param['url']!='' && $seconds < 120 ) { // for longer refreshes, we assume a simple reload of the same page 
					$this->metarefreshStr = trim($param['url'], "' "); // get rid of single quotes around the URL (double quotes are already handled)
				}
			}
			else if( strtolower($param['http-equiv'])=='content-type' )
			{
				// <meta http-equiv="content-type" content="<mime>; charset=<charset>" />
				G_Tools::splitContentType($param['content'], $ct, $ctparam);
				if( $ctparam['charset']!='' ) {
					$this->pi['charset'] = $ctparam['charset'];
				}
			}
			else if( strtolower($param['http-equiv'])=='content-language' )
			{
				// <meta http-equiv="content-language" content="<language>" />
				$temp = $param['content'];
				if( $temp ) {
					$this->pi['lang'] = $param['content'];
				}
			}
			else if( strtolower($param['name'])=='language' ) 
			{
				// <meta name="language=" content="<language>" />
				// (this is not in any standard, however, it is used frequently)
				$temp = $param['content'];
				if( $temp ) {
					$this->pi['lang'] = $param['content'];
				}
			}
			else if( $param['charset']!='' )
			{
				// <meta charset="<charset>" />
				$this->pi['charset'] = $param['charset'];
			}
			else if( strtolower($param['name'])=='robots' )
			{
				// <meta name="robots" content="[noindex],[nofollow],[nosnippet],[noarchive]" />
				// - we treat `nosnippet` equal to `noindex` - if a snippet is not desired, the page does not make much sense for us)
				// - `noarchive` is implicitly supported as we do not use a full-html cache at all and so we cannot display a "show-in-cache"-link
				$robots_settings = strtolower($param['content']);
				if( strpos($robots_settings, 'noindex') !== false || strpos($robots_settings, 'nosnippet') !== false ) { 
					$this->pi['noindex'] = true;
				}

				if( strpos($robots_settings, 'nofollow') !== false ) { 
					$this->pi['nofollow'] = true; 
				}
				
				if( $this->pi['noindex'] && $this->pi['nofollow'] ) { 
					$this->pi['err'] = 'err_norobots'; 
					$this->pi['err_obj'] = 'meta'; 	
				}
			}
		}
		else if( $tag == 'html' )
		{
			// <html lang="...">
			$temp = trim($param['lang']);
			if( $temp ) {
				$this->pi['lang'] = $param['lang'];
			}
		}
		else if( $tag == 'link' )
		{		
			if( $param['rel']=='canonical' )
			{
				// <link rel="canonical" href="..." />
				$this->canonicalStr = trim(html_entity_decode($param['href'], ENT_COMPAT | ENT_HTML401, 'UTF-8'));
			}
		}
		else if( $tag == 'base' )
		{
			// <base href="..." />
			$this->linkbaseStr = trim(html_entity_decode($param['href'], ENT_COMPAT | ENT_HTML401, 'UTF-8'));
		}

		return '';
	}

	private function parseAhref($matches)
	{
		if( !$this->pi['nofollow'] )
		{
			// get the href
			$href  = strtr($matches[2], $this->clean_href);	// the match is not empty and does not contain spaces, however, may have surrounding quotes
			if( $href != '' ) 
			{
				// convert entities to UTF-8 - this should be done before checking any other character of the string
				$href = html_entity_decode($href, ENT_COMPAT | ENT_HTML401, 'UTF-8'); // giving UTF-8 is needed as before 5.4.0 the default encoding was ISO-8859-1 and we support PHP >= 5.1.2
				$discard_link = '';
				
				if( ($p=strpos($href, '#'))!==false ) {
					if( $p == 0 ) {
						$discard_link = 'reason_hashonly';
					}
					else {
						$href = substr($href, 0, $p); // remove #fragment
					}
				}
				
				// rough extension check: sorting things out here will speed up crawling (we save one request to get the real mime-type)
				// (not sure with this: currently, extensions as "a.gif?foo=bar" are allowed: they may return content that is not obvious - 
				// however, they seem to be rare and if this becomes a problem, we could change this behaviour)
				if( ($p=strrpos($href, '.'))!==false ) { 
					$ext = strtolower(substr($href, $p+1));
					if( $this->crawler->disallowed_ext[$ext] ) {
						$discard_link = 'reason_ext';
					}
				}
				
				// protocol check: we can handle only some protocols
				if( ($p=strpos($href, ':'))!==false ) {
					$prot = strtolower(substr($href, 0, $p));
					if( !$this->crawler->allowed_prot[$prot] ) {
						$discard_link = 'reason_prot';
					}
				}
				
				// length check; in common, we allow any URL length, however, on 
				// crawling, we discard some long URLs as they often do not provide wanted content.
				// We may raise the border, however, a maximum length should _always_ be provided.
				if( strlen($href) > Crawler_ParseHtml::MAX_CRAWL_URL_LEN ) {
					$discard_link = 'reason_urltoolong';
				}
				
				// language check: if the anchor has a non-matching language attribute, discard the link
				if( !$discard_link && strpos($matches[1], 'lang')!==false ) { // roght check for performance reasons
					$linklang = '';
					if( preg_match('/\shreflang\s*=\s*"?([^\s>"]*)/i', $matches[1], $temp) ) {
						$linklang = $temp[1];
					}
					else if( preg_match('/\slang\s*=\s*"?([^\s>"]*)/i', $matches[1], $temp) ) {
						$linklang = $temp[1];
					}
					if( $linklang ) {
						if( !$this->crawler->lang_obj->isMatchingLang($linklang) ) {
							$discard_link = 'reason_lang ('.$linklang.')';
						}
					}
				}
				
				// convert the link into an absolute URL, if not yet done for the given href
				if( !$discard_link && !$this->rel_links[$href] ) {
					$this->rel_links[$href] = 1; // avoid doing the stuff below two times for the same URL ...
					$abs_link = clone $this->linkbaseObj;
					$abs_link->change($href);
					if( $abs_link->error ) { 
						$discard_link = 'reason_err ('.$abs_link->error.')';
					}
					else {
						if( 0||$this->crawler->robotstxtObj->urlAllowed($abs_link, 0 /*do not load, fast memory check only*/) ) {
							$this->pi['links'][] = $abs_link;								// ^^^ - else it takes too much time without larget benefit (eg. on wikipedia/Albert_Einstein about 1500 ms - without link checking 100ms) (time taken by regex, not i/o)
						}																	//     - even loading from DB may result in > 1000 requests with only a few hits
						else {																//     - for internal links, the corresponding robots.txt is normally already loaded
							$discard_link = 'err_norobots (robots.txt)';
						}
					}
				}
				
				// add the link
				if( $discard_link ) {
					$this->pi['discarded_links'][$href] = $discard_link;
				}
			}
		}
		
		return '['.$matches[3] .']'; // later on, the text may be discarded (eg. if a single image is linked); we get rid of the links then
	}
	
	private function parseTitle($matches)
	{
		if( $this->pi['title'] == '' ) { // if a document has multiple <title> tags (not allowed, but existant), we'll use the first non-empty
			$this->pi['title'] = $matches[1];
			$this->pi['title'] = strip_tags($this->pi['title']);
			$this->pi['title'] = html_entity_decode($this->pi['title'], ENT_COMPAT | ENT_HTML401, 'UTF-8');
			$this->pi['title'] = G_String::clean($this->pi['title']);
			$this->pi['title'] = G_String::maxWords($this->pi['title'], 16);
		}
	}
	
	function discardText($matches)
	{
		$this->pi['discarded_text'] .= $matches[0];
		return '';
	}
	
	function parseDocument(Crawler_Base $crawler, &$pi)
	{
		// create and save some objects
		$this->crawler = $crawler;
		$this->pi =& $pi;
		
		$text = $pi['content'];	
		
		// remove comments
		$text = preg_replace('/<!--.*?-->/s', '', $text);
		
		// strip styles and scripts - we really want to get rid of this; so we allow some syntax faults as < / SCRIPT >
		// (we have to do this first, as eg. other elements checked below may be contained as text in a comment or script)
		// (for <noframes> and for <noscript> we hope for content or at least for a link; however, if this makes problems we
		// can remove it _after_ the refresh section below (sth. like `<noscript> refresh .. </noscript>` should always work))
		// (CAVE: do not strip <form> tags this way - it is very common that the whole site is a formular ...)
		$text = preg_replace('/<\s*(script|style)[^>]*>.*?<\s*\/\s*(\1)[^>]*>/is', '', $text); // single <script /> and <style /> a removed by strip_tags() below

		// match languages:
		// - match <html lang="..">										- this is the normal settings
		// - match <meta http-equiv="content-language" content="..">	-  not recommended, HTTP header should be used instead
		// - match <meta name="langauge" content=".."					- not in any standard, however, widely used (I used it, too :-| eg. on silverjuke.net)
		// other stuff to match:
		// - match <meta http-equiv="content-type" content="text/html; charset=..." />
		// - match <meta http-equiv="refresh" content="0; URL=..." />
		// - match <meta charset="..." />
		// - match <meta name="robots" content="..." />
		// - match <base href="abs.url" />
		// - match <link rel="canonical" href="..." />				vvv [*] for speed reasons, a rough check here (parseMeta() does another regex)
		$this->linkbaseStr = '';
		$this->canonicalStr = '';
		$this->metarefreshStr = '';
		$text = preg_replace_callback('/<\s*(html|link|meta|base)([^>]*(charset|refresh|robots|canonical|href|lang)[^>]*)>/is', array($this, 'parseMeta'), $text);
		if( $pi['err'] ) {
			return; // error
		}
		
		// create the link base
		$this->linkbaseObj = clone $pi['url'];
		if( $this->linkbaseStr != '' ) {
			$this->linkbaseObj->change($this->linkbaseStr); // base should be absolute, however, here the there is written, relative URLs works as well.
		}
		
		// check for refreshes got by parsing the meta-data
		if( $this->canonicalStr != '' ) {
			$new_url = $this->linkbaseObj; // not sure, if we should use the base or the real url here ...
			$new_url->change($this->canonicalStr);
			if( $pi['url']->getAbs() != $new_url->getAbs() ) {
				$pi['refresh'] = $new_url->getAbs(); return; // if the canonical URL differs from the current URL, we do a redirect
			}
		}
		else if( $this->metarefreshStr != '' ) {
			$new_url = $this->linkbaseObj; // not sure, if we should use the base or the real url here ...
			$new_url->change($this->metarefreshStr);
			if( $pi['url']->getAbs() != $new_url->getAbs() ) {
				$pi['refresh'] = $new_url->getAbs(); return; // meta-refresh only if we're not canonical
			}
		}
				

		// convert text to UTF-8
		if( strtoupper($pi['charset']) != 'UTF-8' ) {
			if( !G_ENCODING::toUtf8($pi['charset'], $text) ) {
				$pi['err'] = G_ENCODING::getLastError(); return; // error
			}
		}
		
		// now, as we have UTF-8, get the title of the document
		$text = preg_replace_callback('/<\s*title[^>]*>(.*?)<\s*\/\s*title\s*>/is', array($this, 'parseTitle'), $text);
		if( $this->pi['title'] == '' ) {
			$pi['err'] = 'err_notitle'; return; // error - maybe we should take the title from somewhere else, hoever, an HTML-document without an title seems unqualified to be (bp)
		}

		// Create links to use
		// --------------------------------------------------------------------
		
		// match links:
		// we surround the link text by { and } to allow removing links from the fulltext in the next step (to get rid of menus, footers etc.)
		// (to the list of links to follow, these links are addes anyway)
		$this->rel_links = array();
		$this->pi['discarded_links'] = array();
		$pi['links'] = array();
		$text = strtr($text, '[]', '()');
		$text = preg_replace_callback('/<\s*a(\s+[^>]*href\s*=\s*([^\s>]+)[^>]*)>(.+?)<\s*\/\s*a\s*>/is', array($this, 'parseAhref'), $text);
		
		// Create text to use
		// --------------------------------------------------------------------
		
		if( !$pi['noindex'] ) 
		{
			// remove all remaining tags and entities
			$text = preg_replace('/<(li|p|div|blockquote|pre|br|td)/is', ' <\1', $text); // insert spaces before tags that are blocks by default (avoid <li>home</li><li>search</li> becoming homesearch, however, for ref<b>f</b>erer, these spaces are faults!)
			$text = strip_tags($text); // NB: strip_tags() does not allow errors as `<a><img</a>` (note the forgotten `>` in the image tag) - maybe we should implement sth. own here. 
			$text = html_entity_decode($text, ENT_COMPAT | ENT_HTML401, 'UTF-8'); // giving UTF-8 is needed as before 5.4.0 the default encoding was ISO-8859-1 and we support PHP >= 5.1.2
			
			// clean the text
			$text = G_String::clean($text);
	
			// remove links lists as [link] [link] [link] - [link] etc.
			// (currently only spaces are allowed betwen the links, maybe we should allow other delimiters, too)
			$pi['discarded_text']	= '';
			$text = preg_replace_callback('/(\[[^\[]*\]\s*){5,100}/s', array($this, 'discardText'), $text); // {5,100} - include an upper limit - otherwise, the pcre.backtrack_limit (?) limit will be reached and the script is stopped by a server error (!) - and/or the wamp-server crashes
			$text = strtr($text, '[]', '  ');
			
			// done
			$pi['body'] = $text;
		}
	}
};
