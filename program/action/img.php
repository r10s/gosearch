<?php
/*******************************************************************************
Go Search Engine - Image Search
****************************************************************************//**

Image Search

@author Björn Petersen

*******************************************************************************/

class Action_Img extends Action_Search
{
	public function handleRequest()
	{
		$this->hasSearchAccessOrDie();
		
		$q = trim($_GET['q']);
		$title = $q==''? G_Local::_('menu_imgsearch') : htmlspecialchars($q);
		$bodyclass = $q==''? 'space' : '';
		echo $this->renderHtmlStart(array('title'=>$title, 'bodyclass'=>$bodyclass));
			
			echo $this->renderSearchForm(array('h1'=>'menu_imgsearch', 'button_title'=>'button_imgsearch', 'bodyclass'=>$bodyclass));
			
			// output page up to here
			G_Tools::forceFlush();
			
			// do the search ...
			if( $q != '' )
			{
				for( $i = 0; $i < 20; $i++ )
				{
					echo 'bild bild bild <br/><br/>';
				}
			}
			
		echo $this->renderHtmlEnd();
	}
};
