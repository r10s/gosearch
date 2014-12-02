<?php 
/*******************************************************************************
Go Search Engine - Character Encodings
****************************************************************************//**

Character Encodings handling

@author Björn Petersen

*******************************************************************************/

class G_Encoding
{	
	static private $error;
	static private $encodings;

	
	static function init()
	{
		// init encodings 
		if( @function_exists('mb_list_encodings') ) {
			$enc_arr = mb_list_encodings();
			foreach( $enc_arr as $enc ) {
				G_Encoding::$encodings[ strtoupper($enc) ] = $enc;
			}
		}
		G_Encoding::$encodings['ISO-8859-1']	= '@utf8_encode'; // the @ is important for getEncodings()
		G_Encoding::$encodings['UTF-8']		= '@already_ok';
	}

	
	static function getEncodings()
	{
		$ret = array();
		foreach( G_Encoding::$encodings as $key => $value ) {
			if( $value{0} == '@' ) {
				$ret[] = $key;
			}
			else {
				$ret[] = $value;
			}
		}
		return $ret;
	}
	
	
	static function getLastError()
	{
		return G_Encoding::$error;
	}
	
	
	static function toUtf8($src_charset, &$text)
	{
		$action = G_Encoding::$encodings[ strtoupper($src_charset) ];
		if( $action == '@already_ok' )
		{
			return true; // success
		}
		else if( $action == '@utf8_encode' )
		{
			$text = utf8_encode($text);
			return true; // success
		}
		else if( $action )
		{
			$text = mb_convert_encoding($text, 'UTF-8', $action /*use original upper/lower case*/ );
			return true; // success
		}
		else
		{		
			G_Encoding::$error = sprintf('err_badcharset (%s)', $src_charset);
			return false; // unsupported encoding
		}
	}
	
};	


G_Encoding::init();
