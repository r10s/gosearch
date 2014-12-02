<?php 
/*******************************************************************************
Go Search Engine - Encryption
****************************************************************************//**

Encryption

@author Björn Petersen

*******************************************************************************/

class G_Crypt
{
	private $public_key;
	private $private_key;
	
	function __construct($public_key, $private_key)
	{
		$this->public_key  = $public_key;
		$this->private_key = $private_key;
	}
	
	function encrypt($text)
	{
		return 'raw:' . $text;
	}
	
	function decrypt($text)
	{
		if( substr($text, 0, 4) != 'raw:' )
		{
			return false;
		}
		
		return substr($text, 4);
	}
};

