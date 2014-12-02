
/*******************************************************************************
Go Search Engine - Admin
****************************************************************************//**

Crawler configuration and information, JavaScript part

@author Björn Petersen

*******************************************************************************/

$(document).ready(function() 
{
	window.setInterval(function() {
		var url = '?a=adminindex&p=ajax&rnd='+new Date().getTime();
		$("#overviewvalues").load(url);
	},
	2500); // 2.5 seconds - if the interval is shorter, the tooltip of the links may not get opened

});
