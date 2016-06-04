# CodeIgniter Multi-level Cache
####Simple modification makes the cache files store to multi-level sub-folders.
------------------------------------

Codeigniter's file-based caching system will take the completely rendered output for HTML and SQL object, when an user visit your web page, Codeigniter will load the data from cache files without MySQL connections, it reduces the server CPU loading a lot. But If you have 100K cache files in the same folder, it may have performace problem.

If you have troble about too many files in a cache folder, here is a solution for you, it is easy and simple.

------------------------------------
##Installation##

*Web Page Cache*

1. Copy `MY_Output.php` to `/application/core/` folder, that's all.


*SQL Object Cache*

1. Copy `MY_Loader.php` to `/application/core/` folder.
2. Copy `MY_DB_cache.php` to  `/application/libraries/` folder.

------------------------------------
##Setup##

Add config varible `multi_level_cache_folders` to the `config.php` or load it directly in Controller, for example:

```php
$folder_array = array(2,2);
$this->config->set_item('multi_level_cache_folders', $folder_array);
```
If the cache file name is `b2690d029271df88fe87305766f95db4`, it will be stored at: `cache/b2/69/b2690d029271df88fe87305766f95db4`


Example 2:
```php
// in config.php
$config['multi_level_cache_folders'] = array(3,1,2);
```
The cache file will be stored at `cache/b26/9/0d/b2690d029271df88fe87305766f95db4`




