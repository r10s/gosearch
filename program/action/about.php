<?php
/*******************************************************************************
Go Search Engine - About
****************************************************************************//**

Render About

@author Björn Petersen

*******************************************************************************/

class Action_About extends G_Html
{
	/***********************************************************************//**
	Called if the user opens the page `?a=about`
	***************************************************************************/
	function handleRequest()
	{
		// render page 
		echo $this->renderHtmlStart(array('title'=>G_PROGRAM_FULL_NAME . ' contributors'));
		
		
			echo G_Html::renderH1(G_PROGRAM_FULL_NAME . ' contributors');
			echo G_Html::renderP('Program Version: '. G_PROGRAM_FULL_NAME . ' ' . G_VERSION);
			echo G_Html::renderP('Main program design and development: Björn Petersen');
			echo G_Html::renderP('Like this program? Become a part of the free search network and install it on your server.<br />'
								.'The Installation is very easy and nothing but a recent PHP-Installation is needed.');
			echo G_Html::renderP('The program will be released under a GPL-like license, however, currently it is not clear which one to take.');
			echo G_Html::renderP(G_PROGRAM_FULL_NAME . ' uses 
										<a href="http://www.php.net/">PHP</a>,
										<a href="http://www.sqlite.org/">sqlite</a>,
										<a href="http://jquery.com/">jQuery</a>.
								  Special Thanks go to
										<a href="http://yacy.net/">YaCy</a> for the Stoplists.');
			echo G_Html::renderP('Source code and downloads are available on <a href="https://github.com/r10s/gosearch">Github</a>.');
			echo G_Html::renderP('Contact: <a href="http://b44t.com/contact">http://b44t.com/contact</a>');
			
			/* not yet needed as we currently have no map implementation ...
			echo G_Html::renderH1('OpenStreetMap contributors', 'id="osm"');
			echo G_Html::renderP('OpenStreetMap-Data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>');
			
			$server = defined('G_TILE_SERVER')? constant('G_TILE_SERVER') : '';
			$attribution = $server==''? Action_Maps::DEFAULT_TILE_ATTRIBUTION : @constant('G_TILE_ATTRIBUTION');
			if( $attribution ) {
				echo G_Html::renderP($attribution);
			}

			$server = defined('G_NOMINATIM_SERVER')? constant('G_NOMINATIM_SERVER') : '';
			$attribution = $server==''? G_Geocode::DEFAULT_GEOCODE_ATTRIBUTION : @constant('G_NOMINATIM_ATTRIBUTION');
			if( $attribution ) {
				echo G_Html::renderP($attribution);
			}
			
			echo G_Html::renderP('OpenStreetMap-JavaScript by <a href="http://leafletjs.com/">Leaflet</a>');
			*/

			
		echo $this->renderHtmlEnd();
	}
};

