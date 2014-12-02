
/*******************************************************************************
Go Search Engine - Maps
****************************************************************************//**

Maps, JavaScript part

@author Björn Petersen

*******************************************************************************/

var $ctmap;
var previous_height = -1;
function resize_map()
{
	var new_height = parseInt($(window).height() - $ctmap.position().top);
	if( new_height != previous_height ) {
		previous_height = new_height;
		if( new_height > 200 ) {
			$ctmap.css({'height':new_height+'px'});
		}
	}
}


	
$(document).ready(function() 
{
	// getting a div to 100% in height via HTML/CSS is complicated and does not work everywhere,
	// esp. not with the html5-doctype. So we do this with jQuery - one initial resize and then we listen to resize events:
	$ctmap = $('#ctmap');
	resize_map();
	$(window).resize(resize_map);
	
	// create the map
	var map = L.map('ctmap', {
		fadeAnimation: false,	// just display after loading, faster
	});
	map.attributionControl.setPrefix(''); // remove leavlet-link, we add attribution to this at another, better, no-js place
	
	// add scale control to map
	var show_imperial = !!$ctmap.attr('data-imperial');
	var show_metric = !!$ctmap.attr('data-metric');
	L.control.scale({imperial:show_imperial, metric:show_metric, position:'bottomright'}).addTo(map); // at bottomleft, it is often hidden by the browser's link preview 
	
	// create map layer
	L.tileLayer($ctmap.attr('data-tile'), {
		attribution: $ctmap.attr('data-about')
	}).addTo(map);	
	
	// initialize the map ...
	var lat = $ctmap.attr('data-lat'), lng = $ctmap.attr('data-lng');
	if( lat != undefined && lng != undefined && lat!='' && lng!='' )  
	{
		// ... show a marker, set fine zoom to marker boundings
		L.marker([lat, lng]).bindPopup($ctmap.attr('data-popup')).addTo(map);
		var set_view_zoom = 15, box = $ctmap.attr('data-box');
		if( box != undefined && box != '' ) { // move to bounds, if possible
			var splitted = box.split(',');
			if( splitted.length == 4 ) {
				map.fitBounds(L.latLngBounds([splitted[0], splitted[2]], [splitted[1], splitted[3]]), {maxZoom: 17});
				set_view_zoom = map.getZoom();
			}
		}
		
		map.setView([lat, lng], set_view_zoom, {animate: false}); // show most of the world
	
	}
	else
	{
		// ... show most of the world
		map.setView([28, 10], 3, {animate: false}); 
	}
});

