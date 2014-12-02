<?php
/*******************************************************************************
Go Search Engine - String normalisation
****************************************************************************//**

@author Björn Petersen

*******************************************************************************/

class G_Normalize
{
	const KEEP_SYMBOLS		= 0x01;
	const KEEP_NUMBERS		= 0x02;
	const DISCARD_SYMBOLS	= 0; // default, may be given to the constructor for clarity
	const DISCARD_NUMBERS	= 0; // default, may be given to the constructor for clarity
	
	private $tr;
	
	function __construct($flags)
	{
		// these characters are discarded and do not go to the normalized string (these characters are eg. not searchable)
		$delimiters = array(
			'^',	'.',	':',	',',	';',	'…',
			'!',	'¡',	'?',	'¿',
			'+',	'-',	'*',	'/',	'=',	'~',	'÷',	'×',	'¬',
			'(',	')',	'[',	']',	'{',	'}',	'<',	'>',
			'"',	"'",	'´',	'`',	
			'‘',	'’',	'‚',	'“',	'”',	'„',	'«',	'»',	'‹',	'›',			
			'‐',	'‑',	'‒',	'–',	'—',	'―',	'_',
			"\\",	'&',	'¦',	
			'●',	'•',	'·',	
			'↑',	'↓',	'←',	'→',	'↗',	'▲',	'▼',	'◄',	'►',	
		);
	
		// symbols, may be discarded (default) or may be treated as words (and are eg. searchable then)
		$symbols = array(
			'$',	'€',	'£',	'¥',
			'§',	'@',	'%',	'#',	'°',
			'†',	'©',	'®',	'™',
		);
		
		// digits, may  be discarded (default) or may be treated as words (and are eg. searchable then; in this case, if possible we normalize non-0-9 to 0-9)
		$digits = array(
			'0'=>'0',	'1'=>'1',	'2'=>'2',	'3'=>'3',	'4'=>'4',	'5'=>'5',	'6'=>'6',	'7'=>'7',	'8'=>'8',	'9'=>'9',
			'²'=>' 2 ',	'³'=>' 3 ',	'¼'=>' 1 4 ',	'½'=>' 1 2 ',	'¾'=>' 3 4 ', // take care not using delimiters here!
		);

		// build the transition table
		$this->tr = G_Normalize::getSpaceTr(); // discard linebreaks and special spaces
		
		foreach( $delimiters as $ch )		  
		{
			$this->tr[$ch] = ' '; // discard delimiters
		}			
		
		if( $flags & G_Normalize::KEEP_SYMBOLS )
		{	
			foreach( $symbols as $ch ) { $this->tr[$ch] = ' '.$ch.' '; }	// keep symbols
		}
		else
		{
			foreach( $symbols as $ch ) { $this->tr[$ch] = ' '; } 			// discard symbols (default)
		}
		
		if( $flags & G_Normalize::KEEP_NUMBERS )
		{
			foreach( $digits as $ch=>$repl ) { $this->tr[$ch] = $repl; }	// keep numbers
		}
		else
		{
			foreach( $digits as $ch ) { $this->tr[$ch] = ' '; }				// discard numbers (default)
		}		
	}

	/***********************************************************************//**
	Function normalizes a string.
	The goal is, after normalisation, the string shall only contain 
	lower-case characters, numbers and symbols that are simelar to characters.
	Currently, this is true for ASCII and for common unicode characters.
	
	@return normalized string
	***************************************************************************/	
	function normalize($str)
	{
		// transform
		$str = G_String::tolower($str);
		$str = strtr($str, $this->tr);
		
		// remove multiple and leanding/trailing spaces
		while( strpos($str, '  ')!==false ) { $str = str_replace('  ', ' ', $str); }
		return trim($str);
	}
	
	
	/***********************************************************************//**
	Function returns an array with <space>=>' ' values (this includes lineends 
	and tabs)
	***************************************************************************/	
	static function getSpaceTr()
	{
		return array(
			"\t"			=> ' ',
			"\n"			=> ' ',
			"\r"			=> ' ',	
			"\xC2\xA0"		=> ' ',	// &nbsp;
			"\xEF\xBB\xBF"	=> ' ',	// BOM for UTF-8, shown in ASCII as "ï»¿"			
		);
	}	
};
