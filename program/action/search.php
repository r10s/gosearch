<?php
/*******************************************************************************
Go Search Engine - Websearch
****************************************************************************//**

The Websearch itself

@author Björn Petersen

*******************************************************************************/

class Action_Search extends G_Html
{
	const SECONDS_TO_WAIT_FIRST = 2.5;
	const SECONDS_TO_WAIT_THEN = 2.5;

	/***********************************************************************//**
	Rough and fast (!) Zeroclick checks.
	The zeroclick-render()-routines can decide in detail not to handle the 
	query by just returning an empty string.
	
	@param string $q	The query as entered by the user, not normalized 
						(otherwise stuff as 2+3 won't work as many 
						characters would be removed)
						
	@return 			If the query can be handled by a zeroclick, the 
						function returns a complete HTML-block-snippet ready 
						for output.  If the query cannot be handled (this is 
						the normal case), the function returns an empty string.
	***************************************************************************/
	private function renderZeroclick($q)
	{
		$ob = false;
		$q_lower = strtolower($q);
		$q_firstword = $q_lower; if(($p=strpos($q_firstword, ' '))!==false ) {$q_firstword = substr($q_firstword, 0, $p);}

		if( strpos($q, '!')!==false ) 
		{
			$ob = new Zeroclick_Bang_Renderer($q);
		}
		else if( ($q[0]>='0' && $q[0]<='9') || $q[0]=='(' || $q[0]=='-' ) // does the string begin with 0-9(- ?
		{
			$ob = new Zeroclick_Calc_Renderer($q); 
		}
		else if( substr($q_lower, 0, 2) == 'ip'		// ip address etc.
			  || substr($q_lower, 0, 4) == 'user'	// user agent etc.
			  || substr($q_lower, 0, 2) == 'do'		// do not track, do-not-track
			  || substr($q_lower, 0, 4) == 'http'  	// http headers etc.
			  )
		{
			$ob = new Zeroclick_Headers_Renderer($q);		
		}
		else if( $q_firstword=='guid' 
			  || $q_firstword=='rot13' || $q_firstword=='reverse' || $q_firstword=='soundex' || $q_firstword=='metaphone' 
			  || $q_firstword=='urlencode'
			  || $q_firstword=='md5' || $q_firstword=='sha1' || $q_firstword=='sha-1'
			  || $q_firstword=='time' || $q_firstword=='unix' || $q_firstword=='server' // time|unix time|server time
			  )
		{
			$ob = new Zeroclick_Calc_Renderer($q); 
		}
		else if( $q_lower == 'lorem ipsum' ) 
		{
			$ob = new Zeroclick_LoremIpsum_Renderer($q); 
		}

		return $ob? $ob->renderBoxAndContent() : '';
	}
	
	/***********************************************************************//**
	Render the search formular
	***************************************************************************/
	protected function renderSearchForm($param)
	{
		$ret = ''; 
		
		$ret .= '<div id="search">';
		
			if( $param['bodyclass'] == 'space' )
			{
				// many space around search
				
				$ret .= G_Html::renderH1('<span>' . G_PROGRAM_NAME . ' '. G_Local::_($param['h1']) . '</span>'); // <span>..</span> may help to place a logo in the css
			}
			
			$ret .= "<form action=\"\" method=\"get\">";
				$ret .= G_Html::addInternalParam('GET'); // as there cannot be an URL in our GET-actions
				
				if( $_GET['a']!='' ) {
					$ret .= "<input type=\"hidden\" name=\"a\" value=\"".$_GET['a']."\" />";
				}
				
				//$text_class = $_GET['q']!=''? '' : ' class="setfocus"'; // set the focus to the input field _only_ if there is no query (otherwise, key up/down are better used for scrolling)
				$ret .= "<input type=\"text\" name=\"q\" value=\"".htmlspecialchars($_GET['q'])."\"" /*. $text_class*/ . " /> ";
				
				$ret .= "<input type=\"submit\" value=\"".G_Local::_($param['button_title'])."\" /> "; // do not add a name attribute to the submit button - this would destroy our fine urls!
				
				if( $param['leftofreset'] ) {
					$ret .= $param['leftofreset'];
				}
				
				if( $param['bodyclass'] != 'space' ) // this will give the maps always the needed reset-button (maps may be changed by typing or by moving the map around with the mouse)
				{
					$reset_url  = $_GET['a']==''?  '?q=' : '?a='.$_GET['a'].'&q=';
					$ret .= ' &nbsp; ' . G_Html::renderA($reset_url, G_Local::_('reset'), 'class="shy"');
				}
				
			$ret .= "</form>";
			
			if( $param['bodyclass'] == 'space' )
			{
				$ret .= '<p class="slogan">' . 'Free, open, non-tracking, ad-free and slow.' . '</p>'; // other adjectives: free, open, private, non-tracking, ad-free ... and slow
			}
			
		$ret .= "</div>\n";
		
		return $ret;
	}

	private function renderResult($title, $url, $snippet, $q)
	{	
		$ret = '<div class="sr">';
			$ret .= '<div class="tt"><a href="'.htmlspecialchars($url).'">'.G_String::hilite($title, $q).'</a></div>';
			$ret .= '<div class="url">' . G_String::hilite(G_String::url2human($url), $q) . '</div>';
			$ret .= '<div class="spt">' . $snippet . '</div>';
		$ret .= "</div>\n";	
		return $ret;		
	}
	
	public function handleRequest()
	{
		$this->hasSearchAccessOrDie();

		$q = trim($_GET['q']);
		$title = $q==''? G_Local::_('menu_websearch') : htmlspecialchars($q);
		$bodyclass = $q==''? 'space' : '';


		
		// Let the browser cache SERP pages for some hours - they are hard enough to get.
		// However, we do this not only for performance on the server (in fact, we can add a cache there), but also as if the
		// user uses the history-back-key, nothing will be faster but a browser-cached apge.
		if( $q != '' ) {
			$zeroclick = $this->renderZeroclick($q); // call zeroclick before any header is printed - zeroclick may result in an redirect (eg. for bangs)
			$this->forceCaching();
		}

		// start HTML
		
		echo $this->renderHtmlStart(array('title'=>$title, 'bodyclass'=>$bodyclass));
		$htmlend_param = array();
		
			// render the search formular, flush page up to here
			echo $this->renderSearchForm(array('h1'=>'menu_websearch', 'button_title'=>'button_search', 'bodyclass'=>$bodyclass));
			
			// do the search ...
			if( $q != '' )
			{
				// check for zeroclick
				echo $zeroclick;

				// render search progress bar and let the Browser load the JavaScripts 
				// (this is a good moment as we have to wait for the search results)
				// (the gif animation does not restart if in cache ... see https://bugzilla.mozilla.org/show_bug.cgi?id=129986 ... but the # restarts it without reloading ...)
				echo "<script>document.write('<div id=\"loading\"><img src=\"program/layout/ld-bar.gif#rnd=".time()."\" alt=\"\" width=\"128\" height=\"20\" /></div>');</script>\n";
				G_Tools::forceFlush(); // first flush to show the progress bar as soon as possible
				echo $this->renderJs();
				G_Tools::forceFlush(); // second flush to let the browser execute the scripts while we're broadcast the search request
				
				// send remote results request
				$ipc_ob = new G_IPC();
				if( !$ipc_ob->ok() ) {
					G_Log::log($ipc_ob->getErr());
					die('IPC error (see log for details)');
				}
				
				$hosts_ob = new G_Hosts();
				$hosts = $hosts_ob->getHosts();

				G_Log::log('p2p', "ok\trequesting results for '".$q."' from " . sizeof($hosts). " hosts");
				
				$request_param = 'a=p2p&p=lookup&id='.$ipc_ob->getId().'&ret='.urlencode($hosts_ob->getSelf()->getAbs()).'&q='.urlencode($q);
				$request_ob = new G_HttpRequest();
				$request_ob->setUserAgent('G_USER_AGENT');
				$request_ob->setTimeout(5); // a very short timeout, the user won't wait long!
				$request_ob->setSendOnly(true); // do not wait for an answer!
				
				$seconds_waited = microtime(true);
				
				$requests_sended = 0;
				foreach( $hosts as $host )
				{
					$request_url = clone $host['url'];
					$request_url->setParam($request_param);
					$request_ob->setUrl($request_url);
					$request_ob->sendRequest();
					//echo $request_url->getAbs();
					
					$requests_sended++;
				}
				
				// get local results
				$results = array();
				$lookup_obj = new G_Lookup();
				$temp = $lookup_obj->lookup($q);
				if( $temp['err'] ) {
					echo G_Html::renderP($result['err'] . ' ('.htmlspecialchars($result['err_obj']).')', 'class="err"');
					echo $this->renderHtmlEnd($htmlend_param);
					return; // syntax error or sth. like that ("no results" is no error)
				}
				else {
					$temp['from'] = 'local';
					$htmlend_param['footer'] .= G_String::seconds2human($temp['seconds']);
					$results[] = $temp;
				}

				// wait a little moment for the remote results
				if( $requests_sended > 0 )
				{
					$seconds_waited = microtime(true) - $seconds_waited;
					$seconds_left_to_wait = Action_Search::SECONDS_TO_WAIT_FIRST - $seconds_waited;
					if( $seconds_left_to_wait > 0 ) {
						usleep($seconds_left_to_wait * 1000000);
					}
					
					$temp = $ipc_ob->rcvMessages();
					if( $temp === false ) {	
						// error - do not continue the search, however, display the local results, if any
						echo G_Html::renderP($ipc_ob->getErr(), 'class="err"');
					}
					else {
						$results = array_merge($results, $temp);
						if( $this->doWaitLonger($requests_sended, $temp) ) {
							usleep(Action_Search::SECONDS_TO_WAIT_THEN * 1000000);
							$temp = $ipc_ob->rcvMessages();
							if( $temp !== false ) {
								$results = array_merge($results, $temp);
							}
						}
					}
				}
				
				// remove the progress bar - if we want to get rid of jQuery one day, we can use `document.getElementById('loading').style.display = 'none';`)
				echo "<script>$('#loading').hide();</script>\n"; 

				// combine remote and local results
				G_Log::log('p2p', "ok\tsorting all results for '".$q."'");
				
				// render the results
				foreach( $results as $result )
				{
					if( sizeof($result['result']) )
					{
						echo '<p style="background-color:yellow;"><i>from ' . htmlspecialchars($result['from']) . ':</i></p>';
						
						for( $i = 0; $i < sizeof($result['result']); $i++ )
						{
							echo $this->renderResult(
									$result['result'][$i]['title'], 
									$result['result'][$i]['url'], 
									$result['result'][$i]['snippet'],
									$q);
						}
					}
				}
			}

		echo $this->renderHtmlEnd($htmlend_param);
	}
	
	private function doWaitLonger($requests_sended_to_hosts, $result_arr)
	{	
		$requests_received_from_hosts = sizeof($result_arr);
		
		if( $requests_received_from_hosts >= $requests_received_from_hosts ) {
			return false; // do not wait longer
		}
		
		if( $requests_received_from_hosts < 1 ) {
			return true; // wait longer
		}
		
		return false; // do not wait longer
	}
	

};
