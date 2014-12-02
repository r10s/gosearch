<?php
/*******************************************************************************
Go Search Engine - Opensearch XML
****************************************************************************//**

Render the Opensearch XML.

For the specification, refer to 
http://www.opensearch.org/Specifications/OpenSearch/1.1#OpenSearch_description_document

@author Björn Petersen

*******************************************************************************/

class Action_Opensearch 
{
	public function handleRequest()
	{
		$host = G_Hosts::getSelf()->getAbs(); // this includes the trailing slash!
		
		header('Content-type: application/opensearchdescription+xml');
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		echo "<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearch/1.1/\" xmlns:moz=\"http://www.mozilla.org/2006/browser/search/\">\n";
		
			echo "<ShortName>" . G_PROGRAM_NAME . "</ShortName>\n";
			echo "<Description>" . G_Local::_('search_with_x', G_PROGRAM_NAME) . "</Description>\n";
			echo "<InputEncoding>UTF-8</InputEncoding>\n";
			echo "<Image height=\"16\" width=\"16\" type=\"image/x-icon\">" . $host . G_Html::getFavicon(). "</Image>\n";
			
			// the URL loaded on search (when the user hits enter - OpenSearch also supports suggestions, however, currently this is not supported by us)
			echo '<Url type="text/html" method="get" template="' .$host. '?q={searchTerms}" />' . "\n";
			
		echo "</OpenSearchDescription>\n";

	}
};
