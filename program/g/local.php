<?php
/*******************************************************************************
Go Search Engine - Local and translation stuff
****************************************************************************//**

Local and translation stuff

@author Björn Petersen

*******************************************************************************/

class G_Local
{
	static private $s_lang_id;
	static private $s_basic_strings_loaded = false;
	static private $s_strings;

	
	/***********************************************************************//**
	find out the language id id use, eg. 'de', 'de-AT', 'en', 'en-US' etc.
	***************************************************************************/
	static function langId()
	{
		G_Local::_('dummy'); // just make sure the strings are initialised
		return G_Local::$s_lang_id;
	}

	
	/***********************************************************************//**
	add a language file to the strings useable for translation.
	scope must not contain leading or trailing slashes.
	Example load('program/lang'); load('program/zeroclick/useragent/lang');
	***************************************************************************/
	static function load($scope)
	{
		// init the language array, if not yet done
		if( !is_array(G_Local::$s_strings) ) {
			G_Local::$s_strings = array();
		}

		// find out, which language file we can use
		$desired_lang = defined('G_FORCE_LANG')? constant('G_FORCE_LANG') : $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		$desired_lang = substr(strtolower(trim($desired_lang)), 0, 2);
		$lang_file = G_Local::getLangFilename($scope, $desired_lang);
		if( !@file_exists($lang_file) ) 
		{ 
			$desired_lang = 'en'; 
			$lang_file = G_Local::getLangFilename($scope, $desired_lang);
			if( !@file_exists($lang_file) ) 
			{
				return; // cannot load language file - this may be or may not be an error
			}
		}

		$strings = array();
			if( $scope == 'program/lang' ) {
				G_Local::$s_lang_id = $desired_lang;
			}
			require_once($lang_file);
		G_Local::$s_strings = array_merge(G_Local::$s_strings, $strings);
	}

	
	/***********************************************************************//**
	convert scope+lang to a full file name, scope must not contain leading or 
	trailing slashes.
	***************************************************************************/
	static private function getLangFilename($scope, $lang)
	{
		return G_DOCUMENT_ROOT . '/' . $scope . '/' . $lang . '/strings.php';;
	}


	/***********************************************************************//**
	_() is used to get a localized string by an ID.
	On the first call, the function loads the strings; if no localized version 
	matching the given id can be found, the ID itself ist returned.
	The returned value can be treated as HTML, an additional htmlspecialchars() 
	ist not needed.
	/**************************************************************************/
	static function _($id)
	{
		if( !G_Local::$s_basic_strings_loaded ) 
		{	
			// load basic language file
			G_Local::$s_basic_strings_loaded = true;
			G_Local::load('program/lang');
		}
		
		$ret = isset(G_Local::$s_strings[$id])? G_Local::$s_strings[$id] : $id; 
		
		$args = func_get_args();
		for( $i = 1; $i < sizeof($args); $i++ )
		{
			$ret = str_replace('$'.$i, $args[$i], $ret);
		}
		
		return $ret;
	}
	
	/***********************************************************************//**
	_cnt() is used for singular/plural strings;
	the localized strings must follow the convetion "$1 user|$1users"
	***************************************************************************/
	static function _cnt($id, $cnt) 
	{
		$ret = explode('|', G_Local::_($id, $cnt));
		if( $cnt!=1 && sizeof($ret)>1 ) {
			return $ret[1]; // plural
		}
		else {
			return $ret[0]; // singular
		}
	}
	
	/***********************************************************************//**
	Prepare a string ready to be used in JavaScript.
	In JavaScript, both quotes are not allowed - remember 
	`onclick="confirm('text');"` ... however, as the language files are HTML, 
	`&quot;` can be used
	***************************************************************************/
	static function _jssafe($id)
	{
		$ret = G_Local::_($id);
		$ret = strtr($ret, "'\"", "__");
		return $ret;
	}
	
	static function _default($id, $default)
	{
		$str = G_Local::_($id);
		return $str==$id? $default : $str;
	}

};
