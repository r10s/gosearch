<?php
/*******************************************************************************
Go Search Engine - Optional configuration
********************************************************************************

The file `config/config.php` contains the basic configuration; if the file does 
not exist, copy `config/config-sample.php` to `config/config.php`.  For a 
"normal" installation, this step is not needed and the system will use the 
default values instead.

*******************************************************************************/



/*******************************************************************************
The directory to be used for the .sqlite files and others. The directory must be
writable and should be defined **without** a trailing slash.  If possible, move 
the data directory outside `htdocs`.
If you change this value, you can simply move the files from the old to the new
directory without data loss. 

By default, the directory is set inside `htdocs` to G_DOCUMENT_ROOT . '/data'
*******************************************************************************/
//define('G_DATA_ROOT', G_DOCUMENT_ROOT . '/data');



/*******************************************************************************
By default, the cron job is triggered whenever any user uses the search engine.
The advantage is zero configuration, however, if no one uses the search engine, 
no crawling takes place.

If - and only if - you call ?a=cron from a real cron job (eg. every minute, 
depending on the following setting), you can disable the browser-based cron job
below.
*******************************************************************************/
//define('G_DISABLE_BROWSER_BASED_CRON', false);



/*******************************************************************************
If - and only if - your server administrator allows PHP scripts to use 
set_time_limit() to set a longer execution time, you can use this directive to
use a longer cron execution time. This does **not** work on many shared or 
managed server! 
*******************************************************************************/
//define('G_OVERWRITE_MAX_EXECUTION_TIME', 30);



/*******************************************************************************
Here you can define a comma-separated list of predefined hosts for the 
peer-to-peer search.
*******************************************************************************/
//define('G_HOSTS', '');


/*******************************************************************************
To use another tile server for the openstreetmap data, put in the server here,
usually in the form http://{s}.tiles.domain.com/{z}/{x}/{y}.png . If the usage
policy requires attribution, place it at G_TILE_ATTRIBUTION.

If you leave these settings out, the default tile server is used.
*******************************************************************************/
//define('G_TILE_SERVER', '');
//define('G_TILE_ATTRIBUTION', '');



/*******************************************************************************
To use another Nominatim server (for geocoding), put in the server here,
usually in the form http://nominatim.domain.com/search (without trailing
slash). If the usage policy  requires attribution, place it at 
G_NOMINATIM_ATTRIBUTION.

If you leave these settings out, the default tile server is used.
*******************************************************************************/
//define('G_NOMINATIM_SERVER', '');
//define('G_NOMINATIM_ATTRIBUTION', '');

