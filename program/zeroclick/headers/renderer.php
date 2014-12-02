<?php 
/*******************************************************************************
Go Search Engine - Zero Click Headers renderer
****************************************************************************//**

Zero Click Headers renderer
 
Matched searches:
-	ip address
-	user agent
-	do not track
-	http header

@author Björn Petersen 

*******************************************************************************/

class Zeroclick_Headers_Renderer extends Zeroclick_Base
{
	private function egalize_str($str)
	{
		$str = strtolower($str);
		$str = strtr($str, ' _-', '   ');
		return $str;
	}
	
	function renderContent()
	{
		$ret = '';
	
		G_Local::load('program/zeroclick/headers/lang');
		
		$q = $this->egalize_str($this->q);

		if( $q == 'ip address'
 		 || $q == $this->egalize_str(G_Local::_('match_ip_address')) )
		{
			$ipadr = isset($_SERVER['HTTP_X_FORWARDED_FOR'])? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
			$ret .= G_Local::_('your_ip_address') . ': ' . htmlspecialchars($ipadr) . ' (' . htmlspecialchars(@gethostbyaddr($ipadr)) . ')';
		}
		
		if( $q == 'user agent'
 		 || $q == $this->egalize_str(G_Local::_('match_user_agent')) )
		{
			$ret .= G_Local::_('your_user_agent') . ': ' . htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
			$ret .= '<br />' . G_Local::_('your_languages') . ': ' . htmlspecialchars($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		}
		
		if( $q == 'do not track' ) // do not match upon 'dnt' - this has too many other meanings
		{
			$ret .= G_Local::_('your_dnt_settings') . ': ';
			if( !isset($_SERVER['HTTP_DNT']) )	{ $ret .= G_Local::_('dnt_unset');	}
			else if( $_SERVER['HTTP_DNT'] )		{ $ret .= G_Local::_('dnt_on');		}
			else								{ $ret .= G_Local::_('dnt_off');		}
		}

		
		if( $q == 'http header' || $q == 'http headers' )
		{	
			$ret .= G_Local::_('your_http_header') . ':<br />';
				$ret .= $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' ' . $_SERVER['SERVER_PROTOCOL'];
				foreach( $_SERVER as $key => $value )
				{
					if( substr($key, 0, 5) == 'HTTP_' )
					{
						// convert HTTP_<UPPER CASE NAME> to a (probable) valid HTTP-header field (capitalized, words divided with -)
						$key = strtr($key, array('HTTP_'=>'', '_'=>' '));
						$key = ucwords(strtolower($key));
						$key = str_replace(' ', '-', $key);
						if( $key == 'Dnt' ) $key = 'DNT';
						
						// line out
						$ret .= '<br />' . htmlspecialchars($key) . ': ' .  htmlspecialchars($value);
					}
				}
		}
		
		return $ret;
	}
};
