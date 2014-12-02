<?php 
/*******************************************************************************
Go Search Engine - Zero Click Calculations renderer
****************************************************************************//**

Zero Click Calculations renderer
 
Matched searches:
- 42
- guid
- rot13, reverse
- md5, sha1
- urlencode
- time, unix time, server time
- simple + - * / operations

Ideas:
- ascii|unicode <character>|<ord> show information about the character and the 
  character itself; plus the surrounding characters (so we would have a dynamic 
  ascii table)

@author Björn Petersen 

*******************************************************************************/

class Zeroclick_Calc_Renderer extends Zeroclick_Base
{
	private function startsWith($input, $token)
	{
		$tlen = strlen($token);
		if( substr($input, 0, $tlen+1) == "$token " ) { 
			$arg = trim(substr($input, $tlen+1));
		}
		else {
			$arg = '';
		}
		return $arg==''? false : $arg;
	}
	
	private function createGuid()
	{
		if (function_exists('com_create_guid'))
		{
			return trim(com_create_guid(), '{}');
		}
		else
		{
			$charid = strtoupper(md5(uniqid(rand(), true)));
			return substr($charid, 0, 8).'-'.substr($charid, 8, 4).'-'.substr($charid,12, 4).'-'.substr($charid,16, 4).'-'.substr($charid,20,12);
		}
	}
	
	function renderContent()
	{
		G_Local::load('program/zeroclick/calc/lang');
		
		// some specials
		if( $this->q == '42' )
		{
			return $this->q . ' = The answer';
		}
		else if( $this->q =='guid' )
		{
			 return G_Local::_('title_rnd_guid') . ': <span class="shy">{</span>' . $this->createGuid() . '<span class="shy">}</span>';
		}
		else if( ($arg=$this->startsWith($this->q, 'rot13'))!==false )
		{
			return 'ROT13: ' . htmlspecialchars(str_rot13($arg));
		}
		else if( ($arg=$this->startsWith($this->q, 'md5'))!==false )
		{
			return 'MD5: ' . htmlspecialchars(md5($arg));
		}		
		else if( ($arg=$this->startsWith($this->q, 'sha1'))!==false || ($arg=$this->startsWith($this->q, 'sha-1'))!==false )
		{
			return 'SHA-1: ' . htmlspecialchars(sha1($arg));
		}
		else if( ($arg=$this->startsWith($this->q, 'reverse'))!==false )
		{
			$rev = join("", array_reverse(preg_split("//u", $arg))); // strrev($arg); is not unicode-save!
			return 'Reversed: ' . htmlspecialchars($rev); 
		}
		else if( ($arg=$this->startsWith($this->q, 'urlencode'))!==false )
		{
			return 'urlencode: ' . htmlspecialchars(urlencode($arg));
		}
		else if( ($arg=$this->startsWith($this->q, 'soundex'))!==false )
		{
			return	'soundex: ' . htmlspecialchars(soundex($arg)) . ', metaphone: ' . htmlspecialchars(metaphone($arg));
		}
		else if( $this->q=='time' || $this->q=='unix time' || $this->q=='server time' ) 
		{
			$timestamp = time();
			return 'Server time: ' . htmlspecialchars(strftime(G_Local::_('format_date_time'), $timestamp))
				. '<br />' . 'Unix time: ' . $timestamp;
		}
		else if( ($arg=$this->startsWith($this->q, 'time'))!==false || ($arg=$this->startsWith($this->q, 'unix time'))!==false )
		{
			if( !preg_match('/^[\d]+$/', $arg) ) { return ''; }
			$timestamp = intval($arg);
			return 'Unix time: ' . strftime(G_Local::_('format_date_time'), $timestamp);
		}
		
		// if the query contains anything but ()0-9.+-*/ or spaces, we do not calc anyting
		$eval = strtolower(str_replace(',', '.', $this->q));
		$operators = '\+\-\*\/'; // note: ^ is the XOR operator in PHP!
		if( !preg_match('/^[\(\)\d\.'.$operators.'\s]+$/', $eval) )
		{
			return '';
		}
		
		// make sure, there is at least one operator
		if( !preg_match('/['.$operators.']/', $eval) )
		{
			return '';
		}
		
		$result = @eval('return ' . $eval .';');
		if( $result !== false )
		{
			$result = str_replace('.', G_Local::_('calc_comma'), $result);
			$ret = $this->q . ' = ' . htmlspecialchars($result);
			return $ret;
		}
	}
};
