<?php
/*******************************************************************************
Go Search Engine - Manage Host
****************************************************************************//**

@author Björn Petersen
	
*******************************************************************************/

class G_Hosts
{
	private $hosts = false;

	
	/***********************************************************************//**
	Returns an array of hosts.
	
	The host returned by getSelf() ist skipped.
	***************************************************************************/
	function getHosts()
	{
		if( !is_array($this->hosts) ) {
			$this->hosts = $this->getPredefinedHosts();
		}
		
		return $this->hosts;
	}
	
	
	/***********************************************************************//**
	Returns an array of hosts predefined by G_HOSTS in `config/config.php`
	
	The host returned by getSelf() ist skipped.
	***************************************************************************/	
	private function getPredefinedHosts()
	{
		$ret = array();
		
		if( defined('G_HOSTS') )
		{
			$selfAbs = $this->getSelf()->getAbs();
			
			$temp = explode(',', G_HOSTS);
			foreach( $temp as $host )
			{
				$host = trim($host);
				if( $host != '' ) 
				{
					if( substr($host, -1) != '/' ) {
						$host .= '/'; // an installation of this program is always a directory!
					}
					
					$test = new G_Url($host);
					if( !$test->error ) {
						if( $test->getAbs() != $selfAbs ) {
							$ret[] = array('url'=>$test);
						}
					}
				}
			}
		}
		
		return $ret;
	}
	
	/***********************************************************************//**
	Returns the host or host/dir as an G_URL object.
	
	The returned URL has the same format as the ones returned by getHosts().
	***************************************************************************/
	static function getSelf() 
	{
		$scheme = 'http://';
		if( $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off' ) {
			$scheme = 'https://';
		}
	
		// get the host - HTTP_HOST will contain the port, SERVER_NAME will not
		$host = $_SERVER['HTTP_HOST'];
		
		// add directory to the name (okay, then it is more than an host name, however, we need a unique part for user identification;
		// we allow both, user@go.hostname.com as well as user@hostname.com/go
		$dir = $_SERVER['SCRIPT_NAME']; 			// is now sth. like /go/index.php
		$dir = str_replace("\\", "/", $dir);		// convert backslashes to slashed - may be needed for windows
		$dir = substr($dir, 0, strrpos($dir, '/'));	// remove stuff after the last slash (/index.php), results in /go/
					// NB: we prefer SCRIPT_NAME over PHP_SELF, as this allows virtual files as index.php/foo.bar
		
		return new G_Url($scheme . $host . $dir . '/');
	}	
};
