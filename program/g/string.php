<?php
/*******************************************************************************
Go Search Engine - String Tools
****************************************************************************//**

Various string tools and formatting functions.

@author Björn Petersen

*******************************************************************************/


class G_String
{
	public static $s_mb_strtolower_exists; // public, set from the global part
	private static $s_cleanTr;
	private static $s_normalizer;

	
	/***********************************************************************//**
	Convert an UTF-8 string to lower case.
	***************************************************************************/
	static function tolower($str)
	{
		// make text lowercase, if possible, use mb_strtolower() as this also regards cyrillic, greek etc.
		if( G_String::$s_mb_strtolower_exists ) {
			return mb_strtolower($str, 'UTF-8');
		}
		else {
			return strtolower($str);
		}
	}	
	
	
	/***********************************************************************//**
	Convert an UTF-8 string to upper case.
	***************************************************************************/
	static function toupper($str)
	{
		// make text uppercase, if possible, use mb_strtolower() as this also regards cyrillic, greek etc.
		if( G_String::$s_mb_strtolower_exists ) {
			return mb_strtoupper($str, 'UTF-8');
		}
		else {
			return strtolower($str);
		}
	}	
	

	/***********************************************************************//**
	Calculate the ordinary number of an UTF-8 character.
	
	If the function receives an empty or erroneous string, it returns 0 by 
	definition.
	***************************************************************************/	
	static function ord($ch)
	{
		if( strlen($ch) == 0 ) 
			return 0; // input error
			
		$id = unpack('N', mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8'));
		if( sizeof($id) == 0 )
			return 0; // unpack error
		
		return intval($id[1]); // may still be 0 ...
	}
	
	
	/***********************************************************************//**
	Function "cleans" a string by doing the following
	- removing linebreaks and special spaces (replace by spaces)
	- replace sequences of more than one spaces by a single space 
	- remove sequences of more than 3 characters
	- trim string
	
	Special characters etc. are _not_ removed, not even triangles etc.
	
	@return cleaned string
	***************************************************************************/		
	static function clean($str)
	{
		// init
		if( !is_array(G_String::$s_cleanTr) ) 
		{
			G_String::$s_cleanTr = G_Normalize::getSpaceTr();
		}
		
		// transform
		$str = strtr($str, G_String::$s_cleanTr);
		
		// remove multiple characters
		$str = preg_replace('/([*+~=_:,\/\-\.]){4,100}/', ' ', $str); // {4,100} - include an upper limit - otherwise, the pcre.backtrack_limit (?) limit will be reached and the script is stopped by a server error (!) and/or the wamp-server crashes
		
		// remove multiple spaces
		while( strpos($str, '  ')!==false ) { $str = str_replace('  ', ' ', $str); }
		
		// nearly done: a final trim() to get rid of leading/trailing spaces
		return trim($str);
	}
	
	
	/***********************************************************************//**
	The function is a shortcut to
		
		$normalize_obj = new G_Normalize(G_Normalize::DISCARD_NUMBERS | G_Normalize::DISCARD_SYMBOLS);
		$str = $normalize_obj->normalize($str);
	
	@return normalized string
	***************************************************************************/		
	static function normalize($str)
	{
		if( !is_object(G_String::$s_normalizer) ) {
			G_String::$s_normalizer = new G_Normalize(G_Normalize::DISCARD_NUMBERS | G_Normalize::DISCARD_SYMBOLS);
		}
		return G_String::$s_normalizer->normalize($str);
	}
	
	
	/***********************************************************************//**
	return the first words from a string. Used eg. to force a title of a max. 
	length.  Moreover, if someone wants to fool this function, also max. strlen 
	is applied.
	***************************************************************************/	
	static function maxWords($in_str, $max_words)
	{
		// apply a max. string length
		$temp = $in_str;
		$max_chars = $max_words * 16;
		if( strlen($temp) >= $max_chars ) {
			$temp = substr($temp, 0, $max_chars); // UTF-8 may be out of order, most times, this will be fixed below
		}
		
		// get the first words of the string
		$out_str = '';
		$words_added = 0;
		$temp = explode(' ', $temp);
		for( $i = 0; $i < sizeof($temp); $i++ ) {
			$out_str .= $i? ' ' : '';
			$out_str .= $temp[$i];
			$words_added++;
			if( $words_added >= $max_words ) {
				break;
			}
		}
		
		if( strlen($in_str) > strlen($out_str) ) {
			$out_str .= ' ...';
		}
		
		return $out_str;
	}
	
	
	/***********************************************************************//**
	The Function hilites the given words in the given string.
	In: ASCII, Out: HTML: While the given string and the words are assumed to
	be ASCII, the function returns HTML.	
	***************************************************************************/
	static function hilite($str, $words)
	{
		$str = strtr($str, array('_'=>'_US'));
	
		$words = explode(' ', G_String::normalize($words));
		foreach( $words as $word ) {
			$str = preg_replace('/('.$word.')/i', '_HS\\1_HE', $str);
		}
		
		//$str = htmlspecialchars($str);
		$str = strtr($str, array('_HS'=>'<b>', '_HE'=>'</b>', '_US'=>'_'));
		return $str;
	}
	
	
	/***********************************************************************//**
	2human functions - convert various types to human readable strings.
	The return value is valid HTML, however, in the very most cases, no special
	HTML entities are used.
	***************************************************************************/	
	static function timestamp2human($timestamp)
	{
		return strftime(G_Local::_('format_date_time'), $timestamp);
	}
	

	/***********************************************************************//**
	Brings the URL in a  readable form, this may result in lack of information!
	
	Trailing slashes are removed only if placed directly after the domain -
	otherwise the trailing slash is significant as `foo.com/bar/` is a directory
	and `foo.com/bar` is a file.
	***************************************************************************/	
	static function url2human($url, $maxchars = 80)
	{
		assert( is_string($url) );
	
		// remove protocol http:// - leave https:// ftp:// etc.
		if( substr($url, 0, 7)=='http://' )
		{ 
			$url = substr($url, 7);
		}
	
		// remove trailing slash, if placed directly after the domain
		if( preg_match('#^([a-z]+://)?[a-z0-9\.\-]+/$#', $url) ) 
		{
			$url = substr($url, 0, -1);
		}
	
		if( strlen($url)>$maxchars )
		{
			$half = intval($maxchars/2)-1;
			return substr($url, 0, $half) . '[...]' . substr($url, -$half); // `&hellip` instead of `...` looks better but may cause problems with some browsers/fonts
		}
		
		return $url;
	}
	
	static function seconds2human($val)
	{
		$ret = sprintf('%1.3f', $val);
		$ret = str_replace('.', G_Local::_('format_decimal_mark'), $ret);
		$ret = G_Local::_('x_seconds', $ret);
		return $ret;
	}	
	
	/***********************************************************************//**
	Regarding the prefix type, we prefer `binary` as this is always unique
	1 KiB are known as 1024 bytes - 1 KB is 1000 Bytes (new) or 1024 Bytes (old)
	
	@param $prefix	eighter `si` or `binary`
	***************************************************************************/
	static function bytes2human($bytes, $prefix = 'binary') 
	{
		if( $prefix == 'si' ) { 
			$divisor = 1000; $mfix = ''; 	// si-Prefix: this will result in KB, comparable to weight, distance etc.
		}
		else { 
			$divisor = 1024; $mfix = 'i';	// iec-prefix or binary-prefix: this will result in KiB etc.
		}
	
		$K = $bytes / $divisor;
		$M = $K / $divisor;
		$G = $M / $divisor;
		$T = $G / $divisor;
		
		if( $T >= 0.8 )			{ $ret = sprintf('%1.1f T%sB', $T, $mfix);	}
		else if( $G >= 10.0 )	{ $ret = sprintf('%1.0f G%sB', $G, $mfix);	}
		else if( $G >= 0.8 )	{ $ret = sprintf('%1.1f G%sB', $G, $mfix);	}
		else if( $M >= 1.0 )	{ $ret = sprintf('%1.0f M%sB', $M, $mfix);	}
		else if( $K >= 1.0 )	{ $ret = sprintf('%1.0f K%sB', $K, $mfix);	}
		else 					{ $ret = sprintf('%1.0f Byte', $bytes);		}
		return  str_replace('.', G_Local::_('format_decimal_mark'), $ret);
	}	
	
	/***********************************************************************//**
	The function calculates a hash of the given text.  The goal is, that
	slimelar text produce the same hash.
	
	For this purpose:
	
	- we normalize the text to contain only characters and numbers
	- we regard only the word beginnings (10 characters, ignore common word 
	  endings)
	- we order the words by a-z 
	- from the result, we return a md5-hash
	
	In short, we can say, we build a md5() hash about the vocabulary.
	This seems to work fine when calling this function without HTML and
	with trimmed link lists.
	
	Ideas, if the above is not sufficient:
	
	- ignore any numbers
	- only regard the first few KiB of text (this may also speed up the
	  process), however, adding redudant words atop may move formally 
	  significant over the KiB border, so, I'm not sure if this really helps
	- ignore the most frequent words (what can this help for?)
	- regard only the first N characteds of a word (this may be useful for
	  singular/plural identification)
	- ignore short words (what can this help for? Short words normally won't
	  hurt as used in all documents - and if there is another short word, this
	  may be significant)
	- ignore common word endings for singular/plural, stemming.  However, this
	  may slow down things.
	- merge words with the same beginning. (eg. "Computer" and "Computers" or
	  "Peter" and "Petersen" - just an idea, not sure if this can help)
	
	see also: https://duckduckgo.com/?q=algorithm%20similar%20text
	***************************************************************************/
	static function vocabularyHash($str)
	{	
		// normalize the text so that it only contains letters
		$str = G_String::normalize($str);
		
		// if the normalized string is empty, we do not calcualte a hash but return an empty string
		if( $str == '' ) {
			return '';
		}
		
		// create array of unique words
		$unique = array();
		$str = explode(' ', $str);
		foreach( $str as $word ) {
			$unique[$word] = 1;
		}
		
		// sort the words by A-Z
		ksort($unique);
		$unique = array_keys($unique);

		return md5(implode('', $unique));
	}
	

};


/*******************************************************************************
Init the static stuff
*******************************************************************************/
G_String::$s_mb_strtolower_exists = @function_exists('mb_strtolower');


