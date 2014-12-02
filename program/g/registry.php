<?php
/*******************************************************************************
Go Search Engine - The registry - ini, users and more
****************************************************************************//**

Handling the registry - ini, users and more.

@author Björn Petersen

*******************************************************************************/

class G_Registry
{
	static private $s_ini_val;
	
	/***********************************************************************//**
	Read a key from the INI.
	
	For this purpose, all INI  values are cached on the first call.
	***************************************************************************/
	static function iniRead($key, $default)
	{
		if( !is_array(G_Registry::$s_ini_val) )  {
			G_Registry::$s_ini_val = array();
			$reg = G_Db::db('registry');
			if( !$reg->sql->query("SELECT f_key, f_value FROM t_ini;") ) {
				die('t_ini not ready!'); // we should really stop here; this may happen eg. for cron jobs if setup is not yet ready
			}
			
			while( ($record=$reg->sql->next_record()) !== false ) {
				G_Registry::$s_ini_val[ $record['f_key'] ] = $record['f_value'];
			}
		}
		
		return isset(G_Registry::$s_ini_val[$key])? G_Registry::$s_ini_val[$key] : $default;
	}

	
	static function iniWrite($key, $value)
	{
		$reg = G_Db::db('registry');
		$reg->addOrUpdateRecord('t_ini', array('f_key'=>$key), array('f_value'=>$value));
		G_Registry::$s_ini_val[ $key ] = $value;
	}
};
