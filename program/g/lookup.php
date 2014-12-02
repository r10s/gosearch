<?php
/*******************************************************************************
Go Search Engine - Lookup
****************************************************************************//**

@author Björn Petersen 

*******************************************************************************/

class G_Lookup  
{
	const MAX_QUERY_ARGS		= 16;	// a maximum to avoid too time-expensive queried
	const MIN_QUERY_ARGS		= 1;	// only "must" words are counted as arguments - not-, site- or lang-operators are not counted
	const MAX_QUERY_LEN			= 1000; // an argument may also be an OR'ed list, so allow some characters more than MAX_QUERY_ARGS * avg. argument length
	const MIN_NON_WILDCARD_LEN	= 3;	// when using wildcards, this is the minimal length of the string without the wilcchard characters * and ?

	
	/***********************************************************************//**
	lookup() is the central search function, it is called from external via
	`?a=p2p&p=lookup&id=..&ret=..&q=..` or from internally directly.
	
	`$q_str` is a space-separated list with the following arguments:

	- `word`			- search for a given word											
	- `word word`		- and-search for several words
	- `word OR word` 	- or-search for several words
	- `-word`			- _exclude_ the word from the query
	- `-word OR word`	- _exclude_ several words from the query, same as
						  `-"<word> <word>"`, eg. `-"microsoft windows"` (we
						  use the quotes only as brackets here; the 
						  system does *not* support a phrase search)
	- `site:domain.com`	- search only of the given host
	- `-site:domian.com` - _exclude_ the given host
	- `lang:de`			- search only for the given language
	 
	This part of the system should be *as* *fast* *as* *possible* - eg. if we 
	spread our search to 1000 peers, we also have to answer approx. 1000 
	external searches for 1 internal search! So, optimisation here makes sense.
	
	NB: I'm no SQL expert - anyone who can help me making the queries faster, 
	is welcome! (bp)
	
	@returns	array('err'=>.., 'result=>array(array('url'=>, 'title'), ..)) 
	***************************************************************************/
	function lookup($q_str)
	{
		$q_str = G_TokenizeHelper::searchify($q_str);
	
		$ret = array('seconds'=>microtime(true));
		
		if( strlen($q_str) > $this::MAX_QUERY_LEN ) {
			return array('err'=>'err_querytoolong');
		}
		
		$q_arr = explode(' ', $q_str);
		
		$db_index = G_Db::db('index');
		


		$arg_cnt = 0;
		$ftquery = '';
		for( $i = 0; $i < sizeof($q_arr); $i++ )
		{
			$arg = $q_arr[$i];
			if( $arg != '' ) 
			{
				$ftquery .= $ftquery==''? '' : ' ';
				if( $arg[0] == '-' ) {
					if( strlen($arg) > 1 ) {
						$ftquery .= $arg;
					}
				}
				else {
					$arg = trim(str_replace('-', ' ', $arg)); // zusammengesetzte Wörter wie `top-themen` werden von sqlite als `top -themen` (also "top ohne themen") interpretiert, daher entfernen wir einfach alle Binnen-Bindestriche
					if( $arg != '' ) {
						$ftquery .= $arg;
						$arg_cnt++;
					}
				}
			}
		}
		
		if( $arg_cnt > $this::MAX_QUERY_ARGS ) {
			return array('err'=>'err_toomanyargs');
		}
		else if( $arg_cnt < $this::MIN_QUERY_ARGS ) {
			return array('err'=>'err_toofewargs');
		}
		
		$ret['result'] = array();

		if( !isset($db_index->functionCreated) ) { 
			$db_index->sql->dbHandle->sqliteCreateFunction('rank', 'sql_rank');
			$db_index->functionCreated = true;
		}
		
		// 								ORDER BY rank(matchinfo(t_fulltext), 1)

		$db_index->sql->query("SELECT f_url,f_title,
									  snippet(t_fulltext,'<b>','</b>',' [...] ',-1,32) AS spt
								 FROM t_url,t_fulltext 
								WHERE f_id=docid 
								  AND t_fulltext MATCH " . $db_index->sql->quote($ftquery) . "
								LIMIT 100;");
		
		while( ($record=$db_index->sql->next_record())!==false )
		{
			$ret['result'][] = array(
				'url'=>$record['f_url'], 
				'title'=>G_TokenizeHelper::unasciify($record['f_title']), 
				'snippet'=>G_TokenizeHelper::unasciify($record['spt'])
			);
		}

		$ret['seconds'] = microtime(true) - $ret['seconds'];
		return $ret;

	}
	


	
	private function explode_n_quote_by_bar(&$db_index, $str, &$ret_err_obj)
	{
		if( strpos($str, '|')!==false ) {
			$ret = ' IN(';
				$items = explode('|', $str);
				for( $i = 0; $i < sizeof($items); $i++ ) {
					$ret .= ($i? ', ' : '') . $db_index->sql->quote($items[$i]);
				}
			$ret .= ')';
			return $ret;
		}
		/*
		else if( strpos($str, '*')!==false || strpos($str, '?')!==false ) {
			$temp = strtr($str, array('*'=>'', '?'=>'', '%'=>'', '_'=>''));
			if( strlen($temp) < $this::MIN_NON_WILDCARD_LEN ) {
				$ret_err_obj['err'] = 'err_wildcardtooshort';
				return '=' .  $db_index->sql->quote('');
			}
			$temp = strtr($str, array('*'=>'%', '?'=>'_'));
			return ' LIKE ' . $db_index->sql->quote($temp);
		}
		*/
		else {
			return '=' . $db_index->sql->quote($str);
		}
	}

};




function sql_rank($aMatchInfo)
{
	global $g_cnt;
	$g_cnt++;
	
    $iSize = 4;
    $iPhrase = (int) 0;                 // Current phrase //
    $score = (double)0.0;               // Value to return //

    /* Check that the number of arguments passed to this function is correct.
    ** If not, jump to wrong_number_args. Set aMatchinfo to point to the array
    ** of unsigned integer values returned by FTS function matchinfo. Set
    ** nPhrase to contain the number of reportable phrases in the users full-text
    ** query, and nCol to the number of columns in the table.
    */
    $aMatchInfo = (string) func_get_arg(0);
    $nPhrase = ord(substr($aMatchInfo, 0, $iSize));
    $nCol = ord(substr($aMatchInfo, $iSize, $iSize));

    if (func_num_args() > (1 + $nCol))
    {
        throw new Exception("Invalid number of arguments : ".$nCol);
    }

    // Iterate through each phrase in the users query. //
    for ($iPhrase = 0; $iPhrase < $nPhrase; $iPhrase++)
    {
        $iCol = (int) 0; // Current column //

        /* Now iterate through each column in the users query. For each column,
        ** increment the relevancy score by:
        **
        **   (<hit count> / <global hit count>) * <column weight>
        **
        ** aPhraseinfo[] points to the start of the data for phrase iPhrase. So
        ** the hit count and global hit counts for each column are found in
        ** aPhraseinfo[iCol*3] and aPhraseinfo[iCol*3+1], respectively.
        */
        $aPhraseinfo = substr($aMatchInfo, (2 + $iPhrase * $nCol * 3) * $iSize);

        for ($iCol = 0; $iCol < $nCol; $iCol++)
        {
            $nHitCount = ord(substr($aPhraseinfo, 3 * $iCol * $iSize, $iSize));
            $nGlobalHitCount = ord(substr($aPhraseinfo, (3 * $iCol + 1) * $iSize, $iSize));
            $weight = ($iCol < func_num_args() - 1) ? (double) func_get_arg($iCol + 1) : 0;

            if ($nHitCount > 0)
            {
                $score += ((double)$nHitCount / (double)$nGlobalHitCount) * $weight;
            }
        }
    }

    return $score;
}

