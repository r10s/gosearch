
Go Search Engine
================

A distributed search engine.  Open source, free, non-tracking, slow - and not
yet ready.  The program will need lots of additional development; currently
it is not clear if will work at all.

Howver, the first steps are donw and the crawler seems to hang less often ;-)


Requirements
------------

A server with PHP 5.1.2 or newer with pdo_sqlite enabled or loadable via dl().
This is true for most servers and even for shared webspaces.


Installation
------------

1. Define where the search engine should be accessed on your server, we would 
   encourage you to use a subdomain as "go.yourdomain.tld".  However, a 
   subdirectory as "www.yourdomain.tld/go/" will work as well.

2. Unzip the downloaded file to the directory corresponding to the place
   defined above; FTP may help with this purpose.  Take care, the directory 
   structure stays intact.  The destination folder should contain the
   directories `config`, `data` and `program` as well as the files `index.php`, 
   `robots.txt`, `LICENSE.md` and this readme.
   
3. Make sure, the directory /data is **writable from PHP**, again, FTP may help 
   with this purpose.

4. Open your search engine in the browser and click onto "login" to create an
   administration account.
   
That's all. You can now start configuring and using your search engine.


Updates
-------

For updates, just replace the `/program` folder with the new version.

