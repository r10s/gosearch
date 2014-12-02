<?php 
/*******************************************************************************
Go Search Engine - Session handling
****************************************************************************//**

Session Handling: We start a session only when it is really needed - this is 
true after a user log in.  For smart checking session vars, always use 
G_Session::get() - this function does not automatically start a session!

After the user logs out, with G_SESSINO::destroy() the complete session incl. 
the session cookie can be destroyed!
 
@author Björn Petersen

*******************************************************************************/


class G_Session
{
	static private $started = false;

	/***********************************************************************//**
	Get a values from a session - if there is no session, this do _not_ start 
	one.
	***************************************************************************/
	static function get($key)
	{
		if( isset($_COOKIE[ G_Session::cookieName() ])  
		 || G_Session::$started ) // we must also check G_Session::$started, as for initializing a session, the cookie is not present on the first call of session_start()
		{
			G_Session::start();
			return $_SESSION[ $key ];
		}
		return false;
	}
	
	
	/***********************************************************************//**
	Set a value defined by a key in a session. If the session is not yet stated,
	it is started now.
	***************************************************************************/	
	static function set($key, $val)
	{
		G_Session::start();
		$_SESSION[$key] = $val;
	}
	

	/***********************************************************************//**
	Start a session, if not yet done.
	***************************************************************************/	
	static private function start()
	{
		if( !G_Session::$started )
		{
			G_Session::$started = true;
			@ini_set('session.use_cookies', 1);
			session_name( G_Session::cookieName() );
			session_set_cookie_params(0,/*lifetime*/  ''/*path - only current dir*/ );
			session_start();
		}
	}
	

	/***********************************************************************//**
	Destroy a session and all pending data
	***************************************************************************/
	static function destroy()
	{
		// 1: remove important session data implicit
		$_SESSION['username'] = '';
		$_SESSION['user_logged_in'] = false;
		$_SESSION['user_is_admin'] = false;
		
		// 2: destroy the session internally
		session_destroy();	
		
		// 3: remove session cookie
		setcookie(G_Session::cookieName(), '',/*value*/  0,/*lifetime*/  ''/*path - must be same as given to session_set_cookie_params()*/ ); 
	}
	

	/***********************************************************************//**
	Returns the name of the session cookie.
	***************************************************************************/
	static function cookieName()
	{
		return G_PROGRAM_ID . '_session';
	}
};

