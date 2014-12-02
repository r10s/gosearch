<?php
/*******************************************************************************
Go Search Engine - Stress tests
****************************************************************************//**

@author Björn Petersen

*******************************************************************************/

class G_Stress 
{
	/***********************************************************************//**
	Peform some tests (first the most generic tests, following specific tests).
	
	Take care, that, if possible, the tests do not invoke any side effects, eg.
	affecting global or static variables in a non-init-only way - otherwise we 
	may get the situation sth. works with these test, but not without!
	***************************************************************************/
	static function doAllTests()
	{
		// we do not want to use $_REQUEST - check if we have reset the array
		// =====================================================================
		
		assert( $_GET['a'] === 'adminetc' );
		assert( !isset($_POST['a']) ); 
		if( defined('G_DEBUG') && constant('G_DEBUG') ) {
			assert( !isset($_REQUEST['a']) ); // in non-debug, we do not reset the array
		}

		
		// test G_IPC
		// =====================================================================
		
		$ob = new G_IPC('badid'); // only characters [a-fA-F0-9] are allowed
		assert( !$ob->ok() );
		assert( $ob->getErr() === 'err_badid (syntax)' );
		
		$ob = new G_IPC('1234567890123456789012345678901234567890123456789012345678901234567890'); // max. 64 characters are allowed for an ID
		assert( !$ob->ok() );
		assert( $ob->getErr() === 'err_badid (syntax)' );

		$ob = new G_IPC('123'); // min. 4 characters are needed for an ID
		assert( !$ob->ok() );
		assert( $ob->getErr() === 'err_badid (syntax)' );
		
		
		// test preg_match
		// =====================================================================
		
		$pattern = '#(^begin|mid|end$)#'; // okay, just for me to test if `^` and `$` can be used inside `|`
		assert(  preg_match($pattern, 'beginning') );
		assert( !preg_match($pattern, 'begn') );
		assert( !preg_match($pattern, 'dasende') );
		assert(  preg_match($pattern, 'dasend') );
		assert(  preg_match($pattern, 'diemidde') );
		assert( !preg_match($pattern, 'wasanderes') );
		
		$pattern = '(^eins|^zwei$)'; // `^` may not be used outside the brackets
		assert(  preg_match($pattern, 'eins') );
		assert(  preg_match($pattern, 'einsweiter') );
		assert(  preg_match($pattern, 'zwei') );
		assert( !preg_match($pattern, 'zweiweiter') );

		
		// test G_Normalize
		// =====================================================================
		
		$ob1 = new G_Normalize(0);
		$ob2 = new G_Normalize(G_Normalize::KEEP_SYMBOLS);
		$ob3 = new G_Normalize(G_Normalize::KEEP_NUMBERS);
		$ob4 = new G_Normalize(G_Normalize::KEEP_SYMBOLS | G_Normalize::KEEP_NUMBERS);
		
		$a = " \n\t\t  x-y ABCdefÄÖÜäöüß. \t 1234567890? \"€£¥#\" \n";
		assert( $ob1->normalize($a) === 'x y abcdefäöüäöüß' );
		assert( $ob2->normalize($a) === 'x y abcdefäöüäöüß € £ ¥ #' );
		assert( $ob3->normalize($a) === 'x y abcdefäöüäöüß 1234567890' );
		assert( $ob4->normalize($a) === 'x y abcdefäöüäöüß 1234567890 € £ ¥ #' );
		

		// test G_String::tolower(), toupper(), ord();
		// =====================================================================

		$a = 'äöüabcß';
		$b = 'ÄÖÜabcß';
		assert( G_String::tolower($a) === G_String::tolower($b) );
		assert( G_String::toupper($a) === G_String::toupper($b) );
		
		assert( G_String::ord("") === 0 );   // error on input should return 0 by definition
		assert( G_String::ord("\0") === 0 ); // nullbyte input also returns 0
		assert( G_String::ord("\t") === 9 );
		assert( G_String::ord("\n") === 10 );
		assert( G_String::ord("\r") === 13 );
		assert( G_String::ord("0") === 48 );
		assert( G_String::ord('A') === 65 );
		assert( G_String::ord('ß') === 223 ); 
		assert( G_String::ord('ð') === 240 ); 
		assert( G_String::ord('ί') === 943 ); 
		assert( G_String::ord('€') === 8364 ); 
		assert( G_String::ord('票') === 31080 );

		
		// test G_String::vocabularyHash()
		// =====================================================================

		$a = 'björn Petersen';
		$b = 'Petersen, BJÖRN 1234';
		assert( G_String::vocabularyHash($a) === G_String::vocabularyHash($b) );
		assert( strlen(G_String::vocabularyHash($a)) >= 16 ); // test min. hash length
		assert( G_String::vocabularyHash('') === '' ); // empty input result in empty hash
		assert( G_String::vocabularyHash(0) === '' );  // empty input result in empty hash
		
		
		// test G_Url
		// =====================================================================

		$a = 'http://domain.com/path/?session=0123456789'; 
		$ob1 = new G_Url($a . '#hashesAreDiscarded');
		assert( $ob1->port === 80 );  // test for standard port
		assert( $ob1->getAbs() == $a ); // G_Url must not get rid of the session or any other parameters
		
		$ob1 = new G_Url('https://foo.com');  $a = $ob1->getAbs();
		$ob2 = new G_Url('https://foo.com/'); $b = $ob2->getAbs();
		assert( $ob1->port === 443 ); // test for standard port
		assert( $a === $b ); // the trailing slash after a domain is added implicit

		$a = 'https://othersslport.com:1234/testfile';
		$ob1 = new G_Url($a);
		assert( $ob1->scheme === 'https' );
		assert( $ob1->port === 1234 );
		assert( $ob1->getAbs() === $a );

		$a = 'http://othernonsslport.com:5678/testfile';
		$ob1 = new G_Url($a);
		assert( $ob1->scheme === 'http' );
		assert( $ob1->port === 5678 );
		assert( $ob1->getAbs() === $a );

		$ob1 = new G_Url('https://foo.com/bar');  $a = $ob1->getAbs();
		$ob2 = new G_Url('https://foo.com/bar/'); $b = $ob2->getAbs();
		assert( $a != $b ); // the trailing slash after a path is important
		
		$ob1->change('file.txt'); $a = $ob1->getAbs();
		$ob2->change('file.txt'); $b = $ob2->getAbs();
		assert( $a === 'https://foo.com/file.txt' && $b === 'https://foo.com/bar/file.txt' );

		$a = 'https://us:er@test.se/testpath/testfile.testext'; // G_HttpRequst and others may not support http-auth, however, G_Url should handle this properly
		$ob1 = new G_Url($a . '#hashesAre_Still_Discarded');
		assert( $ob1->user === 'us' );
		assert( $ob1->pass === 'er' );
		assert( $ob1->port === 443 );
		assert( $ob1->scheme === 'https' );
		assert( $ob1->getAbs() === $a );

		$a = 'https://us:er@test.se:23451/testpath/testfile.testext'; // test SSL on another port - G_Url should not confuse pass/port which are both divided by a colon
		$ob1 = new G_Url($a . '#hashesAre_Still_Discarded');
		assert( $ob1->user === 'us' );
		assert( $ob1->pass === 'er' );
		assert( $ob1->port === 23451 );
		assert( $ob1->scheme === 'https' );
		assert( $ob1->getAbs() === $a );
		
		$ob1 = new G_Url('httpx://noport.com');
		assert( $ob1->error != '' ); // this should be an error, at least because we do not get the default port
		
		$ob1 = new G_Url();
		assert( $ob1->error === 'err_unset' ); 	
		$ob1->setAbs('www.noscheme.com/path'); // on creation/setting as abolute, we implicit assume the scheme and the port ...
		assert( $ob1->scheme === 'http' ); 
		assert( $ob1->port === 80 ); 
		assert( $ob1->host === 'www.noscheme.com' ); 
		assert( $ob1->error === '' ); 
		$ob1->change('www.test.de'); // ... but not on change! this results in `www.noscheme.com/www.test.de` - anything else would let run us into problems
		assert( $ob1->scheme === 'http' ); 
		assert( $ob1->port === 80 ); 
		assert( $ob1->host === 'www.noscheme.com' ); 
		assert( $ob1->path === '/www.test.de' ); 
		assert( $ob1->getAbs() === 'http://www.noscheme.com/www.test.de' ); 
		assert( $ob1->error === '' ); 	
		$ob1->change('http://www.test.de');		
		assert( $ob1->getAbs() === 'http://www.test.de/' ); 
		assert( $ob1->error === '' ); 		
		
		
		// test Crawler_RobotsTxtFile
		// =====================================================================
		
		$ob = new Crawler_RobotsTxtFile('foobar');
		//$ob->setFromUrl(new G_Url("http://www.google.de/robots.txt"));
		$ob->setFromStr("
			User-AGENT : Dummy
			Disallow: 
			
			# just a comment
			User-agent: *
			Disallow:              /dir/disdir # comment 
			\n\r \t Disallow\t: \t /otherdir
			Disallow:			   /(*) # make sure, the brackets are masked correctly in the regex (NB: [] and {} are no valid URL characters at all)
			Disallow:			   /*end$
			Disallow:              /%
			
			User-agent: fux
			Allow:    /otherdir
			Disallow: /testdis/
			Allow:    /testdis/exception
			Disallow:  /wild/*.html	
			Disallow:  /xy/*.ext
			Allow :    /xy/*.ext
			Disallow: /删除投票/侵权 # just to check, not the whole systems fails with some Unicode (however, these characters are invalid in URLs and should normally be encoded using %NN!)
			
			Allow: /abc/
			Disallow: /abc/def/
			
			# tests from https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
			User-agent: gggl
			
			disallow: /
			allow:    /p
			
			User-agent: gggl2
			disallow: /folder
			allow:    /folder/
			
			disallow: /*.htm
			allow:    /page
		");
		
		$ob->setUserAgent('foobar'); // this will use the ruled for user agent `*`
		assert(  $ob->urlAllowed(new G_Url('http://c.com/test')) );
		assert(  $ob->urlAllowed(new G_Url('http://c.com/dir')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/dir/disdir')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/dir/disdir/')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/dir/disdir/ddd')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/dir/disdireee')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/otherdir')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/otherdirfff')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/(cd)')) );
		assert(  $ob->urlAllowed(new G_Url('http://c.com/foo.ende')) ); // check if the $ (end of line) operator works
		assert( !$ob->urlAllowed(new G_Url('http://c.com/foo.end')) );  // check if the $ (end of line) operator works

		$ob->setUserAgent('fux');
		assert(  $ob->urlAllowed(new G_Url('http://c.com/otherdir')) );
		assert(  $ob->urlAllowed(new G_Url('http://c.com/testdis')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/testdis/')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/testdis/subdir')) );
		assert(  $ob->urlAllowed(new G_Url('http://c.com/testdis/exception')) );
		assert( !$ob->urlAllowed(new G_Url('http://c.com/wild/file.html')) );
		assert(  $ob->urlAllowed(new G_Url('http://c.com/wild/file.txt')) );
		assert(  $ob->urlAllowed(new G_Url('http://c.com/xy/allow.ext')) ); // matches two rules, should be allowed as both rules are of equal length
		assert(  $ob->urlAllowed(new G_Url('http://c.com/xy/allowyext')) ); // make sure the dot does not match _any_ character (as usual in regexpr, but but with robots-wildcards)
		assert(  $ob->urlAllowed(new G_Url('http://c.com/abc/')) );
		
		$ob->setUserAgent('gggl');
		assert( !$ob->urlAllowed(new G_Url('http://example.com/xpage')) );
		assert(  $ob->urlAllowed(new G_Url('http://example.com/page')) );
		
		$ob->setUserAgent('gggl2');
		assert(  $ob->urlAllowed(new G_Url('http://example.com/folder/page')) );
		assert(  $ob->urlAllowed(new G_Url('http://example.com/page.htm')) ); // this is undefined, however, we allow it
		
		
		// test G_TokenizeHelper
		// =====================================================================
		
		$ob = new G_TokenizeHelper();
		assert( $ob->searchify(" Björn \t\n\r Nuß x-y") === 'Bjoern Nuss x-y' ); 
		
		assert( $ob->asciify("\n Björn \t\n\r Nuß   x-y  ") === '{:|_+}Bjoern {:|(_}Nuss x-y' );
		
		assert( $ob->unasciify($ob->asciify("\n\r\ttest")) === "test" ); // make sure, threre are no tabs/newline in the snippets/titles - lookup relies on this!
		
		$a = '{}';
		assert( $ob->unasciify($ob->asciify($a)) === $a );
		
		assert( $ob->unasciify($ob->asciify(" <html> & \" ")) === "&lt;html&gt; &amp; &quot;" ); // we convert to HTML in the same step as snippets are already HTML'd 
		
		$a = "Björn Nuß ἀί ð Ð";
		$b = $ob->asciify($a);
		assert( $b === '{:|_+}Bjoern {:|(_}Nuss {:|;=_|.|,_..}αι ð {.|(.}ð' );
		assert( $ob->unasciify($b) === $a );
		
		
		// test G_Lang
		// =====================================================================
		
		assert( G_Lang::str2id('') === 0 );
		assert( G_Lang::str2id('d') === 0 );
		assert( G_Lang::str2id('DeEgalWasNochKommt') === 25701 );
		assert( G_Lang::str2id('de') === 25701 );
		
		$ok = G_Lang::isValidLang('de;xx', $lang, $region);				assert( $ok && $lang == 'de' && $region == '' );
		$ok = G_Lang::isValidLang('en-gb', $lang, $region);				assert( $ok && $lang == 'en' && $region == 'GB' );
		$ok = G_Lang::isValidLang('en-US;q=12', $lang, $region);		assert( $ok && $lang == 'en' && $region == 'US' ); // q is simply ignored
		$ok = G_Lang::isValidLang('xxxx,de,en;en-US', $lang, $region);	assert( $ok && $lang == 'de' && $region == '' ); // bad and subsequent languages are simply ignored
		$ok = G_Lang::isValidLang('xxx', $lang, $region);				assert( $ok && $lang == 'xxx' && $region == '' );
		$ok = G_Lang::isValidLang('xxxx', $lang, $region);				assert( !$ok && $lang == '' && $region == '' );
		$ok = G_Lang::isValidLang('aaa-bbb', $lang, $region);			assert( $ok && $lang == 'aaa' && $region == 'BBB' );
		$ok = G_Lang::isValidLang('cc-dddddd', $lang, $region);			assert( !$ok && $lang == '' && $region == '' );
		$ok = G_Lang::isValidLang('xxxx,deutsch', $lang, $region);		assert( $ok && $lang == 'de' && $region == '' ); // stub replacements
		$ok = G_Lang::isValidLang('english;q=0.8', $lang, $region);		assert( $ok && $lang == 'en' && $region == '' ); // stub replacements
	}
};
