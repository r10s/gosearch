<?php 

// for easy updates, we keep this file small; all stuff goes to /program
define('G_DOCUMENT_ROOT',	dirname(__FILE__)=='/' ? '' : dirname(__FILE__)); // no trailing slash
require_once(G_DOCUMENT_ROOT . '/program/g/framework.php');
