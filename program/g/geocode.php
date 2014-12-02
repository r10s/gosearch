<?php 
/*******************************************************************************
Go Search Engine - Maps
****************************************************************************//**

Geocode stuff, needed by Action_Maps

@author Björn Petersen

*******************************************************************************/

class G_Geocode
{
	const DEFAULT_GEOCODE_SERVER_A    = 'http://open.mapquestapi.com/nominatim/v1/search'; // http://developer.mapquest.com/web/products/open/nominatim - normal usage is okay
	const DEFAULT_GEOCODE_SERVER_B    = 'http://nominatim.openstreetmap.org/search'; 	   // http://wiki.openstreetmap.org/wiki/Nominatim_usage_policy - 1 req/s, only to user requests
	const DEFAULT_GEOCODE_ATTRIBUTION = 'Nominatim Search Courtesy of <a href="http://www.mapquest.com/">MapQuest</a>';
	
	
	function xmlElemStart($parser, $name, $attribs_uppercase)
	{
		$attribs_lowercase = array();
		foreach( $attribs_uppercase as $key=>$value ) {
			$attribs_lowercase[ $key=='LON'? 'lng' : strtolower($key) ] = $value;
		}

		if( $name == 'SEARCHRESULTS' ) {
			$this->searchresults[] = $attribs_lowercase;
		}
		else if( $name == 'PLACE' ) {
			$this->places[] = $attribs_lowercase;
		}
	}
	
	
	function xmlElemEnd($parser, $name)
	{
	}
	
	
	/***********************************************************************//**
	function returns: 
	- array('lat=>53.55, 'lng'=>10.00) on success
	- array('err'=>'short error message') on failure
	***************************************************************************/
	function geocodeDo($q)
	{
		// geocoders to try
		$nominatim_urls = array(
			defined('G_NOMINATIM_SERVER')? constant('G_NOMINATIM_SERVER') : '',
			G_Geocode::DEFAULT_GEOCODE_SERVER_A,	
			G_Geocode::DEFAULT_GEOCODE_SERVER_B,		
		);
		
		// read data
		foreach( $nominatim_urls as $nominatim_url ) {
			if( $nominatim_url ) {
				$http_request = new G_HttpRequest;
				$http_request->setLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
				$http_request->setUserAgent($_SERVER['HTTP_USER_AGENT']!=''? $_SERVER['HTTP_USER_AGENT'] : G_USER_AGENT);
				$http_request->setUrl(new G_Url($nominatim_url . '?format=xml&q=' . urlencode($q)));
				$http_request->setTimeout(5);
				$pi = $http_request->sendRequest();
				if( !$pi['err'] ) {
					break;
				}
			}
		}
		
		if( $pi['err'] ) {
			return array('err'=>'err_geocode_connect ('.$pi['err'].')'); 
		}
		
		// parse XML result
		$this->searchresults = array();
		$this->places = array();
		$parser = xml_parser_create('UTF-8');
		xml_set_object($parser, $this);
		xml_set_element_handler($parser, 'xmlElemStart', 'xmlElemEnd');
		if( !@xml_parse($parser, $pi['content'], true) ) {
			xml_parser_free($parser);
			return array('err'=>'err_geocode_xmlparse');
		}
		xml_parser_free($parser);
		
		if( sizeof($this->searchresults) != 1 ) {
			return array('err'=>'err_geocode_badxml');
		}
		
		// success - however, the result may  be empty!
		return array('searchresults'=>$this->searchresults[0], 'places'=>$this->places);
	}
};
