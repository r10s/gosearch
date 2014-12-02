<?php 
/*******************************************************************************
Go Search Engine - Language stuff
****************************************************************************//**

Handling language lists and guessing languages from text.

@author Björn Petersen

*******************************************************************************/

class G_Lang
{
	static $s_stub;

	function __construct($str = '')
	{
		$this->set($str);
	}
	
	function set($str)
	{
		$ret = true;
		$this->lang_n_regions = array();
		$this->lang_hash = array();
		
		foreach( explode(',', $str) as $lang_n_region ) 
		{
			$lang_n_region = trim($lang_n_region);
			if( $lang_n_region != '' ) 
			{
				if( $this->isValidLang($lang_n_region, $lang, $region) )
				{
					$lang_n_region = $region? "$lang-$region" : $lang;
					if( !in_array($lang_n_region, $this->lang_n_regions) ) {
						$this->lang_n_regions[] = $lang_n_region; // success, lang added
						$this->lang_hash[$lang] = 1;
					}				
				}
				else
				{
					$this->error_lang = $lang_n_region;
					$ret = false; // bad format, continue anyway
				}
			}
		}

		if( sizeof($this->lang_n_regions) == 0 ) {
			$this->lang_n_regions[] = 'en'; // no language set, this is no error
			$this->lang_hash['en'] = 1;
		}
		
		return $ret;
	}
	
	/***********************************************************************//**
	Get languages only
	
	@return string
	***************************************************************************/
	function getLangHash()
	{
		return $this->lang_hash;
	}
	
	/***********************************************************************//**
	Get languages/regions
	
	@return string
	***************************************************************************/
	function getForHumans()
	{
		return implode(', ', $this->lang_n_regions); // space after comma
	}

	/***********************************************************************//**
	Get languages/regions
	
	@return string
	***************************************************************************/
	function getAsAcceptLanguage()
	{
		return implode(',', $this->lang_n_regions); // no space after comma
	}	
	
	/***********************************************************************//**
	Function checks, if the given language string is well formatted as `en-GB;q=0.0`
	and splits it into language and region.
	
	The function also accepts a comma-separated list, and some stupid names as
	"deutsch", however, both are not defined in the HTML-standard - but used
	here and there (isValidLang() normally gets the input directly from an HTML-tag) 
	
	@param		$test		The language string to test
	@param[out]	&$lang		Language extracted from test string
	@param[out]	&$region	Region extracted from test string, may be empty.
	@return					true if the test string is valid, false otherwise
	***************************************************************************/
	static function isValidLang($test, &$lang, &$region)
	{
		$test_arr = explode(',', $test);
		foreach( $test_arr as $test )
		{
			// check if the language matches aaa-BBB;q=...
			if( preg_match('/^\s*([a-z]{2,3})(\-([a-z]{2,3}))?([;\s].*)?$/i', $test, $matches) )
			{	
				$lang	= strtolower($matches[1]);
				$region	= strtoupper($matches[3]); // sic!
				return true;
			}
			
			// check for stub languages
			if( !is_array(G_Lang::$s_stub) ) {
				G_Lang::$s_stub = array(
					'deutsch'	=> 'de',
					'englisch'	=> 'en',
					'english'	=> 'en',
					'german'	=> 'de',
				);
			}
			
			if( ($p = strpos($test, ';')) !== false ) {
				$test = substr($test, 0, $p);
			}
			$test = strtolower(trim($test));
			if( isset(G_Lang::$s_stub[$test]) ) {
				$lang = G_Lang::$s_stub[$test];
				$region = '';
				return true;
			}
		}
		
		$lang = '';
		$region = '';
		return false;
	}
	
	/***********************************************************************//**
	Function checks if the given language string is valid *and* is one of the 
	languages defined by set()
	
	@param		$test		The language string to test
	@return					true if the test string is valid and is defined by 
							set(), false otherwise
	***************************************************************************/
	function isMatchingLang($test)
	{
		if( !$this->isValidLang($test, $test_lang, $test_region) )
		{
			return false;
		}
		
		if( $test_region != '' )
		{
			// check if our languages match the "lang-region"
			if( in_array("$test_lang-$test_region", $this->lang_n_regions) ) {
				return true;
			}

			// check, if our languages match "lang" (strict - do not use lang_hash[] here as this is automatically created also from de-DE)
			if( in_array("$test_lang", $this->lang_n_regions) ) {
				return true;
			}
			
			// if the test is eg. de-AT and it is not matched above but there are other regions defined, the language does not match
			// eg. if the user defines "de-DE, de" 
			if( $this->anyRegionForLang($test_lang) ) {
				return false; 
			}
		}

		// region not set or not important
		if( $this->lang_hash[$test_lang] )
		{
			return true;
		}
		
		return false;
	}
	
	function buildWordFreqArray($text)
	{
		$all_words = explode(' ', G_String::normalize($text));
		
		$word_freq = array();
		foreach( $all_words as $word ) {
			if( $word != '' ) {
				$word_freq[ $word ] ++;
			}
		}
		
		return $word_freq;
	}
	
	function guessLangFromText($text)
	{
		return $this->guessLangFromFreq($this->buildWordFreqArray($text));
	}
	
	/***********************************************************************//**
	Guess a language from given words. This only works, if all languages 
	defined by set() have program/lib/stopword_*.txt files.
	
	Guessing the language is really straight-forward: We count the stopwords 
	for every language - the language for which the most stopwords are found, 
	is the guessed language. (We count the *total* number of stopwords, as the 
	unique number may differ largely from language to language, eg. see the 
	articles "der, die, das" in english and a simple "the" in english).
	
	The method is not 100% - maybe not even 90% - however, for our purpose, it 
	seems to be just fine.  Remember: If a document declares its language
	properly, we do not need this function at all!
	
	@param array $words	Hash with word=>word_freqency
	@return array('lang'=>'..', 'complete_guess'=>true/false); 
			with
			- `lang` - the guessed langauge, empty for _unknown_
			- `complete_guess` is set
				- if the language could be guessed _or_
				- if the language could not be guessed but we have stopword 
				  lists for *all* languages defined by set() (we can assume the
				  language to be "unknown, but known uninteresting" then)
 	***************************************************************************/
	function guessLangFromFreq($words)
	{
		$stopword_obj = new G_Stopword();
		$stopwords = $stopword_obj->get_all_stopwords();

		$lang_freqs = array();
		foreach( $words as $word=>$freq ) 
		{
			if( is_array($stopwords[ $word ]) )			
			{
				foreach( $stopwords[ $word ] as $lang ) 
				{
					$lang_freqs[ $lang ]['unique']++;
					$lang_freqs[ $lang ]['total'] += $freq;
				}
			}
		}
		
		$GUESS_MIN_UNIQUE_WORDS = 3;	// a language with fewer unique stopwords is discarded
		$GUESS_MIN_TOTAL_WORDS = 6;		// a language with fewer total stopwords is discarded
	
		$guessed_lang = '';
		$guessed_lang_total_words = 0;
		foreach( $lang_freqs as $lang=>$freq ) 
		{
			if( $freq['unique'] >= $GUESS_MIN_UNIQUE_WORDS
			 && $freq['total'] >= $GUESS_MIN_TOTAL_WORDS )
			{
				if( $freq['total'] > $guessed_lang_total_words ) {	// we compare against the number of total words, see remark above.
					$guessed_lang_total_words = $freq['total'];
					$guessed_lang = $lang;
				}
			}
		}
		
		$complete_guess = true; 
		if( $guessed_lang == '' ) {
			foreach( $this->lang_hash as $lang ) {
				$test = $stopword_obj->get_stopwords_by_lang($lang);
				if( sizeof($test) == 0 ) {
					$complete_guess = false;
					break;
				}
			}
		}
		
		// done
		return array(
			'lang'=>$guessed_lang, 
			'complete_guess'=>$complete_guess
		);
	}
	
	private function anyRegionForLang($test_lang)
	{
		foreach( $this->lang_n_regions as $lang_n_region ) {
			if( substr($lang_n_region, 0, strlen($test_lang))==$test_lang ) {
				if( $lang_n_region != $test_lang ) {
					return true; // there is a at least one region defined for the given language (eg. de-AT)
				}
			}
		}
		
		return false;
	}

	static function str2id($str)
	{	
		if( strlen($str) >= 2 ) {
			$str = strtolower($str);
			return (ord($str)<<8) | ord($str[1]);
		}
		else {
			return 0;
		}
	}
	
	static function id2str($id)
	{
		return chr($id>>8) . chr($id&0xFF);
	}
	
};
