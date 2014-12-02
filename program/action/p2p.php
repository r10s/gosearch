<?php
/*******************************************************************************
Go Search Engine - Peer-to-peer communication
****************************************************************************//**

Receive a lookup reply from a remote host or send such a request.

Commands:

- `p=lookup&q=<query>&id=<id>&ret=<host>` - lookup for the query and return
  the result to the given host at the given id
  
- `p=result&id=<id>` - receive a result; the data themselves are included in
  the POST body.

TODO:

On heavier load, we should create a single lookup thread that handles all
lookup requests in queued order (first in, first out), requests older than a few 
seconds will be discarded.
  
@author Björn Petersen 

*******************************************************************************/

class Action_P2P // not based upon HTML!
{
	private function handleLookup()
	{
		$q = $_GET['q'];
		$id = $_GET['id']; 
		if( !$id ) {
			G_Log::log('p2p', 'err_badretid' . "\t" . $q);
			return; // error
		}

		// lookup
		$lookup_ob = new G_Lookup();
		$result_arr = $lookup_ob->lookup($q);
		if( $result_arr['err'] ) {
			G_Log::log('p2p', $result_arr['err'] . "\t" . $q);
			return; // error
		}
		else if( sizeof($result_arr['result']) == 0 ) {
			G_Log::log('p2p', "ok\tnothing found for '$q'");
			return; // ok - but nothing found
		}
		
		// send the result to the caller
		$send_seconds = microtime(true);
		
			// add some additional information just for debugging purposes
			$result_arr['q']  = $q;
			$result_arr['from']  = G_Hosts::getSelf()->getAbs();

			// prepare the URL
			$url = new G_Url($_GET['ret']);
			if( $url->error ) {
				G_Log::log('p2p', $url->error . "\t" . $q);
				return; // error
			}
			$url->setParam('a=p2p&p=result&id=' . $_GET['id']);
			
			// prepare and send the request
			$request_ob = new G_HttpRequest();
			$request_ob->setUrl($url);
			$request_ob->addPostData('result', serialize($result_arr));
			$request_ob->setUserAgent(G_USER_AGENT);
			$request_ob->setTimeout(5); // a very short timeout, the user won't wait long!
			$request_ob->setSendOnly(true); // do not wait for an answer!
			$request_ob->sendRequest();
		
		$send_seconds = microtime(true) - $send_seconds;
		
		// done, log the success proudly :-)
		G_Log::log('p2p', "ok\tsend " . sizeof($result_arr['result'])  . " results for '$q' to " . $_GET['ret'] . ' in ' . 
			sprintf('%1.3f', $result_arr['seconds']) . '+'. sprintf('%1.3f', $send_seconds) . ' s');
	}
	
	
	private function handleReceiveResult()
	{	
		// check the result - we need the inspection of the result only for logging purposes;
		// so, we can speed up the stuff by skipping the serialize(unserialize()) cascade;
		// however, in practice, probably, this won't change much.
		if( ($result_arr=unserialize($_POST['result'])) === false ) {
			G_Log::log('p2p', 'err_unserialize');
			return; // error
		}
		
		G_Log::log('p2p', "ok\treceived ".sizeof($result_arr['result'])." results for '".$result_arr['q']."' from ".$result_arr['from']);
		
		// send the result to the listening process
		$ipc_ob = new G_IPC($_GET['id']);
		if( !$ipc_ob->ok() ) {
			G_Log::log('p2p', $ipc_ob->getErr());
			return; // error
		}
		
		$ipc_ob->sendMessage($result_arr); 
	}
	
	
	function handleRequest()
	{
		switch( $_GET['p'] )
		{
			case 'lookup':	$this->handleLookup();			break;
			case 'result':	$this->handleReceiveResult();	break;
			default:		die('bad p-param.');			break;
		}
	}
};
