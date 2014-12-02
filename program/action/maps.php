<?php
/*******************************************************************************
Go Search Engine - Maps
****************************************************************************//**

Render the Maps.

TODO: a result list is missing.  If we decide to add one, a good place would 
be left of the map together with the earch input field.  The map itself would
gain some height then.

@author Björn Petersen

*******************************************************************************/

class Action_Maps extends Action_Search
{
	const DEFAULT_TILE_SERVER		= 'http://otile1.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png';
	const DEFAULT_TILE_ATTRIBUTION	= 'Tiles Courtesy of <a href="http://www.mapquest.com/">MapQuest</a>';

	public function handleRequest()
	{
		$this->hasSearchAccessOrDie();
		
		// render page start
		$q = trim($_GET['q']);
		$title = $q==''? G_Local::_('menu_maps') : htmlspecialchars($q);
		$this->addCss('program/lib/leaflet/leaflet-0.7/leaflet.css');
		$this->addJs('program/lib/leaflet/leaflet-0.7/leaflet.js');
		$this->addJs('program/action/maps-1.js');
		echo $this->renderHtmlStart(array('title'=>$title, 'footer'=>false));

		// render the search formular
		echo $this->renderSearchForm(array('h1'=>'menu_maps', 'button_title'=>'button_search'));
		G_Tools::forceFlush();

		// query the geocoder for the search string
		$gi = false;
		if( $q )
		{
			
			$cache = new G_Cache(G_Db::db('maps'), 't_geocodecache', 7*G_Cache::DAYS);
			if( ($temp=$cache->lookup($q))!==false ) {
				$gi = unserialize($temp);
			}
			else {
				$geocoder = new G_Geocode;
				$gi = $geocoder->geocodeDo($q);
				$cache->insert($q, serialize($gi));
			}
		}
		
		// render the map - param[] will go to the div and read by javascript - 
		// this method allows the browser to start the JavaScript enginge really after page load (the alternative would be to use <script>var ...</script>)
		$param = array();
		
		// find out the tile provider to use
		$server = defined('G_TILE_SERVER')? constant('G_TILE_SERVER') : '';
		if( $server == '' )  { $server = Action_Maps::DEFAULT_TILE_SERVER; }
		$param['tile'] = $server;
		
		// set the about URL (there may be additional parameters set by renderUrl())
		$param['about'] = '&copy; ' . $this->renderA('?a=about#osm', 'OpenStreetMap contributors');
		if( G_Registry::iniRead('has_imprint', 0) ) {
			$param['about'] .= ' - ' . G_Html::renderA('?a=imprint', G_Local::_('imprint'));
		}

		// find out scale metric to use (metric/imperial)
		$temp = strtolower(G_Local::_('format_scale'));
		if( strpos($temp, 'imperial')!==false ) { $param['imperial'] = 1; }
		if( strpos($temp, 'metric')!==false ) { $param['metric'] = 1; }
		if( !isset($param['imperial']) && !isset($param['metric']) ) { $param['imperial'] = 1; $param['metric'] = 1; }
		
		// query/render the map
		if( $gi )
		{
			if( $gi['err'] ) {
				echo G_Html::renderP(htmlspecialchars($gi['err']));
			}
			else if( sizeof($gi['places'])==0 ) {
				echo G_Html::renderP(G_Local::_('x_not_found', '<i>'.htmlspecialchars($q).'</i>'), 'class="err"');
			}
			else {	
				$param['lat'] = $gi['places'][0]['lat'];
				$param['lng'] = $gi['places'][0]['lng'];
				$param['popup'] = $gi['places'][0]['display_name'];
				$param['box'] = $gi['places'][0]['boundingbox'];
			}
		}
		echo  "</div><div id=\"ctmap\"".$this->dataEncode($param).">"; // div is closed in renderHtmlEnd() instead of the first div closed here ...
		
		// render page end
		echo $this->renderHtmlEnd();
	}
	
	
	/***********************************************************************//**
	function encodes an array as ` data-key="value" data-key2="value2" ` 
	(the returns string  starts end ends with a space)
	***************************************************************************/
	private function dataEncode($arr)
	{
		$ret = ' ';
		foreach($arr as $key=>$value) {
			$ret .= 'data-' . $key . '="' . htmlspecialchars($value) . '" '; 
		}
		return $ret;
	}
};
