<?php
/*******************************************************************************
Go Search Engine - Admin
****************************************************************************//**

Admin - Misc.

@author Björn Petersen

*******************************************************************************/


class Action_Adminetc extends G_HtmlAdmin
{
	function __construct()
	{
		parent::__construct();
		$this->availCmd = array(
			'crawlerinfo' 		=> array('safe_on_GET'=>true),
			'dance' 			=> array(),
			'help' 				=> array('safe_on_GET'=>true),
			'iniread'			=> array('safe_on_GET'=>true,	'param_descr'=>'[<db>]'),
			'iniwrite' 			=> array(						'param_descr'=>'<key> <value>', 'min'=>2),
			'phpinfo'			=> array('safe_on_GET'=>true),
			'removeduplicates'	=> array(),
			'sql'				=> array(						'param_descr'=>'<db> <query>', 'min'=>2),
			'sqliteinfo'		=> array('safe_on_GET'=>true),
			'step' 				=> array(),
			'stress'			=> array('safe_on_GET'=>true),
			'strfunc'			=> array('safe_on_GET'=>true,	'param_descr'=>'<str>'),
			'urlinfo'			=> array('safe_on_GET'=>true,	'param_descr'=>'<url> [raw]', 'min'=>1),
		);
	}

	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_help($argv)
	{
		echo G_Html::renderP('<b>Available commands:</b>');
		foreach( $this->availCmd as $cmd=>$param ) {
			$html = $cmd;
			if( $param['param_descr'] ) {
				$html .= ' ' .  htmlspecialchars($param['param_descr']);
			}
			echo G_Html::renderP($html);
		}
	}

	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_crawlerinfo($argv)
	{
		// http/https
		$html = 'http';
		if( G_HttpRequest::httpsAvailable() ) {
			$html .= ', https';
		}
		else {
			$html .= ' (https is not supported; this may be a problem)';
		}
		echo G_Html::renderP('Supported protocols: ' . $html);
		
		// compressions
		$html = G_HttpRequest::getAcceptableContentEncodings();
		if( $html == '' ) {
			$html = 'none';
		}
		else {
			$html = str_replace(',', ', ', $html);
		}
		echo G_Html::renderP('Supported compressions: ' . $html);
		
		// encodings
		$enc = G_Encoding::getEncodings();
		$html = htmlspecialchars(implode(', ', $enc));
		echo G_Html::renderP('Supported character encodings: ' . $html);
	}

	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_phpinfo($argv)
	{
		ob_start();
			phpinfo();
			$pinfo = ob_get_contents();
		ob_end_clean();
		$html = '<style type="text/css">
				pre {margin: 0px; font-family: monospace;}
				table {border-collapse: collapse;}
				td, th { border: 1px solid #000000; vertical-align: baseline;}
				.p {text-align: left;}
				.e {background-color: #ccccff; font-weight: bold; color: #000000;}
				.h {background-color: #9999cc; font-weight: bold; color: #000000;}
				.v {background-color: #cccccc; color: #000000;}
				.vr {background-color: #cccccc; text-align: right; color: #000000;}
				img {float: right; border: 0px;}
				hr {display:none;}
			</style>';
		$html .= preg_replace ('%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo );
		echo $html;
	}

	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_stress($argv)
	{
		echo G_Html::renderP('Executing all stress tests ...');
			error_reporting(E_ALL);
			assert_options(ASSERT_ACTIVE, 1);
			echo G_Stress::doAllTests();
		echo G_Html::renderP('done.');
	}
	
	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_sqliteinfo($argv)
	{
		// sqlite version
		$reg = G_Db::db('registry');
		$reg->sql->query("SELECT sqlite_version() AS ver;");
		$reg->sql->next_record();
		$sqlite_version = $reg->sql->fs('ver');
		echo G_Html::renderP('SQLite version: ' . $sqlite_version);
		
		// sqlite compile options
		$temp = array();
		$html = '';
		$reg->sql->query('PRAGMA compile_options;');
		while( $record=$reg->sql->next_record() ) { $temp[] = $record; }
		foreach( $temp as $record ) {
			list($key, $val) = each($record);
			$html .= ($html==''? '' : ', ') . $val;
			if( $val == 'ENABLE_FTS3' ) {
				$html .= ' (';
				
					// FTS4 is an extension to FTS3 and is _always_ available since sqlite 3.7.4 
					$html .= 'FTS4 ' . (version_compare($sqlite_version, '3.7.4',  '>=')? 'available, ' : 'not available, ');
					
					// the uncicode61 tokenizer _may_ be available since sqlite 3.7.13
					$unicode61avail = false;
					if( version_compare($sqlite_version, '3.7.13', '>=') ) {
						$old = error_reporting(0); // switch of error reporting temporary manually; this is needed because fts3_tokenizer() prints an ugly error otherwise (I do not know a better method to check if an tokenizer is available)
							$reg->sql->query("SELECT fts3_tokenizer('unicode61')");
						error_reporting($old);
						if( $reg->sql->next_record() ) {
							$unicode61avail = true;
						}
					}
					$html .= 'Unicode61 ' . ($unicode61avail? 'available' : 'not available');

					
				$html .= ')';
			}
		}
		echo G_Html::renderP('SQLite compile options: ' . $html);
		
		// functions that may be useful for asynchronous access from different threads
		$html = (function_exists('msg_send')? 'available' : 'not available');
		echo G_Html::renderP('msg_send(): ' . $html);
		
		$html = (function_exists('sem_get')? 'available' : 'not available');
		echo G_Html::renderP('sem_get(): ' . $html);
	}	
	
	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_sql($argv)
	{
		$dbname = $argv[1];
		$query  = implode(' ', array_splice($argv, 2));
		if( $dbname != 'index' && $dbname != 'crawler' && $dbname != 'maps' && $dbname != 'registry' ) {
			echo G_Html::renderP('Bad database name.', 'class="err"'); return; // error
		}
		
		$db = G_Db::db($dbname);
		$db->sql->query($query);
		$rows_out = 0;
		$head_out = false;
		$maxlen = 64;
		while( ($record=$db->sql->next_record()) )
		{
			if( !$head_out ) {
				echo '<table class="grid"><tr>';
					foreach( $record as $key=>$value ) {
						echo '<td><b>'.htmlspecialchars($key).'</b></td>';
					}
				echo '</tr>';
				$head_out = true;
			}
			
			echo '<tr>';
				foreach( $record as $key=>$value ) {
					if( strlen($value) > $maxlen ) {
						$shortened = substr($value, 0, $maxlen); // UTF-8 may be out of order here
						echo '<td>' . htmlspecialchars($shortened) . '<span title="'.htmlspecialchars($value).'">[...]</span></td>';
					}
					else {
						echo '<td>'.htmlspecialchars($value).'</td>';
					}
					
				}
			echo '</tr>';
			
			if( ($rows_out%100)== 0 ) { G_Tools::forceFlush(); }
			
			$rows_out++;
		}
	
		if( $head_out ) {
			echo '</table>';
		}
		
		if( $rows_out > 0)
		{
			echo G_Html::renderP($rows_out . ' row(s) found.');
		}
		else if( ($affected_rows=$db->sql->affected_rows()) > 0 ) 
		{
			echo G_Html::renderP($affected_rows . ' row(s) affected.');;
		}
		else 
		{
			echo G_Html::renderP('0 row(s) found or affected.');
		}		
	}

	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_iniread($argv)
	{
		$dbname = sizeof($argv)>=2? $argv[1] : 'registry';
		$this->cmd_sql(array('', $dbname, 'SELECT * FROM t_ini ORDER BY f_key;'));
	}
	
	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_iniwrite($argv)
	{
		$key = $argv[1];
		$value = implode(' ', array_splice($argv, 2));
		G_Registry::iniWrite($key, $value);
		echo G_Html::renderP('done.');
	}

	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_strfunc($argv)	
	{
		$input = implode(' ', array_splice($argv, 1));
		
		echo '<pre>';
			$x = G_TokenizeHelper::searchify($input);
			echo "G_TokenizeHelper::searchify():  " . htmlspecialchars($x) . "\n";
			
			$x = G_TokenizeHelper::asciify($input);
			echo "G_TokenizeHelper::asciify():    " . htmlspecialchars($x) . "\n";
			
			$x = G_TokenizeHelper::unasciify($x);
			echo "G_TokenizeHelper::unasciify():  " . htmlspecialchars($x) . "\n";
			
			echo "\n";

			$x = G_String::normalize($input);
			echo "G_String::normalize()           " . htmlspecialchars($x) . "\n";
			
			$x = G_String::vocabularyHash($input);
			echo "G_String::vocabularyHash():     " . htmlspecialchars($x) . "\n";
		echo '</pre>';
	}
	
	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_removeduplicates($argv)
	{
		$hashes = array();
		$db_index = G_Db::db('index');
		$db_index->sql->query("SELECT f_id, f_url, f_body FROM t_url,t_fulltext WHERE f_id=docid;");
		while( $db_index->sql->next_record() )
		{
			$url = $db_index->sql->fs('f_url');
			$id = $db_index->sql->fs('f_id');
			$body = $db_index->sql->fs('f_body');
			$hash = G_String::vocabularyHash($body);
			
			//echo "$id := $hash<br />";
			
			$hashes[ $hash ][] = array('url'=>$url, 'id'=>$id);
		}
		
		$duplicate_ids = array();
		foreach( $hashes as $hash=>$urls )
		{
			if( sizeof($urls) > 1 ) {
				for( $i = 0; $i < sizeof($urls); $i++  ) {
					$url = htmlspecialchars($urls[$i]['url']);
					$id = $urls[$i]['id'];
					echo '<a href="'.$url.'" target="_blank">' . $url .' ('. $id . ')</a> ';
					if( $i > 0 ) {
						$duplicate_ids[] = $id;
					}
				}
				echo '<br /><br />';
			}
		}
		
		echo sizeof($duplicate_ids) . " duplicates found.<br />";
		
		if( sizeof($duplicate_ids) ) {
			$db_index->sql->exec("DELETE FROM t_url WHERE f_id IN(".implode(', ',$duplicate_ids).")");
			$db_index->sql->exec("DELETE FROM t_fulltext WHERE docid IN(".implode(', ',$duplicate_ids).")");
			
			echo sizeof($duplicate_ids) . " duplicates deleted.";
		}
			
	}

	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_dance($argv)
	{
		// lock database
		if( g_is_db_locked() || !g_lock_db(time()+15) ) {	
			echo $this->renderLockError();
			return;
		}

		// dance data
		$crawler = new Crawler_Base();
		$di = $crawler->dance();
		
		$html = '<pre>';
			$html .= htmlspecialchars(print_r($di, true));
		$html .= '</pre>';
		echo $html;
		
		// unlock database
		g_unlock_db();		
	}	
	
	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_step($argv)
	{
		$this->cmd_urlinfo($argv);
	}	
	
	
	/***********************************************************************//**
	Execute the given command.  No return value, if HTML should be written, 
	it should be just echo'd. 
	***************************************************************************/
	private function cmd_urlinfo($argv)
	{
		$input_without_bang = $argv[1];
	
		$html = '';
		
		// load page info
		if( $argv[0] == 'step' ) 
		{
			if( g_is_db_locked() || !g_lock_db(time()+15) ) {	
				echo $this->renderLockError();
				return;
			}
			$crawler = new Crawler_Base();
			$pi = $crawler->step();
			g_unlock_db();
			
			if( $pi['err'] ) {
				$html .= G_Html::renderP('Errors on crawling the following URL:');
			}
			else {
				$html .= G_Html::renderP('The following URL was crawled and is waiting to be copied to the index now:');
			}
		}
		else if( $argv[2] == 'raw' )
		{
			$http_req = new G_HttpRequest();
			$http_req->setUserAgent(G_USER_AGENT);
			$gurl = new G_Url($input_without_bang);
			$http_req->setUrl($gurl); 
			$langobj = new G_Lang(G_Registry::iniRead('crawler_lang', '')); $http_req->setLanguage($langobj->getAsAcceptLanguage());
			$pi = $http_req->sendRequest();
		}
		else
		{
			$crawler = new Crawler_Base();
			$gurl = new G_Url($input_without_bang);
			$pi = $crawler->crawlUrl($gurl);
		}

		$html .= '<table class="grid">';

			// now, echo all stuff up to here (the following stuff reads information via http which is way slower)
			echo $html;
			G_Tools::forceFlush();
			$html = '';
			
		
			// common
			if( $pi['url'] || $pi['redirects'] ) {
				$html .= '<tr><td><b>URL</b></td><td>';
					$allurls = '';
					if( is_array($pi['redirects']) && sizeof($pi['redirects']) ) {
						foreach( $pi['redirects'] as $r_url ) {
							$allurls[] = $r_url;
							$html .= G_Html::renderA('?a=adminetc&cmd='.urlencode("urlinfo $r_url raw"), htmlspecialchars($r_url)).' redirects to:<br />';
						}
					}
					$r_url = $pi['url']->getAbs();
					$allurls[] = $r_url;
					$html .= 	G_Html::renderA('?a=adminetc&cmd='.urlencode("urlinfo $r_url raw"), htmlspecialchars($r_url)) 
						. ' ' .	G_Html::renderA($r_url, G_Html::ARR_VIEW, 'target="_blank"');
				$html .= '</td></tr>';
			}

			if( $pi['err'] ) {
				$html .= '<tr>';
					$html .= '<td nowrap><b style="color:#F00;">Error</b></td>';
					$html .= '<td><span style="color:#F00;">';
						$html .= htmlspecialchars($pi['err']);
						if( $pi['err_obj'] ) {
							$html .= ' (' . htmlspecialchars($pi['err_obj']) . ')';
						}
					$html .= '</span></td>';
				$html .= '</tr>';
			}
			
			// titel
			if( $pi['title'] != '' ) {
				$html .= '<tr><td><b>Title</b></td><td>';
					$html .= htmlspecialchars($pi['title']);
				$html .= '</td></tr>';
			}

			// links
			if( is_array($pi['links']) || is_array($pi['discarded_links']) || $pi['nofollow']) {
				$html .= '<tr><td><b>Links</b></td><td>';
					if( $pi['nofollow'] )
					{
						$html .= '<em>Nofollow-page: Links not collected.</em> ';
						if( is_array($pi['links']) && sizeof($pi['links']) ) { $html.= '<b>CRITICAL: Why are there links set?</b> '; }
						if( is_array($pi['discarded_links']) && sizeof($pi['discarded_links']) ) { $html.= '<b>CRITICAL: Why are discarded links set?</b> '; }
					}
					else
					{
						$html .= '<div class="scroll" style="width:32em; float:left;">';
							$html .= sizeof($pi['links']) . ' links:';
							if( is_array($pi['links']) ) {
								$i = 0;
								foreach( $pi['links'] as $url_ob ) {
									$url  = $url_ob->getAbs();
									$html .= '<br />' . ($i+1) . '. ' . $this->renderA('?a=adminetc&cmd='.urlencode("urlinfo $url"), htmlspecialchars($url));
									$i++;
								}
							}
						$html .= '</div>';
						$html .= '<div class="scroll" style="width:32em; float:left;">';
							$html .= sizeof($pi['discarded_links']) . ' discarded links:';
							if( is_array($pi['discarded_links']) ) {
								$i = 0;
								foreach( $pi['discarded_links'] as $link => $err ) {
									$html .= '<br /><del>' . ($i+1) . '.&nbsp;' . htmlspecialchars($link) . '</del> ('.htmlspecialchars($err).')';
									$i++;
								}
							}
						$html .= '</div>';
					}
				$html .= '</td></tr>';
			}
			
			// fulltext
			if( $pi['body'] != '' || $pi['discarded_text'] != '' || $pi['noindex'] ) {
				$html .= '<tr><td><b>Full text</b></td><td>';
					if( $pi['noindex'] )
					{
						$html .= '<em>Noindex-page: Text not collected.</em> ';
						if( $pi['body'] != '' || $pi['discarded_text'] != '' ) { $html.= '<b>CRITICAL: Why is the text set anyway?</b> '; }
					}
					else
					{
						$html .= '<div class="scroll" style="width:32em; float:left;">';
							$html .= G_String::bytes2human(strlen($pi['body'])) . ':<br />';
							$html .= 'Hash: '.$pi['bodyhash'].'<br />';
							$html .= htmlspecialchars($pi['body']);
						$html .= '</div>';
						$html .= '<div class="scroll" style="width:32em; float:left;">';
							if( $pi['discarded_text'] != '' ) {
								$html .= G_String::bytes2human(strlen($pi['discarded_text'])) . ' ';
								$html .= 'discarded text from link lists<br />';
								$html .= '<del>' . htmlspecialchars($pi['discarded_text']) . '</del>';
							}
						$html .= '</div>';
					}
				$html .= '</td></tr>';
			}
			
			// request
			if( $pi['request_raw'] ) {
				$html .= '<tr>';
					$html .= '<td nowrap><b>Request</b></td>';
					$html .= '<td>'.nl2br(htmlspecialchars(trim($pi['request_raw']))).'</td>';
				$html .= '</tr>';
			}

			// response
			if( $pi['header_raw'] || $pi['content'] || $pi['content_bytes'] || $pi['content_bytes_compressed'] || $pi['truncated'] )  {
				$html .= '<tr><td><b>Response</b></td><td>';
					$html .= 'Header ' . G_String::bytes2human(strlen($pi['header_raw'])) . ', content '. G_String::bytes2human($pi['content_bytes']);
						if( $pi['content_bytes_compressed'] ) {
							$html .= ' (' . G_String::bytes2human($pi['content_bytes_compressed']) . ' compressed)';
						}
						if( $pi['truncated'] ) {
							$html .= ' <span style="color:red;">(truncated)</span>';
						}
					if( $pi['header_raw'] || $pi['content'] ) {
						$content_utf8 = $pi['content'];
						G_Encoding::toUtf8($pi['charset'], $content_utf8); // $pi['content'] is not converted by default as it is not needed (only parts are internally converted)
						$html .=	':'
							.	'<div class="scroll"><pre>'
							.		htmlspecialchars($pi['header_raw']) . "\n\n" . htmlspecialchars($content_utf8)
							.	'</pre></div>';
					}
				$html .= '</td></tr>';
			}

			// character set
			if( $pi['charset'] ) {
				$html .= '<tr>';
					$html .= '<td><b>Encoding</b></td>';
					$html .= '<td>' . htmlspecialchars($pi['charset']) . '</td>';
				$html .= '</tr>';
			}
			
			// language
			if( $pi['lang'] ) {
				$html .= '<tr>';
					$html .= '<td><b>Language</b></td>';
					$html .= '<td>';
						$html .= htmlspecialchars($pi['lang']);
						if( $pi['lang_guessed'] ) {
							$html .= ' (guessed)';
						}
					$html .= '</td>';
				$html .= '</tr>';
			}
			
			// benchmarks
			if( $pi['benchmark'] ) {
				$html .= '<tr><td><b>Benchmark</b></td><td>';
					$temp = '';
					foreach(  $pi['benchmark'] as $key => $obj ) {
						$temp .= $temp? ', ' : '';
						$temp .= sprintf("$key: %1.3f s", $obj->get());
					}
				$html .= $temp . '</td></tr>';
			}
	
		$html .= '</table>' . "\n";
		
		$html .= G_Html::renderTopLink();
		
		echo $html;
	}	
	
	
	/***********************************************************************//**
	Render an error message
	***************************************************************************/
	private function renderLockError()
	{
		$cron = new Action_Cron();
		
		$html = '';
		
		$html .= G_Html::renderP('<b>The crawler is still running in another thread.</b><br />
				Please disable the automatic crawling to step manually from here.<br />
				If you have already disabled the automatic crawling, please 
				wait at about '.($cron->getMaxExecutionSeconds()+Action_Cron::RESTART_AFTER_SECONDS).'
				seconds until the remote task terminates itself.' /*n/t*/);
				
		return $html;
	}

	
	/***********************************************************************//**
	Called, if the user opens the page `?a=adminetc`
	***************************************************************************/
	public function handleRequest()
	{
		$this->hasAdminAccessOrDie();

		// check the entered command (if any)
		// ---------------------------------------------------------------------

		$do_execute_cmd = false;
		$cmd_exists  = false;
		$cmd_error = '';
		$can_destructive = false;
		if( isset($_POST['cmd']) ) 
		{
			$cmd_str = trim($_POST['cmd']);
			$do_execute_cmd = true;
			$can_destructive = true;
		}
		else if( isset($_GET['cmd']) ) // we allow passing the command by GET, however, we only allow read-only access then
		{
			$cmd_str = trim($_GET['cmd']); 
			$do_execute_cmd = true;
		}
		else 
		{
			$cmd_str = trim($_POST['cmd_in_form']);
			$can_destructive = true;
		}
			
		while( strpos($cmd_str, '  ')!==false ) { $cmd_str = str_replace('  ', ' ', $cmd_str); }
		$cmd_argv = explode(' ', $cmd_str);
		if( sizeof($cmd_argv)>=1 && isset($this->availCmd[$cmd_argv[0]]) ) {
			$cmd_exists = true;
		}

		if( $cmd_exists ) {
			if( isset($this->availCmd[$cmd_argv[0]]['min']) ) {
				$soll = $this->availCmd[$cmd_argv[0]]['min'] + 1;
				if( sizeof($cmd_argv) < $soll ) {
					$cmd_error = 'Too few arguments.';
				}
			}
			
			if( !$can_destructive && !$this->availCmd[$cmd_argv[0]]['safe_on_GET'] ) {
				$cmd_error = 'For security reasons, the given command is not valid inside a simple link.';
				$cmd_str = '';
				$cmd_array = array();
			}
		}
		else {
			$cmd_error = 'Unknown command <i>' . htmlspecialchars($cmd_str) . '</i> - type <i>help</i> for a list of available commands.';
		}
				
		// prepare form
		// ---------------------------------------------------------------------
		
		if( !$do_execute_cmd )
		{

			$di = G_DIR::recursiveInfo(G_DATA_ROOT);
			$size_val =  G_String::bytes2human($di['bytes']) . ' ' . G_Local::_('a_in_b') . ' ' . G_DATA_ROOT;

			// stress tests
			$execute_stress_tests = false;
			if( defined('G_DEBUG') && constant('G_DEBUG') ) {
				$dbg_val = G_Local::_('switch_on') . ' (executing all stress tests below)';
				$execute_stress_tests = true;
			}
			else {
				$dbg_val = G_Local::_('switch_off');
			}		
				
			$reg = G_Db::db('registry');
			$form = new G_Form(array(
				'action'			=> '?a=adminetc',
				'ok'				=> 'button_apply', // by convention: OK=save and close dialog; Apply=save, but stay in dialog
				'input' 			=> array(
					'imprint'		=> array('type'=>'textarea', 'value'=>$reg->readValue('t_text', array('f_key'=>'imprint'), 'f_text'), 'label'=>'imprint'),
					'size'			=> array('type'=>'readonly', 'value'=>$size_val, 'label'=>'data'),
					'debug'			=> array('type'=>'readonly', 'value'=>$dbg_val, 'label'=>'Debug'),
					'cmd_in_form'	=> array('type'=>'text', 	 'value'=>$cmd_str, 'label'=>'input_cmd'),
				)
			));
			
			if( $form->isOk() ) {
				// save: a "settings saved" message may be confusing, as the message stays on the screen even on subsequent (then, unsaved) edits.
				// so, we just rely on slow internet connections, so that the user really will get the information sth. was submitted to the server ;-)			
				$imprint_text = trim($form->param['input']['imprint']['value']);
				$reg->addOrUpdateRecord('t_text', array('f_key'=>'imprint'), array('f_text'=>$imprint_text));
				G_Registry::iniWrite('has_imprint', $imprint_text==''? 0 : 1);

				// advanced command entered? 
				if( $cmd_exists ) {
					$do_execute_cmd = true;
				}
				else if( $cmd_str != '' ) {
					$form->addError($cmd_error);
				}
			}
		}
	
		
		// render
		// ---------------------------------------------------------------------

		echo $this->renderHtmlStart(array('title'=>G_Local::_('admin_etc'))); 
			if( $do_execute_cmd )
			{
				// render/execute a command - do not use $_GET here - this may be dangerous for simple links!
				$html = '<form method="post" action="'.G_Html::renderUrl('?a=adminetc').'">';
					$html .= '<p>';
						$html .= '<label for="cmd">' . G_Local::_('input_cmd') . '</label> &nbsp; ';
						$html .= '<input type="text" name="cmd" id="cmd" value="'.htmlspecialchars($cmd_str).'" style="width:50%;" /> ';
						$html .= '<input type="submit" value="'.G_Local::_('button_execute')  .'" /> '; // for HTML, the first button is always the one activated when hitting the enter-key
						
					$html .= '</p>';
				$html .= "</form>\n";
				echo $html;
				
				if( $cmd_error === '' ) {
					G_Tools::forceFlush();	
					set_time_limit(0);
					call_user_func(array($this, 'cmd_'.$cmd_argv[0]), $cmd_argv);
				}
				else {
					echo G_Html::renderP($cmd_error, 'class="err"');
				}
				echo  G_Html::renderP('<br />' . G_Html::renderA('?a=adminetc', G_Local::_('button_cancel')));
			}
			else
			{
				// render the form
				echo G_Html::renderH1(G_Local::_('settings'));
				echo $form->render();		
				G_Tools::forceFlush();
				
				if( $execute_stress_tests )
				{
					G_Stress::doAllTests();
				}
			}
		echo $this->renderHtmlEnd();
	}	
};
