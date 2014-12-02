<?php 
/*******************************************************************************
Go Search Engine - Zero Click Base Class
****************************************************************************//**

"Zeroclick" are modules that are triggered on specific user input.  Eg. if the
user types "2*2" a zeroclick module may display "4" immediately.

As a rule of thumb, every single zeroclick module should only be very few KB in
size, you can treat 10 KB as a maximum for code _and_ data.  If a module is 
larger it must really give enourmous benefits to a majority of users!

Technically, a zeroclick box is a class derived from Zeroclick_Base and should
implement least renderContent().  renderContent() then should check the query in 
$this->q _carefully_ - if in doubt, a query can also have another meaning, do 
not match!  However, if the query is fine, renderContent() should return just a 
line of HTML to display.  If it decides not handle the query just an empty 
string should be returned.

How is a zeroclick-object being created? And which?

- well, for performance reasons, we do not have such a check in the 
  zeroclick-classes - otherwise we would always need to load all zeroclick files
  just to see that there is nothing to display (which is true for the very most
  cases)

- so, we decided to hardcode the stuff in Action_Search::renderZeroclick() - 
  this function can make checks much faster (eg. by putting everything starting
  with a number togeher) and we can also react to conflichts there.

- most times, Action_Search::renderZeroclick() will not render anything - but in
  this case none of the zeroclick files get loaded - not even this file!

Ideas:

- show colors from #rrggbb or rgb(r,g,b)  

- Input year => show calendar

- Input town/country/region: Show a map (this is more complicated and would
  require OSM or sth. like that)
	
- Input "ascii table" or a character encoding => show the character table

- Results may take more room, the box, however, should not take too much
  place (1/3 or the screen?).  If the content is larger, overflow should be
  hidden and the box should be expandable (via CSS?)
	
- We can also treat the zeroclick boxes as mini-programs; if a page reload 
  will be needed, we can also display _only_ the zeroclock-box, eg. by an
  additional parameter.

@author Björn Petersen

*******************************************************************************/

class Zeroclick_Base
{
	protected $q;
	
	function __construct($q)
	{
		$this->q = $q;
	}

	private function renderStart()
	{
		return '<div class="zeroclick">';
	}
	
	private function renderEnd()
	{
		return '</div>';
	}
	
	
	/***********************************************************************//**
	renderContent() or renderBoxAndContent() should be implemented by
	derived classes.
	
	@return renderContent() should return the HTML code (as simple lines, no 
			paragraphs or boxes) of the zeroclick, if the input could not be 
			matched, an empty string is returned.
	***************************************************************************/
	protected function renderContent()
	{
		return ''; // this method should be overwritten by derived classes
	}
	
	
	/***********************************************************************//**
	renderContent() or renderBoxAndContent() should be implemented by
	derived classes.

	@return renderContent() should return the HTML code (boxed by div or sth. 
			like that) of the zeroclick, if the input could not be matched, 
			an empty string is returned.
	***************************************************************************/
	function renderBoxAndContent()
	{
		$content = $this->renderContent();
		if( $content == '' )
			return ''; // for errors or if the query cannot be handled: no zeroclick-box
			
		return	$this->renderStart()
			.		$content	
			.	$this->renderEnd() . "\n";
	}
};
