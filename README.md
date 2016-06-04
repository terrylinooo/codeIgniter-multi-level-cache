# CodeIgniter-Multi-level-Cache
Simple modification makes the cache files store to multi-level sub-folders.
------------------------------------

Codeigniter's file-based caching system will take the completely rendered output for HTML and SQL object, when an user visit your web page, Codeigniter will load the data from cache files without MySQL connections, it reduces the server CPU loading a lot. But If you have 100K cache files in the same folder, it may have performace problem.

If you have troble about too many files in cache folder, here is a solution for you, it is easy and simple.


###Installation###

