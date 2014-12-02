<?php 
/*******************************************************************************
Go Search Engine - Directory functions
****************************************************************************//**

Directory functions

@author Björn Petersen

*******************************************************************************/


 
class G_Dir
{
	/***********************************************************************//**
	Same as filesize() but with a fallback for file sizes > 2 GB on 32 bit 
	machines. 
	
	The function returns an integer or a float for larger files; on errors, 
	`false` is returned.
	***************************************************************************/
	static function bigFilesize($path)
	{
		// first, try the normal filesize()
		$size = @filesize($path);
		if( $size > 0 && $size !== false ) {
			return $size; // success
		}

		// filesize() failed: get the size by seeking and counting.
		// "This starts out by skipping ~1GB at a time, reads a character in, 
		// repeats.  When it gets into the last GB, it halves the size whenever 
		// the read fails.  The last couple of bytes are just read in."
		// (from http://php.net/manual/en/function.filesize.php#113457 )
		if( ($fp = @fopen($path, 'r')) === false ) {	
			return false; // error
		}
		$size = 0;
		$test = 1073741824;
		fseek($fp, 0, SEEK_SET);
		while ($test > 1) {
			fseek($fp, $test, SEEK_CUR);
			if( fgetc($fp) === false ) {
				fseek($fp, -$test, SEEK_CUR);
				$test = (int)($test / 2);
			}
			else {
				fseek($fp, -1, SEEK_CUR);
				$size += $test;
			}
		}

		while (fgetc($fp) !== false) {
			$size++;
		}
		
		fclose($fp);
		return $size; // success
	}

	static private function recursiveInfo__($start_folder, &$di)
	{
		// if the path has a slash at the end we remove it here
		if(substr($start_folder, -1) == '/') { $start_folder = substr($start_folder, 0, -1); }
		
		// go through all files in the directory
		$subdirs = array();
		$handle = @opendir($start_folder);
		if( $handle ) 
		{
			while( $entry_name = readdir($handle) ) 
			{
				if( substr($entry_name, 0, 1) == '.' ) {
					continue; // skip the files "." and ".." and all hidden files
				}
				
				$entry_fullpath = $start_folder . '/' . $entry_name;
				
				if( @is_file($entry_fullpath) )
				{
					$fs = G_Dir::bigFilesize($entry_fullpath);
					if( $fs !== false ) {
						$di['bytes'] += $fs;
					}
				}
				else if( @is_dir($entry_fullpath) )
				{
					$subdirs[] = $entry_fullpath;
				}
			}
			
			closedir($handle);
		}
		
		// do the recursion - we do this after closedir() to avoid too many directory handles left open
		foreach( $subdirs as $entry_fullpath )
		{
			G_DIR::recursiveInfo__($entry_fullpath, $di);
		}
	}
	
	static function recursiveInfo($dir)
	{
		$di = array();
		$di['bytes'] = 0;
		
		G_DIR::recursiveInfo__($dir, $di);
		
		return $di;
	}
};
