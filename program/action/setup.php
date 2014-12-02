<?php
/*******************************************************************************
Go Search Engine - Setup and check the data directory, users etc.
****************************************************************************//** 

@mainpage

This file *is* *not* a complete development documentation, however, there may 
be some important information in here.


Global Parameters
-----------------

Parameter names used globally by the platform framework:

- `a=<action>` - The action

- `l=<layout>` - The layout, currently forwarded (we avoid sessions/cookies when
  not logged in), but not used
				
- `u` - Just a flag, set, if the user is logged in unset otherwise. Needed as we 
  let the browser cache the SERPs - and we do want a proper header/menu if the 
  user searches -> logs in -> searches again - the last page would come out of
  the cache with the wron header otherwise.

  I do not really like this solution, but I have no other idea.  

  The alternative is, to just ignore this issue. It is only a problem, if the 
  user does the _same_ search from before login after login. maybe this is not 
  so often a problem -  and the F5/reload key will help. Ignoring this issue, we
  have the advantage of more proper URLs, simple go/?q=.. instead of go/?u&q=..  
  What do others with this issue? Eg. Github ignores it as well. So, if we 
  ignore it, we're at least not alone ;-)
  
  EDIT 28.03.2014: An alternative may be to have a look at the cookie from
  JavaScript and add/hide the corresponding elements.

One-character names that _may_ be used by actions/modules:

- `p=<page>` - a page in an action
	
- `q=<query>` - a query

All other one-character names are reserved for future use. Actions/modules shall
use parameters with more than one character therefore.

Actions
-------

Files in the `/program/action` directory are the main entry points and can be 
executed directly from the URL.

Therefore, eg. the URL `?a=profile` will search for a file 
`/program/action/profile.php` that must define the class `Action_Profile` then.
If this is successfull, the class is instanced and the member 
`$ob->handle_request()` is called.

All this is done in `/program/g/framework.php` 

Conventions
-----------

- Language:
  Documentation language is english. Maybe the worst, but english ;-)

- Source code files _must_ use UTF-8 _without_ BOM

- Source code: 
  It is desired to document all important classes and functions using the 
  Doxygen format, however, currently, most stuff is documented the "free way"

- Naming conventions we try to follow (inspired by http://www.php-fig.org/ ):

	- `DEFINED_VALUE`
	- `function_name()`
	- `Scope_ClassName` 
	- `$this::CLASS_CONSTANT`
	- `$this->classMemberVar`
	- `$this->classMemberFunction()`
	
- Readmes & Comments: These texts should be formatted using 
  [Markdown](http://daringfireball.net/projects/markdown/).  For text- and 
  Markdown files, we prefer the file extension `.txt` instead of `.md` as the 
  latter is less spreaded and does not work from scratch.

- we do not use `$_REQUEST` - instead, we use `$_POST` and `$_GET` (there are
  some security issues with `$_REQUEST`, eg. on cross site scripting); we
  _plan_ to avoid letting `$_GET` modify any data  - no delete or logout
  links.

Issues
------

- is sth. like if( !defined('G_INDEX_PASSED') ) die('!G_INDEX_PASSED'); atop of 
  every PHP-file needex?  This may add little security, however, as most files 
  do not execute anything but only init some static values, there is no large
  benefit IMHO ... 

@author Björn Petersen 

****************************************************************************//**

Action_Setup is used to check the data directory, users etc.

Some Notes to the databases
---------------------------

- "INTEGER PRIMARY KEY" makes the field an alias to ROWID, a 64bit, 
  table-unique integer. 

- AUTOINCREMENT can be added, but this will add a little overhead and only  
  makes sure, IDs are not re-used - if reusage does not hurt it can be skipped. 

- UNIQUE and PRIMARY KEY impies and index in sqlite!
 
- We prefix table names with "t_" and field names with "f_" - this is a little
  bit ugly, but this way, we do not confuse with reserved words (which are 
  **many** in the different database implementations), and we are always 
  consistent.

- Plural or singular table names? Well, we prefer singular names:

	- Tables by definition, are collections of records.  Therefore, it is 
	  redundant to plural a table name.
		
	- Objects can have irregular plurals or not plural at all, but there is a
	  singular name most times (with few exceptions like News).
		
	- Combining names: t_user_prop is fine, t_users_props is ugly, IMHO
	
	- There may be other reasons against singular, however, that's the way we
	  go.
	  
@author Björn Petersen 

*******************************************************************************/

class Action_Setup extends G_Html
{
	private function renderSetupStart()
	{
		echo $this->renderHtmlStart(array('title'=>G_Local::_('setup_title')));
			echo '<div style="border: 6px dashed #C0C000; padding: 2.5em; background: #FFFFC0; margin:3em 0;">'; // we hardcode the style here is it is only needed once and as it is easy to forget if in the layout ...
	}
	private function renderSetupEnd()
	{
			echo '</div>';
		echo $this->renderHtmlEnd();
	}
	
	/***********************************************************************//**
	Function is called for the request ?a=setup.   If the setup is not yet 
	done:
	
	- the function checks if the data directory defined by G_DATA_ROOT is 
	  writable
	- the function creates all needed databases in the data directory
	- finally, it prompts the user to create an administration account
	
	If the setup was already finished before, the function prints an 404 error.
	
	@param none
	@returns noting
	***************************************************************************/
	function handleRequest()
	{
		// check, if the data directory is writable
		$ob = new G_DbSetup();
		if( !$ob->isDataDirWritable() )
		{
			echo $this->renderSetupStart();
				echo G_Local::_('setup_makewritable', G_DATA_ROOT, G_Html::renderAhref('?a=setup&rnd='.time()), '</a>');
			echo $this->renderSetupEnd();
			exit();
		}
	
		// initialize/update the database files
		$ob->databaseSetup();	
	
		// create administator
		$reg = G_Db::db('registry');
		$reg->sql->query("SELECT * FROM t_user WHERE f_admin<>0;");
		if( $reg->sql->next_record() )
		{
			$this->msgAndDie(404);
		}
		
		$form = new G_Form(array(
			'action'		=>	'?a=setup',
			'autocomplete'	=>	'off',
			'input' 		=>	 array(
				'username'	=>	array('value'=>'',	'required'=>true, 'validate'=>'username', 'right'=>G_Tools::onHost()),
				'pw1' 		=>	array('value'=>'', 	'required'=>true, 'type'=>'password', 'label'=>'input_pw'),
				'pw2'		=>	array('value'=>'', 	'required'=>true, 'type'=>'password', 'label'=>'input_repeat_pw'),
			)
		));

		if( $form->isOk() )
		{
			$username = $form->param['input']['username']['value'];
			if( !$reg->addRecord('t_user', array(
				'f_username'	=>	$username,
				'f_pw'			=>	crypt($form->param['input']['pw1']['value']),
				'f_authtoken'	=>	Action_Profile::createAuthToken(),
				'f_admin'		=>	1,
			)) ) {
				G_Log::logAndDie('Cannot create user. ' . $reg->getLastError());
			}
			// G_Log::log('admin created: '.$username); -- this is not really interesting
			G_Html::redirect('?a=login&username=' . urlencode($username));
		}
		
		echo $this->renderSetupStart();
			echo G_Local::_('setup_createadmin') . "\n";
			echo $form->render();
		echo $this->renderSetupEnd();

	}
	
	
	/***********************************************************************//**
	Function is called from most HTML pages via the G_Html constructor.
	The function checks:
	
	-	if the setup should be started - if so, we redirect to ?a=setup and
		Action_Setup::handleRequest() does the rest
	-	if the database should be updated
	
	@param none
	@return nothing
	***************************************************************************/
	static function smartInstallOrUpdate()
	{
		if( $_GET['a']=='setup' )
		{
			return;
		}
		
		$reg = G_Db::db('registry', false);
		if( $reg===false 
		 || !$reg->sql->table_exists('t_ini') 
		 || $reg->recordCnt('t_user')==0 )
		{
			G_Html::redirect('?a=setup');
		}

		// silent udpate
		$ob = new G_DbSetup();
		$ob->databaseSetup();		
	}
};



		
