<?php
/*******************************************************************************
Go Search Engine - Error Handling
****************************************************************************//**

A shortcut for Action_Error(404), needed in framework.php

@author Björn Petersen

*******************************************************************************/

class Action_Error404 extends Action_Error
{
	function __construct()
	{
		parent::__construct(404);
	}
};
