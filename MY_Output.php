<?php

class MY_Output extends CI_Output {

	public function __construct()
	{
			parent::__construct();
	}


	// --------------------------------------------------------------------

	/**
	 * Write Cache
	 *
	 * @param	string	$output	Output data to cache
	 * @return	void
	 */
	public function _write_cache($output)
	{
		$CI =& get_instance();
		$path = $CI->config->item('cache_path');
		$cache_path = ($path === '') ? APPPATH.'cache/' : $path;

		if ( ! is_dir($cache_path) OR ! is_really_writable($cache_path))
		{
			log_message('error', 'Unable to write cache file: '.$cache_path);
			return;
		}

		$uri = $CI->config->item('base_url')
			.$CI->config->item('index_page')
			.$CI->uri->uri_string();

		if (($cache_query_string = $CI->config->item('cache_query_string')) && ! empty($_SERVER['QUERY_STRING']))
		{
			if (is_array($cache_query_string))
			{
				$uri .= '?'.http_build_query(array_intersect_key($_GET, array_flip($cache_query_string)));
			}
			else
			{
				$uri .= '?'.$_SERVER['QUERY_STRING'];
			}
		}

		// BEGIN --- modification for supporting multi-level cache folders

        if (is_array($CI->config->item('multi_level_cache_folders')))
        {
            $uri_md5 = md5($uri);
            $layers = $CI->config->item('multi_level_cache_folders');
            $layer = $this->get_folder_layers($uri_md5, $layers);
            $cache_path .= implode('/' ,  $layer) . '/';

            if (!is_dir($cache_path))
            {
                $old = umask(0);
                mkdir($cache_path, 0777, true);
                umask($old);
            }
        }

        // END --- modification for supporting multi-level cache folders

		$cache_path .= md5($uri);

		if ( ! $fp = @fopen($cache_path, 'w+b'))
		{
			log_message('error', 'Unable to write cache file: '.$cache_path);
			return;
		}

		if (flock($fp, LOCK_EX))
		{
			// If output compression is enabled, compress the cache
			// itself, so that we don't have to do that each time
			// we're serving it
			if ($this->_compress_output === TRUE)
			{
				$output = gzencode($output);

				if ($this->get_header('content-type') === NULL)
				{
					$this->set_content_type($this->mime_type);
				}
			}

			$expire = time() + ($this->cache_expiration * 60);

			// Put together our serialized info.
			$cache_info = serialize(array(
				'expire'	=> $expire,
				'headers'	=> $this->headers
			));

			$output = $cache_info.'ENDCI--->'.$output;

			for ($written = 0, $length = strlen($output); $written < $length; $written += $result)
			{
				if (($result = fwrite($fp, substr($output, $written))) === FALSE)
				{
					break;
				}
			}

			flock($fp, LOCK_UN);
		}
		else
		{
			log_message('error', 'Unable to secure a file lock for file at: '.$cache_path);
			return;
		}

		fclose($fp);

		if (is_int($result))
		{
			chmod($cache_path, 0640);
			log_message('debug', 'Cache file written: '.$cache_path);

			// Send HTTP cache-control headers to browser to match file cache settings.
			$this->set_cache_header($_SERVER['REQUEST_TIME'], $expire);
		}
		else
		{
			@unlink($cache_path);
			log_message('error', 'Unable to write the complete cache content at: '.$cache_path);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Update/serve cached output
	 *
	 * @uses	CI_Config
	 * @uses	CI_URI
	 *
	 * @param	object	&$CFG	CI_Config class instance
	 * @param	object	&$URI	CI_URI class instance
	 * @return	bool	TRUE on success or FALSE on failure
	 */
	public function _display_cache(&$CFG, &$URI)
	{
		$cache_path = ($CFG->item('cache_path') === '') ? APPPATH.'cache/' : $CFG->item('cache_path');

		// Build the file path. The file name is an MD5 hash of the full URI
		$uri = $CFG->item('base_url').$CFG->item('index_page').$URI->uri_string;

		if (($cache_query_string = $CFG->item('cache_query_string')) && ! empty($_SERVER['QUERY_STRING']))
		{
			if (is_array($cache_query_string))
			{
				$uri .= '?'.http_build_query(array_intersect_key($_GET, array_flip($cache_query_string)));
			}
			else
			{
				$uri .= '?'.$_SERVER['QUERY_STRING'];
			}
		}

		// BEGIN --- modification for supporting multi-level cache folders

        if (is_array($CFG->item('multi_level_cache_folders')))
        {
            $uri_md5 = md5($uri);
            $layers = $CFG->item('multi_level_cache_folders');
            $layer = $this->get_folder_layers($uri_md5, $layers);
            $cache_path .= implode('/' ,  $layer) . '/';

            if (!is_dir($cache_path))
            {
                return FALSE;
            }
        }

        // END --- modification for supporting multi-level cache folders


		$filepath = $cache_path . md5($uri);

		if ( ! file_exists($filepath) OR ! $fp = @fopen($filepath, 'rb'))
		{
			return FALSE;
		}

		flock($fp, LOCK_SH);

		$cache = (filesize($filepath) > 0) ? fread($fp, filesize($filepath)) : '';

		flock($fp, LOCK_UN);
		fclose($fp);

		// Look for embedded serialized file info.
		if ( ! preg_match('/^(.*)ENDCI--->/', $cache, $match))
		{
			return FALSE;
		}

		$cache_info = unserialize($match[1]);
		$expire = $cache_info['expire'];

		$last_modified = filemtime($filepath);

		// Has the file expired?
		if ($_SERVER['REQUEST_TIME'] >= $expire && is_really_writable($cache_path))
		{
			// If so we'll delete it.
			@unlink($filepath);
			log_message('debug', 'Cache file has expired. File deleted.');
			return FALSE;
		}
		else
		{
			// Or else send the HTTP cache control headers.
			$this->set_cache_header($last_modified, $expire);
		}

		// Add headers from cache file.
		foreach ($cache_info['headers'] as $header)
		{
			$this->set_header($header[0], $header[1]);
		}

		// Display the cache
		$this->_display(substr($cache, strlen($match[0])));
		log_message('debug', 'Cache file is current. Sending it to browser.');
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Delete cache
	 *
	 * @param	string	$uri	URI string
	 * @return	bool
	 */
	public function delete_cache($uri = '')
	{
		$CI =& get_instance();
		$cache_path = $CI->config->item('cache_path');
		if ($cache_path === '')
		{
			$cache_path = APPPATH.'cache/';
		}

		if ( ! is_dir($cache_path))
		{
			log_message('error', 'Unable to find cache path: '.$cache_path);
			return FALSE;
		}

		if (empty($uri))
		{
			$uri = $CI->uri->uri_string();

			if (($cache_query_string = $CI->config->item('cache_query_string')) && ! empty($_SERVER['QUERY_STRING']))
			{
				if (is_array($cache_query_string))
				{
					$uri .= '?'.http_build_query(array_intersect_key($_GET, array_flip($cache_query_string)));
				}
				else
				{
					$uri .= '?'.$_SERVER['QUERY_STRING'];
				}
			}
		}

        // BEGIN --- modification for supporting multi-level cache folders

        $uri = $CI->config->item('base_url').$CI->config->item('index_page').ltrim($uri, '/');

        if (is_array($CI->config->item('multi_level_cache_folders')))
        {
            $uri_md5 = md5($uri);
            $layers = $CI->config->item('multi_level_cache_folders');
            $layer = $this->get_folder_layers($uri_md5, $layers);
            $cache_path .= implode('/' ,  $layer) . '/';

            if (!is_dir($cache_path))
            {
                return FALSE;
            }
        }

        $cache_path .= md5($uri);

        // END --- modification for supporting multi-level cache folders

		if ( ! @unlink($cache_path))
		{
			log_message('error', 'Unable to delete cache file for '.$uri);
			return FALSE;
		}

		return TRUE;
	}
    /**
     * @param $md5
     * @return array
     */
    function get_folder_layers($md5, $layers)
    {
        $layer = array();
        $last_key = 0;
        $last_value = 0;

        foreach ($layers AS $key => $value)
        {
            if ($key != 0)
            {
                $key = $last_key + $last_value;
            }
            $layer[] = substr($md5, $key, $value);

            $last_key   = (int) $key;
            $last_value = (int) $value;
        }
        return $layer;
    }
}
