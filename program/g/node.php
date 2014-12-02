<?php
/*******************************************************************************
Go Search Engine - Accessing other Nodes (or Hosts)
****************************************************************************//**

Accessing other Nodes (or Hosts)

@author Björn Petersen

*******************************************************************************/

class G_Node
{
	private $url;
	private $public_key;
	
	function get_public_key()
	{
		if( $public_key == '' ) {
			$this->send_raw_msg('get_public_key');
		}
	}
	
	private function send_raw_msg($msg)
	{
	}
	
	public function send_msg($msg)
	{
	}
};
