<?php 
/*******************************************************************************
Go Search Engine - Formulars
****************************************************************************//**

Formulars are defined by an array as follows:

	array(
		'action'	=>	'?a=login',
		'name' 		=>	'login', // optional
		'cancel'	=>	'true', // optional - add a cancel button
		'delete'	=>	'true', // optional - add a delete button
		'input' 	=>	array(
			'first_name' => array(
				'type'	=	'hidden|text|password'),
				'value'	=>	'', 
				'right'	=>	'descr right of field',
				'below'	=>	'descr below',
			),
			'next_name' => array(
				...
			),
	);

POST or REQUEST/GET?  G_Form uses POST to read data  (for security reasons, one
should use POST whenever dealing with formulars that modify andy data (this 
avoids modification of data by just giving an obfuscated link to the user).  
	
IDEA: It should be possible to encrypt formulars even without https:

- for each form, the server creates a random public/private key pair and
  remembers it for a little while

- the server sends a the public key in an hidden form field

- JavaScript encrypts the formular data with this public key before it is 
  sended
  
- the server decrypts the formular using the remembered private key

The key must be unique for every form, so a replay is not possible.
There seems to be already implementations for this, see 
http://www.jcryption.org/

@author Björn Petersen	

*******************************************************************************/

class G_Form
{
	public $param;
	public $errors;
	
	function __construct($param)
	{
		$this->param = $param;
		$this->errors = array();
		
		// complete some values - when adding stuff later, there must noting be left out! 
		foreach( $this->param['input'] as $name => &$curr )
		{	
			if( !$curr['type'] )  { $curr['type'] = 'text';	}
		}
	}
	
	function addError($error)
	{
		$this->errors[] = $error;
	}
	
	function hasErrors()
	{
		return sizeof($this->errors)? true : false;
	}
	
	/***********************************************************************//**
	The function isOk() checks,
	
	- if the form was posted and OK was pressed, if so:
	- all parameters are set to the posted values
	- the parameters are roughly checked
	
	If everything is allright, true is returned and the data are ready for
	further processing.  Else, false is returned and $this->errors contains the
	problems (they, however, are shown automagic on the next render())
	***************************************************************************/
	function isOk()
	{
		$this->errors = array();
		
		if( !isset($_POST['subseq']) || isset($_POST['cancel']) || isset($_POST['delete']) )
			return false; // no further processing - errors is empty
		
		foreach( $this->param['input'] as $name => &$curr )
		{	
			if( !$curr['label'] ) {	$curr['label'] = 'input_'.$name;	}

			// get value from formular
			// (we trim all values, currently, I cannot imaging a situation where spaces are desired)
			switch( $curr['type'] ) {
				case 'readonly':											break;
				case 'checkbox':	$curr['value'] = $_POST[$name]? 1 : 0; 	break;
				default:			$curr['value'] = trim($_POST[$name]); 	break;
			}
			
			// validate the value
			if( $curr['value'] == '' ) {
				// got an empty value
				if( $curr['required'] ) {
					$this->errors[] = G_Local::_($curr['label']) . ': ' . G_Local::_('input_required');
				}
			}
			else {
				// go a non-empty value ...
				if( $curr['type']=='select' ) {
					// ensure, the submitted value really exists in the predefined options
					if( !isset($curr['options'][ $curr['value'] ]) ) {
						$this->errors[] = G_Local::_($curr['label']) . ': ' . G_Local::_('bad_value') .': ' .htmlspecialchars($curr['value']); // this may only happen after updates, where old values are no longer used ...
					}
				}
				else if( $curr['type']=='password' && $name=='pw2' ) {
					// ... ensure identical password fields for fields named pw1 and pw2
					$pw1_val = $this->param['input']['pw1']['value'];
					if( $pw1_val!='' && $pw1_val!=$curr['value'] ) { 	
						$this->errors[] = G_Local::_('input_pwmismatch'); // no label before needed - we have only one password field per formular
					}
				}
				else if( $curr['validate']=='username' ) {
					// ... valiate user name
					if( !preg_match('/^[a-z][a-z0-9\-]*$/i', $curr['value']) ) {
						$this->errors[] = G_Local::_($curr['label']) . ': ' . G_Local::_('input_badusername', 'a-z A-Z 0-9 -');
					}
				}
			}
		}
		
		return sizeof($this->errors)? false : true;
	}
	
	function shallCancel()
	{
		return (isset($_POST['subseq'])&&isset($_POST['cancel']))? true : false;
	}
	
	function shallDelete()
	{
		return (isset($_POST['subseq'])&&isset($_POST['delete']))? true : false;
	}
	
	function render()
	{
		// form start
		$autocomplete = '';
		if( $this->param['autocomplete'] ) { $autocomplete = " autocomplete=\"{$this->param['autocomplete']}\""; }
		
		$formname = $this->param['name']? " name=\"{$this->param['name']}\"" : '';
		$ret = "<form{$formname} action=\"".G_Html::renderUrl($this->param['action'])."\" method=\"post\" class=\"std\"{$autocomplete}>";
		
			// pass 1: render hidden stuff, get some information
			$max_label_chars = 0;
			$anything_required = false;
			$ret .= "<input type=\"hidden\" name=\"subseq\" value=\"1\" />";
			foreach( $this->param['input'] as $name => &$curr )
			{
				if( !$curr['label'] ) {	$curr['label'] = 'input_'.$name;	}
				
				if( $curr['type'] == 'hidden' ) {
					$ret .= "<input type=\"hidden\" name=\"{$name}\" value=\"".htmlspecialchars($curr['value'])."\" />";
				}
				else {
					$curr_label_chars = strlen(G_Local::_($curr['label'])) + ($curr['required']? 2 : 0);
					if( $curr_label_chars > $max_label_chars ) {
						$max_label_chars = $curr_label_chars;
					}
					
					if( $curr['required'] ) {
						$anything_required = true;
					}
				}
			}
			$ret .= "\n";
			
			// text above
			if( $anything_required )
			{
				$req_mark = '<span class="rq">*</span>';
				$ret .= '<p>';
					$ret .= G_Local::_('form_required_descr', $req_mark);
				$ret .= "</p>\n";
			}
			
			if( sizeof($this->errors) )
			{
				$ret .= '<p class="err">';
					for( $i = 0; $i < sizeof($this->errors); $i++ ) 
					{
						$ret .= $i? '<br />' : (G_Local::_('err').': ');
						$ret .= $this->errors[$i];
					}
				$ret .= "</p>\n";
			}
			
			// pass 2: render input controls
			$ret .= '<table>';
			foreach( $this->param['input'] as $name => &$curr )
			{
				if( $curr['type'] == 'hidden' )
					continue; // already rendered above
					
				$ret .= "<tr><td>";
					
					$ret .= "<label for=\"{$name}\">" . G_Local::_($curr['label']);
						if( $curr['required'] ) $ret .= ' ' . $req_mark;
					$ret .= "</label> ";
				
				$ret .= '</td><td>';
					
					switch( $curr['type'] ) 
					{
						case 'readonly':
							$ret .= $curr['valuehtml']? $curr['valuehtml'] : htmlspecialchars($curr['value']);
							break;
						
						case 'checkbox':
							if( 1 ) {
								$checked = $curr['value']? ' checked="checked"' : '';
								$ret .= "<input type=\"checkbox\" name=\"{$name}\" id=\"{$name}\" value=\"1\"{$checked} /> ";
							}
							else {
								$ret .= "<select name=\"{$name}\">";
									$yes_selected =  $curr['value']? ' selected="selected"' : '';
									$no_selected  = !$curr['value']? ' selected="selected"' : '';
									$ret .= "<option value=\"1\"{$yes_selected}>".G_Local::_('yes').'</option>';
									$ret .= "<option value=\"0\"{$no_selected}>" .G_Local::_('no').'</option>';
								$ret .= '</select>';
							}
							break;
						
						case 'select':
							$ret .= "<select name=\"{$name}\">";
								$sth_selected = false;
								foreach( $curr['options'] as $option_value => $option_descr ) {
									$selected = '';
									if( $curr['value']==$option_value )  {
										$selected = ' selected="selected"';
										$sth_selected = true;
									}
									$ret .= "<option value=\"".htmlspecialchars($option_value)."\"{$selected}>".G_Local::_($option_descr).'</option>';
								}
								if( !$sth_selected ) {
									$ret .= "<option value=\"".htmlspecialchars($curr['value'])."\" selected=\"selected\">".G_Local::_('bad_value').': '.$curr['value'].'</option>';
								}
							$ret .= '</select>';
							break;
						
						case 'textarea':
							$rows = $curr['rows']>0? $curr['rows'] : 2;
							$ret .= "<textarea name=\"{$name}\" rows=\"{$rows}\">";
								$ret .= htmlspecialchars($curr['value']);
							$ret .= '</textarea>';
							break;
						
						case 'password': // for security reasons, always set the value to "" - otherwise partly entered passwords are cached in the browser, double choice of sniffing etc.
							$ret .= "<input type=\"{$curr['type']}\" name=\"{$name}\" id=\"{$name}\" value=\"\" /> ";
							break;
						
						default: // text, misc.
							$ret .= "<input type=\"{$curr['type']}\" name=\"{$name}\" id=\"{$name}\" value=\"".htmlspecialchars($curr['value'])."\" /> ";
							break;
					}
					
					if( $curr['right'] )
					{
						$ret .= ' ' . $curr['right'];
					}
					
					if( $curr['below'] ) 
					{
						$ret .= '<br />' . $curr['below'];
					}
					
				$ret .= "</td></tr>\n";
			}
			
			// optional: delete tick (this works best without JavaScript (two actions required))
			if( $this->param['delete'] ) 
			{
				$ret .= "<tr><td>";
					$label = (is_string($this->param['delete']) && $this->param['delete'] != '')? $this->param['delete'] : 'delete';
					$ret .= '<label for="delete">' . G_Local::_($label) . '</label>';
				$ret .= "</td><td>";
					$ret .= '<input type="checkbox" name="delete" id="delete" value="1" /> ';
				$ret .= "</td></tr>\n";
			}	
				
			// buttons
			$ret .= "<tr><td></td><td>";
				// always: OK or sth. simelar
				$label = G_Local::_(is_string($this->param['ok'])? $this->param['ok'] :'button_ok');
				$ret .= "<input type=\"submit\" name=\"ok\" value=\"$label\" /> ";
				
				// optional: Cancel button
				if( $this->param['cancel'] ) {
					$label = G_Local::_(is_string($this->param['cancel'])? $this->param['cancel'] : 'button_cancel');
					$ret .= "<input type=\"submit\" name=\"cancel\" value=\"$label\"> ";
				}
				
			$ret .= "</td></tr>\n";
			
			$ret .= "</table>";
			

			
		$ret .= "</form>\n";
		return $ret;
	}
};
