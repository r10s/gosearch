<?php
/*******************************************************************************
Go Search Engine - Admin
****************************************************************************//**

Admin: Host Settings

@author Björn Petersen

*******************************************************************************/

class Action_Adminhosts extends G_HtmlAdmin
{
	/***********************************************************************//**
	Called if the user opens the page ?a=adminhosts
	***************************************************************************/
	public function handleRequest()
	{
		$this->hasAdminAccessOrDie();

		echo $this->renderHtmlStart(array('title'=>G_Local::_('admin_hosts')));

			$hosts_ob = new G_Hosts();
			$hosts = $hosts_ob->getHosts();
			
			echo G_Html::renderH1(G_Local::_cnt('admin_n_host_s', sizeof($hosts)));

			foreach( $hosts as $host )
			{
				echo G_Html::renderP(htmlspecialchars(G_String::url2human($host['url']->getAbs())));
			}
			
			echo G_Html::renderH1(G_Local::_('admin_hostself'));
			
			echo G_Html::renderP(htmlspecialchars(G_String::url2human($hosts_ob->getSelf()->getAbs())));
			
		echo $this->renderHtmlEnd();
	}
};
