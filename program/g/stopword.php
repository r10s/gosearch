<?php
/*******************************************************************************
Go Search Engine - Stopword handling
****************************************************************************//**

Stopword handling

@author Björn Petersen

*******************************************************************************/

class G_Stopword
{
	static private $stopwords = false;
	
	
	/***********************************************************************//**
	Get an hash of stopwords as word=>1
	
	@param	$lang		String; the language to get the stopwords for.  If
						unknown, stopwords for *all* known languages are 
						returned.  The language may also be given as 
						`de-DE,en-US` etc. - in this case, the first language 
						is used.
	@returns array		Hash of stopwords
	***************************************************************************/
	function get_stopwords_by_lang($lang)
	{
		$lang = str_replace(',', '-', $lang);
		list($lang) = explode('-', $lang);
		$lang = trim(strtolower($lang));
		
		if( !is_array(G_Stopword::$stopwords[ $lang ]) )
		{
			// load the stopwords form /program/lib/stopwords/stopwords_<lang>.txt
			G_Stopword::$stopwords[ $lang ] = array();
			$filename = G_DOCUMENT_ROOT . '/program/lib/stopwords/stopwords_' . $lang . '.txt';
			if( @file_exists($filename) ) {
				// we have a stopword list for the given language; fine.
				G_Stopword::$stopwords[ $lang ] = $this->get_stopword_contents($filename, $lang);
			}
			else {
				// no stopwords for this language, this is no error, we simply return an empty array
				G_Stopword::$stopwords[ $lang ] = array();
			}
		}
		
		return G_Stopword::$stopwords[ $lang ];
	}
	
	/***********************************************************************//**
	Get all stopwords as an hash word=>array(lang1, lang2, ...)
	 
	@returns 	hash of stopwords
	***************************************************************************/
	function get_all_stopwords()
	{
		if( !is_array(G_Stopword::$stopwords['__all__']) )
		{
			$all = array();
				$dirname = G_DOCUMENT_ROOT . '/program/lib/stopwords/';
				$handle = @opendir($dirname);
				if( $handle ) {
					while( $entry_name = readdir($handle) ) {
						if( preg_match('/stopwords_([a-zA-Z0-9_\-]+)\.txt/', $entry_name, $matches) ) {
							$lang = $matches[1];
							$lang_stopwords = $this->get_stopwords_by_lang($lang);
							foreach( $lang_stopwords as $word=>$dummy ) {
								$all[ $word ][] = $lang;
							}
						}
					}
					closedir($handle);
				}
			G_Stopword::$stopwords['__all__'] = $all;
		}
		
		return G_Stopword::$stopwords['__all__'];
	}
	
	private function get_stopword_contents($filename)
	{
		$ret = array();
		$lines = explode("\n", @file_get_contents($filename));
		foreach( $lines as $line ) {
			if( ($p=strpos($line, '#')) !== false ) { $line = substr($line, 0, $p); }
			if( ($p=strpos($line, '|')) !== false ) { $line = substr($line, 0, $p); }
			if( $line != '' ) {
				$line = G_String::normalize($line);
				if( $line != '' ) {
					$ret[ $line ] = 1;
				}
			}
		} 
		return $ret;
	}	
	
};
