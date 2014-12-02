<?php 
/*******************************************************************************
Go Search Engine - Tools
****************************************************************************//**

The class G_Tools provides some tools that do not fit well into other clases

@author Björn Petersen

*******************************************************************************/

class G_Tools 
{	
	/***********************************************************************//**
	Convert a comma-separated list to an array with key=>1
	***************************************************************************/
	static function str2hash($str)
	{
		$hash = array();
		$temp = explode(',', $str);
		foreach( $temp as $ext ) {
			$ext = trim($ext);
			if( $ext != '' ) {
				$hash[ $ext ] = 1;
			}
		}
		return $hash;
	}
	
	
	/***********************************************************************//**
	Parse formats used in the content-type, but also otherwhere (eg. 
	"text/html; charset=foo" or "0; url=refresh")
	 
	@param string $string		string to parse
	@param[out] string $ct		first, unnamed argument
	@param[out] array $ctparam	subsequent, named arguments as key=>value
	@return void
	***************************************************************************/
	static function splitContentType($string, &$ct, &$ctparam)
	{
		
		$arr = explode(';', $string);
		$ct = trim($arr[0]);
		$ctparam = array();
		for( $i = 1; $i < sizeof($arr); $i++ ) {
			if( ($p=strpos($arr[$i], '=')) !== false ) {
				$ctparam[ strtolower(trim(substr($arr[$i], 0, $p))) ] = trim(substr($arr[$i], $p+1));
			}
		}
	}
			
	
	
	/***********************************************************************//**
	Get the current hostname as a string as " on domain.com"; the return value 
	is HTML.
	***************************************************************************/	
	static function onHost()  
	{
		// we add the spaces here, maybe we'll change this function to @hostname some time.
		// however, currently we don't to avoid confusion with e-mail adresses
		$ret = ' ' . G_Local::_('a_on_b') . ' ' . htmlspecialchars(G_String::url2human(G_Hosts::getSelf()->getAbs()));
		return $ret;
	}
	
	
	/***********************************************************************//**
	Flush echo'd content up to here.  `forceFlush()` may be equal to a simple 
	`flush()`, however, it also flushes pending output buffers (eg. gzip).
	
	(other methods from http://www.php.net/manual/de/function.flush.php#87807 ,
	eg. `apache_setenv('no-gzip' ..)`, `ini_set('zlib.output_compression' ..)`,
	`ini_set('implicit_flush' ..)` and `ob_implicit_flush()` seems not to be 
	required)
	***************************************************************************/
	static function forceFlush()
	{	
		// fill the buffers with about 4 K - do not do this as forceFlush() is also used eg. by the cron-pixel
		//echo str_repeat('<!-- X -->', 410); -- seems not to help
		
		// flush all buffers; this was sufficient eg. for DSM 4, but no longer for DSM 5 :-(
		for( $i = ob_get_level()-1; $i >= 0; $i-- ) { 
			ob_flush();
		}
		
		// flush PHP buffers
		flush();
	}
	
};
