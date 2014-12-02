/*******************************************************************************
Go Search Engine - Framework
****************************************************************************//**

The framework, JavaScript part

@author Björn Petersen

*******************************************************************************/

function prepare_timeout(seconds)
{
	var url = '?a=cron&ret=json&rnd='+new Date().getTime();
	window.setTimeout(function() {
		$.getJSON(url, function(json_data) {
			prepare_timeout(json_data['refresh']);
		});
	},
	seconds*1000);
}

$(document).ready(function() {
	
	// set the focus to a marked control
	// this does not work very wll with mobile devices.
	//$("input.setfocus:first").focus();

	// keep the cron job alive: the first call is fixed after two minutes; after that we get a "better" timeout from the server
	prepare_timeout(120);
});

