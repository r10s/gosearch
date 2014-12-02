<?php
/*******************************************************************************
Go Search Engine - Zeroclick Bang Renderer
****************************************************************************//**

Zeroclick Bang Renderer

This renderer is a little different form the other zeroclick renderers as it 
does not return HTML but simply does a redirect URL.

The {searchTerms} syntax is inspired by http://www.opensearch.org ; see
bangdb.txt for details.

@author Björn Petersen

*******************************************************************************/

class Zeroclick_Bang_Renderer extends Zeroclick_Base
{	
	/***********************************************************************//**
	returns all bangs as 
	`array('!bang1'=>array('lang1'=>url, 'lang2'=>url), ..);`
	***************************************************************************/
	private function loadBangs()
	{
		$db = array();
		$filename = G_DOCUMENT_ROOT . '/program/zeroclick/bang/bangdb.txt';
		$lines = explode("\n", @file_get_contents($filename));
		foreach( $lines as $line ) {
			// there is no explicit comment handling, however, using # at the start of the line or after the URL will work
			if( preg_match('/^\s*(![^\s\[]+)(\[([a-z]+)\])?\s+([^\s]+)/', $line, $matches) ) {
				$db[ $matches[1] ][ $matches[3] ] = $matches[4]; // matching line found
			}
		}
		return $db;
	}
	
	
	function getBangFirstChar($bang)
	{
		$ret = substr($bang, 1, 1);
		if( $ret == '/' ) $ret = 's';
		return $ret;
	}
	

	function renderBangOverview()
	{
		$last = '-';
		$ret = 'Bangs:';
		$db = $this->loadBangs();
		foreach( $db as $bang=>$urls ) {
			$ret .= $this->getBangFirstChar($last)!=$this->getBangFirstChar($bang)? '<br />' : ' ';
			$ret .= $bang;
			$last = $bang;
		}
		return $ret;
	}

	
	private function getMatchingLangUrl($urls)
	{
		if( G_Lang::isValidLang(G_Local::langId(), $lang, $region) ) {
			if( $urls["$lang-$region"] ) return $urls["$lang-$region"];
			if( $urls[$lang] ) return $urls[$lang];
		}
		return $urls[''];
	}
	
	
	function renderContent()
	{		
		// get entered bang (if any)
		if( strpos(strtolower($this->q), '!bang')!==false ) {
			return $this->renderBangOverview();
		}
		else if( $this->q[0] == '!' ) {
			// !bang arg
			if( ($p=strpos($this->q, ' '))===false ) return '';
			$entered_bang = substr($this->q, 0, $p);
			$arg = substr($this->q, $p+1);

		}
		else if( ($p=strpos($this->q, '!'))!==false ) {
			// arg !bang
			$entered_bang = substr($this->q, $p);
			$arg = substr($this->q, 0, $p);
		}
		else {
			// no bang entered
			return '';
		}
		
		$entered_bang = str_replace(' ', '', $entered_bang);
		if( $entered_bang == '!' ) return ''; // empty bang entered
		
		$arg = trim($arg);
		if( $arg == '' ) return ''; // no argument entered
		
		// load out bang database
		$bang_db = $this->loadBangs();
		if( !isset($bang_db[$entered_bang]) ) {
			if( !isset($bang_db['!ddg']) ) return ''; // cannot handle !bang
			
			// route unknown bangs through duckduckgo.com - if we have a more complete list or our own bangs, we may get rid of this
			$arg = $this->q;
			$entered_bang = '!ddg'; 
		}
		
		// possible bangs ...
		$redir_url = $this->getMatchingLangUrl($bang_db[$entered_bang]);
		if( substr($redir_url, 0, 1) == '!' ) $redir_url = $this->getMatchingLangUrl($bang_db[$redir_url]);
		if( $redir_url == '' ) return '';
		
		$redir_url = str_replace('{searchTerms}', urlencode($arg), $redir_url);
		$redir_url = str_replace('{searchTerms-ISO-8859-1}', urlencode(utf8_decode($arg)), $redir_url);

		G_Html::redirect($redir_url);
	}
};
