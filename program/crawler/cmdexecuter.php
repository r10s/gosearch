<?php
/*******************************************************************************
Go Search Engine - Handle crawling commands
****************************************************************************//**

@author Björn Petersen

*******************************************************************************/


class Crawler_CmdExecuter
{
	private $crawlerJustDeactived = false;
	
	function justDeactivated()
	{
		return $this->crawlerJustDeactived;
	}

	function execute($cmd)
	{
		switch( $cmd['cmd'] )
		{
			case 'addurl':
				$url = $cmd['url'];
				$db_crawler = G_Db::db('crawler');
				if( $db_crawler->readRecord('t_waiting', array('f_url'=>$url)) !== false ) {
					G_Log('crawler', 'err_urlalreadywaiting ' . $url);
				}
				else if( !$db_crawler->addRecord('t_waiting', array('f_url'=>$url, 'f_time'=>time())) ) {
					G_Log('crawler', 'err_add ' . $url . ' '. $db_crawler->getLastError());
				}
				break;
			
			case 'deleteurl':
				$url = $cmd['url'];
				$db_crawler = G_Db::db('crawler');
				$db_crawler->sql->exec("DELETE FROM t_waiting WHERE f_url=".$db_crawler->sql->quote($url).";");
				break;
			
			case 'registry':
				//$db_crawler = G_Db::db('crawler');
				//$db_crawler->addOrUpdateRecord('t_ini', array('f_key'=>$cmd['key']), array('f_value'=>$cmd['value']));
				if( $cmd['key'] == 'crawler_active' && $cmd['value'] == 0 ) {
					$this->crawlerJustDeactived = true;
				}
				break;
		}
	}
};
