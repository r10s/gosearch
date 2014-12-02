<?php
/*******************************************************************************
Go Search Engine - HTML base class
****************************************************************************//**

G_Html should be used as a base class for any Action_* that outputs HTML. 
Session and login handling are checked here.  Moreover, G_Html defines some
static formatting routines that can be easily used from whereever.

Some parameters for index.php when generating HTML-pages:

-	a=<action>	-	invoke the given action
-	q=<words>	-	search for the given words
-	... for more, heave a look at action/README.txt

To the included files (CSS, Favicon, JavaScript):

We append a version to the included file's name; the version should increase 
whenever the file is changed. This makes sure, clients get the updates at once 
while still allowing full caching without any tricks.

@author Björn Petersen
	
*******************************************************************************/

class G_Html
{
	static $s_last_instance;
	
	const ARR_VIEW = '&#8599;';
	const LOGIN_REQUIRED_DEFAULT = 0;
	
	private $css;
	private $jsPending;
	private $forceCachingVal = false;
	
	function __construct()
	{
		// first, check if we need to perform a setup (install or update)
		$reg = G_Db::db('registry', false);
		if( $reg===false 
		 || !$reg->sql->table_exists('t_ini') 
		 || $reg->recordCnt('t_user')==0
		 || floatval(G_Registry::iniRead('g_version', 0))<G_VERSION )
		{
			Action_Setup::smartInstallOrUpdate();
		}
	
		// the js/css array should be ready before the user calls renderHtmlStart()
		$this->jsPending = array();
		$this->addJs('program/lib/jquery/jquery-1.11.0.min.js');
		$this->addJs('program/g/framework.js');
		$this->css = array();
		$this->addCss('program/layout/screen.css');
		$this->authCookieName = G_PROGRAM_ID.'_auth';

		// make sure, we enter the following initialisation only once - esp. if it has faild and we go here again by Action_Error ...
		if( is_object(G_Html::$s_last_instance) ) {
			return;
		}
		G_Html::$s_last_instance = $this;
	
		// init the session, use cookies, if possible
		if( !G_Session::get('user_logged_in') && isset($_COOKIE[$this->authCookieName]) )
		{	
			$bad_cookie = true;
			
			list($cookie_username, $cookie_auth_hash) = explode(':', $_COOKIE[$this->authCookieName]);
			if( $reg->sql->table_exists('t_user') 
			 && ($record = $reg->readRecord('t_user', array('f_username'=>$cookie_username)))!==false )
			{
				if( $record['f_authtoken']!='' && $record['f_authtoken']==$cookie_auth_hash )
				{
					$this->setSessionUser($cookie_username, $record['f_admin']);
					$bad_cookie = false;
				}
			}
			
			if( $bad_cookie )
			{
				$this->securityDelay();
			}
		}
	}
	
	
	/***********************************************************************//**
	Added JavaScripts are added to the end of the list.
	***************************************************************************/
	protected function addJs($js)
	{
		assert( !in_array($js, $this->jsPending) ); // this only detectes double files if there is no renderJs() in between, however, we do not add debug-only code
		
		$this->jsPending[] = $js;
	}


	/***********************************************************************//**
	By default, additional CSS are added to the start of the list.
	(most times, this function is used by libraries as leaflet - so we have the 
	chance to overwrite settings in the basics)
	***************************************************************************/	
	protected function addCss($css, $add_top = true)
	{
		if( $add_top ) {
			array_unshift($this->css, $css);
		}
		else {
			$this->css[] = $css;
		}
	}
	
	protected function forceCaching()
	{
		$this->forceCachingVal = true;
	}
	
	protected function setSessionUser($username, $user_is_admin)
	{
		G_Session::set('username',		 	$username);	
		G_Session::set('user_logged_in',	true);
		G_Session::set('user_is_admin',		$user_is_admin);
	}
	
	protected function removeAuthCookie()
	{
		if( isset($_COOKIE[$this->authCookieName]) ) {
			setcookie($this->authCookieName, '', 0); // remove cookie
			unset($_COOKIE[$this->authCookieName]);
		}
	}
	
	/***********************************************************************//**
	securityDelay() sleeps a little moment to slow down brute force attacks. The
	function should be called eg. on failed logins or bad auth cookies. The 
	delay has a random component to avoid attacks measuring times eg. on hash
	calculations.
	***************************************************************************/	
	protected function securityDelay()
	{
		usleep(mt_rand(500,1500) * 1000);
	}
	
	
	/***********************************************************************//**
	Check, if the user has the right to use the websearch and related stuff.
	_If_ the function returns, the user has websearch access.
	***************************************************************************/		
	protected function hasSearchAccessOrDie()  
	{
		if( !G_Session::get('user_logged_in')
		 && G_Registry::iniRead('login_required', G_Html::LOGIN_REQUIRED_DEFAULT) ) {	
			G_Html::redirect('?a=login');
		}
	}


	/***********************************************************************//**
	Check, if the user has the right to access its profile pages and related 
	stuff -	_if_ the function returns, the user is logged in.
	***************************************************************************/		
	protected function hasUserAccessOrDie()
	{
		if( !G_Session::get('user_logged_in') ) {
			G_Html::redirect('?a=login');
		}
	}

	
	/***********************************************************************//**
	Check, if the user has the right to access administration pages.
	_If_ the function returns, the user has admin access
	***************************************************************************/		
	protected function hasAdminAccessOrDie()
	{
		if( !G_Session::get('user_logged_in') ) {	
			G_Html::redirect('?a=login');
		}
		else if( !G_Session::get('user_is_admin') ) {
			$this->msgAndDie(401);
		}
	}


	static function getFavicon()
	{
		return 'program/layout/favicon.ico';
	}
	
	protected function renderHtmlStart($param)
	{
		if( !is_array($param) ) die('missing parameters array for renderHtmlStart().');
		
		$this->footer = isset($param['footer'])? $param['footer'] : true;
		
		// get a fine title
		$title = '';
		if( $param['title'] ) {
			$title = $param['title'];
		}
		$title .= $title != ''? ' - ' : '';
		$title .= G_PROGRAM_NAME;

		// set caching (currently (31.10.2013), we do not use header("cache...") as we think, PHP makes a good job here)
		if( $this->forceCachingVal )
		{
			header("Cache-Control: public");	// this is the recommended approach for caching pages ...
			header("Pragma:");					// ... however, also remove "Pragma: no-chache" header set eg. by Synology DSM 5.x
			header('Expires: ' . gmdate("D, d M Y H:i:s", time()+60*60*3) . ' GMT'); // expire in 3 hours, must be a RFC 1123 date, see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.21
		}
		
		// html start
		$ret =	"<!DOCTYPE html>\n"
			.	"<html lang=\"".G_Local::langId()."\">\n"
			.	"<head>\n"
			.		"<meta charset=\"UTF-8\" />\n" // NOT: ISO-8859-1
			.		"<meta name=\"robots\" content=\"noindex,nofollow\" />\n" // We're a search engine, do not index us (needed in addition to robots.txt as robots.txt is not read by everyone and does not work in directories)
			.		"<title>$title</title>\n";
		
		foreach( $this->css as $css ) {
			$ret .=	"<link rel=\"stylesheet\" href=\"$css\" />\n"; // CSS should be loaded first, type="text/css" is required in HTML4 but not in HTML5 as usd by us 
		}
		
		$ret .=		"<link rel=\"shortcut icon\" type=\"image/x-icon\" href=\"".G_Html::getFavicon()."\" />\n"
			.		"<link rel=\"search\" type=\"application/opensearchdescription+xml\" href=\"?a=opensearch\" title=\"" . G_PROGRAM_NAME /*=OpenSearchDescription->ShortName*/. "\" />\n"
			.	"</head>\n";
			
		$bodyclass = $param['bodyclass']? " class=\"{$param['bodyclass']}\"" : '';
		$ret .= "<body$bodyclass>\n";
		
		// main menu
		// (not sure, if the relative URL "" works everywhere as expected, so we use "?q=" instead)
		// Navigation bar how-to: http://www.w3schools.com/css/css_navbar.asp
		
		$a = strval($_GET['a']);
		$items_l[] = array('url'=>'?q='.urlencode($_GET['q']), 			'descr'=>G_PROGRAM_NAME.' '.G_Local::_('menu_websearch'),	'sel'=>$a==''); 
		//$items_l[] = array('url'=>'?a=img&q='.urlencode($_GET['q']),	'descr'=>G_Local::_('menu_imgsearch'),								'sel'=>$a=='img');
		//$items_l[] = array('url'=>'?a=maps&q='.urlencode($_GET['q']),	'descr'=>G_Local::_('menu_maps'),									'sel'=>$a=='maps');
		$items_l[] = array('url'=>'?a=help', 								'descr'=>G_Local::_('menu_help'),									'sel'=>$a=='help');
		if( G_Session::get('user_is_admin') ) {
			$items_l[] = array('url'=>'?a=admin', 							'descr'=>G_Local::_('menu_admin'),									'sel'=>substr($a,0,5)=='admin');
		}
		if( G_Session::get('user_logged_in') ) {
			$items_r[] = array('url'=>'?a=profile', 						'descr'=>G_Local::_('menu_profile'),	 								'sel'=>$a=='profile');
			$items_r[] = array('url'=>'?a=logout', 							'descr'=>'&times;', 'tooltip'=>G_Local::_('menu_logout'),			'sel'=>false);
		}
		else {
			$items_r[] = array('url'=>'?a=login', 							'descr'=>G_Local::_('menu_login'), 'sel'=>($a=='login'));
		}

		$ret .= "<div id=\"mainmenu\">"	
			.		'<ul>' . G_Html::renderItems($items_l) .  "</ul>"
			.		'<ul>' . G_Html::renderItems($items_r) .  "</ul>"
			.	"</div>\n";
		
		if( $param['submenu'] ) {
			$ret .= "<div id=\"submenu\">"	
				.		'<ul>' . G_Html::renderItems($param['submenu']) .  "</ul>"
				.	"</div>\n";
		}
		
		// content start
		$ret .= "<div id=\"content1\">\n";
		
		return $ret;
	}
	
	protected function renderHtmlEnd($param = '')
	{
		if( !is_array($param) ) $param = array();
		$ret = '';

		// content end
		$ret .= "</div>\n";	
		
		// footer
		if( $this->footer ) {
			$ret .= '<div id="footer">';
				// prefix
				if( $param['footer'] ) {
					$ret .= $param['footer'];
					$ret .= ' - ';
				}
				
				// program version etc.
				$ret .= '&copy; ' . G_Html::renderA('?a=about', G_PROGRAM_NAME . ' contributors ');
				
				if( $_GET['a']!='setup' )
				{
					if( G_Registry::iniRead('has_imprint', 0) ) {
						$ret .= ' - ' . G_Html::renderA('?a=imprint', G_Local::_('imprint'));
					}

					$ret .= $this->renderCronPixel(); // start cron from here? this is the default, however, a "real" cron may be a little bit smarter
				}
				
				// debug: show sql commands
				if( G_Registry::iniRead('show_sql', 0) )
				{
					$ret .= '<br />';
					foreach( G_SQLITE_CLASS::$benchmarks as $bm) {
						if( strlen($bm[1]) > 120) {
							$sql = '<span title="' . htmlspecialchars($bm[1]) . '">' . htmlspecialchars(substr($bm[1], 0, 115)) . '...</span>';
						}
						else {	
							$sql = htmlspecialchars($bm[1]);
						}
						$bold = ''; $boldend = ''; if( $bm[2] > 0.5 ) { $bold = '<b>'; $boldend = '</b>'; }
						$time = $bold . sprintf('%0.3f', $bm[2]) . ' s' . $boldend;
						$ret .= '<br />' . $bm[0] . ': ' . $sql;
						if( isset($bm[3]) ) {
							$ret .= ' &rarr; '.htmlspecialchars($bm[3]);
						}
						$ret .= ' in ' . $time;
					}
				}
					
				// debug: show included/required files
				if( G_Registry::iniRead('show_files', 0) ) {
					$ret .= '<br />';
					$files = get_required_files();
					foreach( $files as $f ) {
						$ret .= '<br />' . htmlspecialchars($f);
					}
				}
		
			$ret .= "</div>\n";
		}
		
		// load JavaScripts - for speed reasons, we do this at the end; this allows the browser to render the page first
		// (the caller, however, may decide to load the scripts sooner, eg. when waiting on results ...)
		$ret .= $this->renderJs();
		
		// html end
		$ret .=	"</body>\n"
			.	"</html>\n";
		
		return $ret;
	}
	

	/***********************************************************************//**
	Render all JavaScripts added up to now using addJs() (you may still add 
	JavaScripts after calling this function; they are rendered on the next call 
	to renderJs() then).
	
	If you do not call renderJs() in derived classes, it is called automatically
	one	time just before the page ends.
	***************************************************************************/
	protected function renderJs()
	{
		$ret = '';
		foreach( $this->jsPending as $js ) {
			$ret .= "<script src=\"$js\"></script>\n"; // type="text/javascript" is required in HTML4 but optional in HTML5
		}									//    ^^^ the \n is important as this allows us to flush the data and send it to the user
		$this->jsPending = array();
		return $ret;
	}
	
	
	protected function renderCronPixel()
	{
		$ret = '';
		if( !isset($this->cron_pixel_tried) ) 
		{
			$this->cron_pixel_tried = true;
			
			if( !defined('G_DISABLE_BROWSER_BASED_CRON') || !constant('G_DISABLE_BROWSER_BASED_CRON') ) 
			{
				if( !g_is_db_locked() ) 
				{
					// We use an image here as this also works without Javascript. However, subsequent refreshes will require Javascript
					$ret .= '<img src="'.htmlspecialchars('?a=cron&ret=img&rnd='.time()) /*no internal parameters, no renderUrl() */.'" width="1" height="1" alt="" />';
				}
			}
		}	
		return $ret;
	}
	

	protected function msgAndDie($msg_or_code, $add_msg = '')
	{
		$ob = new Action_Error($msg_or_code, $add_msg);
		$ob->handleRequest();
		exit();
	}
	
	
	static function renderH1($html, $attr = '')
	{
		if( $attr != '' ) $attr = ' '.trim($attr);
		return "<h1{$attr}>" . $html .  "</h1>\n";
	}
	
	
	static function renderP($html, $attr = '')
	{
		if( $attr != '' ) $attr = ' '.trim($attr);
		return "<p{$attr}>" . $html .  "</p>\n";
	}

	
	static function renderItems($items)
	{
		$ret = '';
		foreach( $items as &$item )
		{
			$class = $item['sel']? ' class="sel"' : '';
			
			$ret .= "<li{$class}>";
			
				$aattr = '';
				if( $item['tooltip'] ) { $aattr = ' title="'.$item['tooltip'].'"'; }
				if( $item['aclass'] )  { $aattr .= ' class="'.$item['aclass'].'"'; }
				
				if( isset($item['url']) ) { // the url may be "" - therefore check using isset() 
					$ret .= G_Html::renderAhref($item['url'], $aattr);
					$aend = '</a>';
				}
				else {
					$ret .= "<i class=\"nop\"{$aattr}>";
					$aend = '</i>';
				}
				
				$ret .= $item['descr'];
				$ret .= $aend;
			
			$ret .= '</li>';
		}
		return $ret;
	}
	

	static function renderTopLink()
	{
		return G_Html::renderP(G_Html::renderA('#mainmenu', '&uarr; '.G_Local::_('top_link')));
	}
	

	/***********************************************************************//**
	function adds some paramters to internal (relative) urls; external 
	(absolute) urls are unchanged
	***************************************************************************/		
	static function addInternalParam($str)
	{
		if( substr($str, 0, 1) == '?' ) {
			// add layout or other special parameters to a given URL
			if( isset($_GET['l']) )						{ $str = '?l=' . urlencode($_GET['l']) . '&' . substr($str, 1); }
			//if( G_Session::get('user_logged_in') )	{ $str = '?u&' . substr($str, 1); } // [*] needed as the loggedIn/loggedOut pages have different headers, see also remark in action/README.txt
			//if( substr($str, -4)=='&rnd' )			{ $str .= '='.rand(10000000,99999999); } // avoid links being marked as visited -- we do no longer use this - visited links are visited links and should be shown as visited links... form follows function!
		}
		else if( $str == 'GET' ) {
			// for GET forms, we need to add the same stuff as hidden fields (for POST forms, we use a std. URL in action=?...)
			$str = '';
			//if( G_Session::get('user_logged_in') )	{ $str .= '<input type="hidden" name="u" value="" />'; } // see [*] above
			if( isset($_GET['l']) ) 			{ $str .= '<input type="hidden" name="l" value="'.htmlspecialchars($_GET['l']).'" />'; }
		}
		
		return $str;
	}
		
		
	static function redirect($url)
	{
		header("Location: ".G_Html::addInternalParam($url));
		exit();
	}
	
	static function renderUrl($url /* a plain url - `&` must not be masked as `&amp;`! */)
	{
		// mask `&`
		return htmlspecialchars(G_Html::addInternalParam($url));
	}
	
	
	static function renderAhref($url /* a plain url - `&` must not be masked as `&amp;`! */ , $attr = '')
	{
		if( $attr != '' ) $attr = ' '.trim($attr);
		return "<a href=\"".G_Html::renderUrl($url)."\"{$attr}>";
	}
	
	
	static function renderA($url /* a plain url - `&` must not be masked as `&amp;`! */ , $text, $attr = '')
	{
		return G_Html::renderAhref($url, $attr) . $text . '</a>';
	}
	

};

