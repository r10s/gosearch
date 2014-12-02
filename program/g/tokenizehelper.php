<?php
/*******************************************************************************
Go Search Engine - Tokenize Helper
****************************************************************************//**

The only sqlite/fts tokenizer that is always available is the tokenizer 
"simple".  In general, the tokenizer works well with unicode-characters, but 
lacks the ability to convert Unicode to lowercase, to remove diacritics and
to recognize unicode delimiters.

The class G_TokenizeHelper solves these problems by converting the text to ascii
before it is added to the FTS;  moreover, it adds some hints to allow
reconstruction of the original input in most cases.

NB: Diacritics:  Words that differ only in diacritics are the same words for us
(this is also the default in sqlite/unicode61 and this is the default in
localized Google implementations) So G_TokenizeHelper converts diacritics to
their base character, however, other characters as greek or cyrillic stay alone.

BTW: Fine unicode table available at: http://unicode-table.com/en/#0119

@author Björn Petersen ×

*******************************************************************************/


class G_TokenizeHelper
{
	private static $s_asciifyPrepare;
	private static $s_asciifyTr;
	private static $s_ids;
	
	private static $s_hex   = '0123456789ABCDEF '; // changing this or the next string would make all stored text-markers unusable!
	private static $s_delim = ".,:;!?+~*/=[]()_|"; // ASCII-only, delimiters only, no HTML-Characters (<>&"), no characters that _may_ be non-delimiters (as &%$@-#§`^'), no space, no backslash
	
	static function init()
	{
		if( is_array(G_TokenizeHelper::$s_asciifyPrepare) )
			return; // initialisation already done
		
		$cnvDiacritics = array( 
			// remove diacritics from latin characters
			// (accent=>raw, uppercase characters are added automatically)
			// TODO: Diacritics of other languages messing!
			'à'=>'a',	'á'=>'a',	'â'=>'a',	'ã'=>'a',	'ä'=>'ae',	'å'=>'a',	'æ'=>'ae',
			'ç'=>'c',	'ć'=>'c',	'ĉ'=>'c',	'č'=>'c',
			'è'=>'e',	'é'=>'e',	'ê'=>'e',	'ë'=>'e',	'ę'=>'e',
			'ì'=>'i',	'í'=>'i',	'î'=>'i',	'ï'=>'i',	'ñ'=>'n',
			'ó'=>'o',	'ò'=>'o',	'ô'=>'o',	'õ'=>'o',	'ö'=>'oe',	'ø'=>'o',	'œ'=>'oe',
			'ß'=>'ss',	'ś'=>'s',	'ŝ'=>'s',	'ş'=>'s',	
			'ú'=>'u',	'ù'=>'u',	'û'=>'u',	'ü'=>'ue',				
			'ý'=>'y',	'ÿ'=>'y',
			
			// remove diacritics from greek characters
			// TODO: many other characters missing ...
			'ἀ'=>'α',
			'ί'=>'ι',
			
			// the following stuff stays as real characters, however, help on the case conversion
			// (uppercase=>lowercase)
			// TODO: Characters of other languages messing!
			'Ð'=>'ð',
			'Þ'=>'þ',
		);
		
		// here, we add some replacements that are not undone in unasciify
		G_TokenizeHelper::$s_asciifyPrepare = array_merge(G_Normalize::getSpaceTr(), array(
			// for quotes, we always use the single quotes (double quotes must be delim'encoded, see above)
			'`'=>"'",		'´'=>"'",		'‘'=>"'",		'’'=>"'",		'‚'=>"'",		'‹'=>"'",		'›'=>"'",	
			'“'=>"'",		'”'=>"'",		'„'=>"'",		'«'=>"'",		'»'=>"'",	
			
			// convert common unicode delimiters to ascii delimters - otherwise the "simple" tokenizer would add them to a word ...
			'↑'=>'-',		'↓'=>'-',		'←'=>'-',		'→'=>'-',
			'▲'=>'-',		'▼'=>'-',		'◄'=>'-',		'►'=>'-',
			'●'=>'-',		'•'=>'-',
			'¦'=>'|',		'…'=>'...',		
			'‒'=>'-',		'–'=>'-',		'—'=>'-',		'―'=>'-',
			'²'=>' 2 ',		'³'=>' 3 ',		'¼'=>' 1/4 ',	'½'=>' 1/2 ',	'¾'=>' 3/4 ',
			
			// for the following symbols, leave them as letters, but make sure, they're surrounded by delimiters
			// the "simple" tokenizer would add them to bounding words otherwise)
			'†'=>' † ',		'™'=>' ™ ',		'©'=>' © ',		'®'=>' ® ',		'¿'=>' ¿ ',		'¡'=>' ¡ ',
			'€'=>' € ',		'£'=>' £ ',		'¥'=>' ¥ ',
		
		));
		$delimEncode = array(
			// the following characters are delimeter-encoded using {<charCode>} and added to G_TokenizeHelper::$s_asciifyPrepare
			'{', '}', // internal use
			'<', '>', '"', '&', // HTML characters
			
			'×', '÷', '¬' // Unicode delimiters, however, some are converted to ascii above ...
		);

		// build the conversion tables
		G_TokenizeHelper::$s_asciifyTr = array();
		G_TokenizeHelper::$s_ids = array();
		foreach( $cnvDiacritics as $lcFrom=>$lcTo )
		{
			$ucFrom = G_String::toupper($lcFrom);

			$lcID = G_String::ord($lcFrom);
			$ucID = G_String::ord($ucFrom);
			
			G_TokenizeHelper::$s_asciifyTr[$ucFrom] = sprintf('{%06d', $ucID);	// important: 1+6=7 characters decimal ID, see below [*]
			G_TokenizeHelper::$s_asciifyTr[$lcFrom] = sprintf('{%06d', $lcID);	// if upper-/lowercase are the same, lowercase wins
			
			G_TokenizeHelper::$s_ids[$ucID] = array($ucFrom, $lcTo);
			G_TokenizeHelper::$s_ids[$lcID] = array($lcFrom, $lcTo);
		}
		
		foreach( $delimEncode as $from ) {
			$id = G_String::ord($from);
			$rules = sprintf('%X', $id);
			$rules = strtr($rules, G_TokenizeHelper::$s_hex, G_TokenizeHelper::$s_delim);
			G_TokenizeHelper::$s_asciifyPrepare[ $from ] = '{'.$rules.'}';
			
			G_TokenizeHelper::$s_ids[$id] = array($from, $from); // needed at [*2]
		}
	}
	
	
	/***********************************************************************//**
	asciify() converts the input to plain ascii understandable by the "simple"
	sqlite tokenizert.  To allow reconstruction, we add the original characters
	encoded with delimiters only in front of the words.
	
	NB: What is a delimiter in sqlite/simple: From simpleNext() in sqlite3.c):
	Every character <0x80 that is no alpha-numberic, is treated as a delimiter;
	Characters >=0x80 are _never_ treated as delimiters!
	***************************************************************************/
	static function asciify($str)
	{
		$str = strtr($str, G_TokenizeHelper::$s_asciifyPrepare);
		$str = preg_replace_callback('/([a-zA-Z]*[\x80-\xFF]+)+/', array('G_TokenizeHelper', 'asciifyCallback'), $str);
	
		// remove multiple spaces
		while( strpos($str, '  ')!==false ) { $str = str_replace('  ', ' ', $str); }
		return trim($str);
	}
	static function asciifyCallback($matches)
	{
		$word = strtr($matches[0], G_TokenizeHelper::$s_asciifyTr);
		
		$rules = '';
		while( ($p=strpos($word, '{'))!==false )
		{
			$id = intval(substr($word, $p+1, 6 /* see [*] */), 10);
			$word = substr_replace($word, G_TokenizeHelper::$s_ids[$id][1], $p, 7); // for the `7`: see [*] aboves
			$rules = sprintf('%X %X ', $p, $id) . $rules;
		}
	
		if( $rules != '' ) {
			$rules = strtr(trim($rules), G_TokenizeHelper::$s_hex, G_TokenizeHelper::$s_delim);
			return '{' . $rules . '}' . $word;
		}
		else {
			return $word;
		}
	}
	
	/***********************************************************************//**
	Function recreated most unicode characters lowered/normalized by asciify() 
	before.
	
	The input string should contain no HTML but <b> and </b> for highlighting.
	The returned result is HTML.
	***************************************************************************/
	static function unasciify($str)
	{
		return preg_replace_callback('/{([^}]*)}([<\/>a-zA-Z\x80-\xFF]*)/', array('G_TokenizeHelper', 'unasciifyCallback'), $str);
	}
	static function unasciifyCallback($matches)
	{
		$rules = strtr($matches[1], G_TokenizeHelper::$s_delim, G_TokenizeHelper::$s_hex);
		$rules = explode(' ', $rules);
		$word  = $matches[2];
		if( sizeof($rules)==1 )
		{
			// recreate a single character as `{` `}` `<` `>` `&` or `"`
			$id = intval($rules[0], 16);
			$word = htmlspecialchars(G_TokenizeHelper::$s_ids[$id][0]) . $word; // set at [*2]
		}
		else
		{
			$b_start = strpos($word, '<b>');
			$b_end = strpos($word, '</b>');
			$word = strip_tags($word);
			
			for( $i = 0; $i < sizeof($rules); $i+=2 ) {
				$p = intval($rules[$i], 16);
				$id = intval($rules[$i+1], 16);
				$word = substr_replace($word, G_TokenizeHelper::$s_ids[$id][0], $p, strlen(G_TokenizeHelper::$s_ids[$id][1]));
			}
			
			if( $b_start!==false ) $word = '<b>' . $word;
			if( $b_end!==false ) $word = $word . '</b>';
		}
		
		return $word;
	}
	
	static function searchify($str)
	{
		$str = G_TokenizeHelper::asciify($str);
		$str = preg_replace('/{([^}]*)}/', ' ', $str);
		
		// remove multiple spaces
		while( strpos($str, '  ')!==false ) { $str = str_replace('  ', ' ', $str); }
		return trim($str);
	}
};

G_TokenizeHelper::init();
