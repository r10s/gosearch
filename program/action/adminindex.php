<?php
/*******************************************************************************
Go Search Engine - Admin
****************************************************************************//**

Admin: Crawler configuration and information

@author Björn Petersen

*******************************************************************************/


class Action_Adminindex extends G_HtmlAdmin
{
	/***********************************************************************//**
	Handle and show details for an URL.
	Called by handleRequest().
	***************************************************************************/
	private function handleDetails($input_without_bang, $bang)
	{
		echo $this->renderStart();
			if( $input_without_bang != '' ) {
				$ri = $this->getRoughInfo($input_without_bang);
				echo G_Html::renderP($ri['msg']);
			}
			G_Tools::forceFlush();
			echo $this->renderOverview();
		echo $this->renderEnd();
	}

	private function getRoughInfo($url)
	{
		$temp = new G_Url($url);
		$link_ext = $temp->getAbs();
		$link_int = '?a=adminindex&input='.urlencode($link_ext);

		$db_index = G_Db::db('index');

		$record_indexed = $db_index->readRecord('t_url', array('f_url'=>$link_ext));
		
		$msg = G_Local::_($record_indexed !== false? 'admin_urlstateidx' : 'admin_urlstatenotidx'); 
		
		if( !$record_indexed ) {
			$msg .= G_Local::_('admin_urlstateandha');
		}
		$msg .= '. ';
		

		return array(	'msg'			=>	$msg,
						'link_ext'		=>	$link_ext);
	}
	
	
	/***********************************************************************//**
	Handle, show and save the crawler settings.
	Called by handleRequest().
	***************************************************************************/	
	private function handleSettings()
	{
		$langobj = new G_Lang();
		$langobj->set($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		
		$cron_disabled_hint = '';
		if( defined('G_DISABLE_BROWSER_BASED_CRON') && constant('G_DISABLE_BROWSER_BASED_CRON') )
		{
			$url = G_Hosts::getSelf()->getAbs() . '?a=cron';
			$cron_disabled_hint = G_Local::_('admin_crawlercronhint', "<em>$url</em>", '<em>config.php/G_DISABLE_BROWSER_BASED_CRON</em>');
		}
		
		$settings_form = new G_Form(array(
			'action'				=> '?a=adminindex&p=settings',
			'cancel'				=> true,
			'input' 				=> array(
				'crawler_active'	=> array(
											'type'=>'checkbox', 
											'value'=>G_Registry::iniRead('crawler_active', Action_Cron::CRAWLER_ACTIVE_DEFAULT), 
											'label'=>'admin_crawleractive',
											'below'=>$cron_disabled_hint,
											),
				'crawler_lang'		=> array(
											'type'=>'text', 
											'value'=>G_Registry::iniRead('crawler_lang', ''), 
											'label'=>'languages', 
											'required'=>true, 
											'right'=>G_Local::_('eg').' '.htmlspecialchars($langobj->getForHumans()),
											), 
			)
		));

		if( $settings_form->isOk() )
		{
			$cmdstack = new Crawler_CmdStack();
			
			$reg_val = $settings_form->param['input']['crawler_active']['value']? 1 : 0;
			G_Registry::iniWrite('crawler_active',	$reg_val);
			$cmdstack->push(array('cmd'=>'registry', 'key'=>'crawler_active', 'value'=>$reg_val));
			
			if( $langobj->set($settings_form->param['input']['crawler_lang']['value']) ) {
				$reg_val = $langobj->getForHumans();
				G_Registry::iniWrite('crawler_lang', $reg_val);
				$settings_form->param['input']['crawler_lang']['value'] = $reg_val;
				//$cmdstack->push(array('cmd'=>'registry', 'key'=>'crawler_lang', 'value'=>$reg_val));
			}
			else {
				$settings_form->addError(G_Local::_('bad_value') . ': ' . $langobj->error_lang);
			}
			
			if( !$settings_form->hasErrors() ) {
				G_Html::redirect('?a=adminindex');
			}
		}	
		else if( $settings_form->shallCancel() ) 
		{
			G_Html::redirect('?a=adminindex');
		}
			
		echo $this->renderStart();
			
			echo G_Html::renderH1(G_Local::_('settings'));
			
			echo $settings_form->render();
			
		echo $this->renderEnd();
	}
	
		
	/***********************************************************************//**
	Handle the overview.
	Called by handleRequest().
	***************************************************************************/
	private function handleOverview()
	{
		if( $_GET['p'] == 'ajax' )
		{
			echo $this->renderOverviewValues();
		}
		else
		{
			echo $this->renderStart();
				
				echo G_Html::renderP(G_Local::_('admin_urladdhints'));
				G_Tools::forceFlush();
				
				echo $this->renderOverview();
				
			echo $this->renderEnd();
		}
	}
		
	
	/***********************************************************************//**
	Render the overview.
	***************************************************************************/	
	private function renderOverview()
	{
		$html = '';	
	
		$html .= G_Html::renderH1(G_Local::_('state'));
		
		// warn for some missing functionalities and uncommon settings:
		// - if HTTPS is missing, this is really bad and a show-stopper
		// - character encodings are not a big problem, UTF-8 is always available, 8-bit-encodings are normally available in masses; so there is no show-stopper here
		// - compressions may be missing, however, this only slows down crawling and is no show-stopper
		if( !G_HttpRequest::httpsAvailable() ) {
			$html .= G_Html::renderP(G_Local::_('admin_httpsnotavail'), 'class="warn"');
		}
		
		if( !G_Registry::iniRead('crawler_active', Action_Cron::CRAWLER_ACTIVE_DEFAULT) )
		{
			$html .= G_Html::renderP(G_Local::_('admin_crawlerinactive'), 'class="warn"');
		}
		
		// show overview valies
		$html .= '<div id="overviewvalues">';
			$html .= $this->renderOverviewValues();
		$html .= '</div>';
		

		
		$html .= G_Html::renderP(G_Html::renderA('?a=adminindex&p=settings', G_Local::_('settings').'...'));
		
		return $html;
	}
	private function renderOverviewValues()
	{
		$html = '';
	
		$db_index = G_Db::db('index'); 

		$html .= '<table class="grid">';

			$crawler_active = G_Registry::iniRead('crawler_active', Action_Cron::CRAWLER_ACTIVE_DEFAULT);

			$cnt_urls_in_index = 'err';
			$db_index->sql->query('SELECT COUNT(*) AS cnt FROM t_url;');
			if( $db_index->sql->next_record() ) {
				$cnt_urls_in_index = $db_index->sql->fs('cnt');
			}
			
			// URLs in index / waiting for dance
			$html .= '<tr><td>'.G_Local::_('admin_sturlsinindex').'</td><td>';
				$html .= $cnt_urls_in_index;

			$html .= '</td></tr>';

			// Domains in index
			$cnt_hosts_in_index = 0;
			$db_index->sql->query('SELECT COUNT(*) AS cnt FROM t_host;');
			if( $db_index->sql->next_record() ) {
				$cnt_hosts_in_index = $db_index->sql->fs('cnt');
			}
			$html .= '<tr><td>'.G_Local::_('admin_stdomains').'</td><td>';
				$html .= $cnt_hosts_in_index;
			$html .= '</td></tr>';
			
			// get some urls just crawled/being crawling next
			if( $crawler_active )
			{
				$title = G_Local::_('admin_sturlspleasewait');
				$html .=	'<tr title="'.$title.'"><td>'.G_Local::_('admin_stjustcrawled').'</td><td>';	
				
					// the url last crawled (error or success)
					$url = @file_get_contents(G_DATA_ROOT . '/crawler.status');
					if( $url != '' ) {
						$html .= G_String::url2human($url, 80);
					}
					else {
						$html .= '-';
					}
					
					$html .= '<br />(' . G_Local::_('admin_sturlspleasewait') . ')';
					
					// crawling would also start over by the javascript part, however,
					// this may be delayed for some minutes (we call ?a=cron only every few minutes)
					// so, *if* the user has the state open, we'll this additional method for keeping the job alive (looks also better, if it is always busy ;-)
					$html .= $this->renderCronPixel();
								
				$html .=	'</td></tr>';
			}

		$html .= '</table>';	
		return $html;
	}
	

	/***********************************************************************//**
	Add URLs
	Called by handleRequest()
	***************************************************************************/	
	function handleAdd()
	{
		$url_ob = new G_Url($_GET['input']);
		if( !$url_ob->error ) 
		{
			$url = $url_ob->getAbs();

			$cmdstack = new Crawler_CmdStack();
			$cmdstack->push(array('cmd'=>'addurl', 'url'=>$url));
			if( $cmdstack->getErr() ) 
			{
				$msg = G_Local::_('err') . ' (' . $cmdstack->getErr() . ')';
			}
			else
			{
				$msg = G_Local::_('admin_urladded') . '</p><p>' .  G_Local::_('admin_urladdanother');
			}
		}
		else
		{
			$msg = G_Local::_('err') . ' (' . $url_ob->error . ')';
		}
		
		echo $this->renderStart();
			echo G_Html::renderP($msg);
			echo $this->renderOverview();
		echo $this->renderEnd();
	}
	
		
	/***************************************************************************
	Delete URLs
	Called by handleRequest()
	***************************************************************************/
	/*
	private function handleDelete()
	{
		$delete_urls = explode(' ', $_GET['delete']);
		
		$cmdstack = new Crawler_CmdStack();
		foreach( $delete_urls as $delete_url ) 
		{
			$cmdstack->push(array('cmd'=>'deleteurl', 'url'=>$delete_url));
		}

		G_Html::redirect('?a=adminindex');
	}
	*/
	
	
	/***************************************************************************
	Rendering Tools
	***************************************************************************/	
	private function renderStart()
	{
		$this->addJs('program/action/adminindex-1.js');

		$html = $this->renderHtmlStart(array('title'=>G_Local::_('admin_index')));
			if( $_GET['p'] != 'settings' )
			{
				$html .= '<form action="" method="get">';
					$html .= '<p>';
						$html .= '<label class="h1" for="input">&gt;</label> ';
						$html .= G_Html::addInternalParam('GET'); // as there cannot be an URL in our GET-actions
						$html .= '<input type="hidden" name="a" value="adminindex" /> ';
						$html .= '<input type="text" name="input" id="input" value="'.htmlspecialchars($_GET['input']).'" size="50" /> ';
						$html .= '<input type="submit" name="test" value="'.G_Local::_('admin_test')  .'" /> '; // for HTML, the first button is always the one activated when hitting the enter-key
						$html .= '<input type="submit" name="add" value="' .G_Local::_('admin_urladd').'" /> '; 
						if( $_GET['input'] != '' ) { 
							$html .= ' &nbsp; ' . G_Html::renderA('?a=adminindex', G_Local::_('reset'), 'class="shy"');
						}
					$html .= '</p>';
				$html .= "</form>\n";
			}
		
		return $html;
	}
	
	private function renderEnd()
	{
		return $this->renderHtmlEnd();
	}
	
	
	/***********************************************************************//**
	Called if the user opens the page `?a=adminindex`
	***************************************************************************/
	public function handleRequest()
	{
		$this->hasAdminAccessOrDie();
		
		$input = $_GET['input'];
		if( $_GET['p'] == 'settings' )
		{
			// settings
			$this->handleSettings();
			return;
		}
		else if( isset($_GET['add']) && $input != '' )
		{
			// add an URL (must be after about: to avoid invalid URLs)
			$this->handleAdd();
			return;
		}	
		/*
		else if( isset($_GET['delete']) )
		{
			// delete a space-separated list of URLs (must be after about: to avoid invalid URLs)
			$this->handleDelete();
			return;
		}
		*/
		else if( $input != '' ) 
		{
			// a simple test (must be after about: to avoid invalid URLs)
			$this->handleDetails($input, '');
			return;
		}
		else
		{
			// handle default
			$this->handleOverview();
			return;
		}
	}	
};
