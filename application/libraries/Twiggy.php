<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Twiggy - Twig template engine implementation for CodeIgniter
 *
 * Twiggy is not just a simple implementation of Twig template engine
 * for CodeIgniter. It supports themes, layouts, templates for regular
 * apps and also for apps that use HMVC (module support).
 *
 * @package   			CodeIgniter
 * @subpackage			Twiggy
 * @category  			Libraries
 * @author    			Edmundas Kondrašovas <as@edmundask.lt>
 * @license   			http://www.opensource.org/licenses/MIT
 * @version   			0.8.5 modifed
 * @copyright 			Copyright (c) 2012 Edmundas Kondrašovas <as@edmundask.lt>
 */

require_once(APPPATH . 'vendor/autoload.php');

class Twiggy
{
	private $CI;

	private $_config = array();
	private $_template_locations = array();
	private $_data = array();
	private $_globals = array();
	private $_themes_base_dir;
	private $_theme;
	private $_layout;
	private $_template;
	private $_twig;
	private $_twig_loader;
	private $_module;
	private $_meta = array();
	private $_rendered = FALSE;

	/**
	* Constructor
	*/

	public function __construct()
	{
		log_message('debug', 'Twiggy: library initialized');

		$this->CI =& get_instance();

		$this->_config = $this->CI->config->item('twiggy');

		$this->_themes_base_dir = ($this->_config['include_apppath']) ? APPPATH . $this->_config['themes_base_dir'] : $this->_config['themes_base_dir'];
		
		try
		{
			$this->_twig_loader = new Twig_Loader_Filesystem($this->_template_locations);
		}
		catch(Twig_Error_Loader $e)
		{
			log_message('error', 'Twiggy: failed to load the default theme');
			show_error($e->getRawMessage());
		}

		// Decide whether to enable Twig cache. If it is set to be enabled, then set the path where cached files will be stored.
		$this->_config['environment']['cache'] = ($this->_config['environment']['cache']) ? $this->_config['twig_cache_dir'] : FALSE;

		$this->_twig = new Twig_Environment($this->_twig_loader, $this->_config['environment']);

		
		// Initialize defaults
		$this->theme($this->_config['default_theme'])
			 ->layout($this->_config['default_layout'])
			 ->template($this->_config['default_template']);
		
		//debug mode ?
		if ($this->_config['environment']['debug']) {
			$this->_twig->addExtension(new Twig_Extension_Debug());			
		}

		$this->_globals['title'] = NULL;
		$this->_globals['meta'] = NULL;
	}

	/**
	 * Set data
	 *
	 * @access	public
	 * @param 	mixed  	key (variable name) or an array of variable names with values
	 * @param 	mixed  	data
	 * @param 	boolean	(optional) is this a global variable?
	 * @return	object 	instance of this class
	 */

	public function set($key, $value = NULL, $global = FALSE)
	{
		if(is_array($key))
		{
			foreach($key as $k => $v) $this->set($k, $v, $global);
		}
		else
		{
			if($global)
			{				
				$this->_globals[$key] = $value;
			}
			else
			{
			 	$this->_data[$key] = $value;
			}
		}

		return $this;
	}

	/**
	 * Unset a particular variable
	 *
	 * @access	public
	 * @param 	mixed  	key (variable name)
	 * @return	object 	instance of this class
	 */

	public function unset_data($key)
	{
		if(array_key_exists($key, $this->_data)) unset($this->_data[$key]);

		return $this;
	}

	/**
	 * Set title
	 *
	 * @access	public
	 * @param 	string
	 * @return	object 	instance of this class
	 */

	public function title()
	{
		if(func_num_args() > 0)
		{
			$args = func_get_args();

			// If at least one parameter is passed in to this method,
			// call append() to either set the title or append additional
			// string data to it.
			call_user_func_array(array($this, 'append'), $args);
		}

		return $this;
	}

	/**
	 * Append string to the title
	 *
	 * @access	public
	 * @param 	string
	 * @return	object 	instance of this class
	 */

	public function append()
	{
		$args = func_get_args();
		$title = implode($this->_config['title_separator'], $args);

		if(empty($this->_globals['title']))
		{
			$this->set('title', $title, TRUE);
		}
		else
		{
			$this->set('title', $this->_globals['title'] . $this->_config['title_separator'] . $title, TRUE);
		}

		return $this;
	}

	/**
	 * Prepend string to the title
	 *
	 * @access	public
	 * @param 	string
	 * @return	object 	instance of this class
	 */

	public function prepend()
	{
		$args = func_get_args();
		$title = implode($this->_config['title_separator'], $args);

		if(empty($this->_globals['title']))
		{
			$this->set('title', $title, TRUE);
		}
		else
		{
			$this->set('title', $title . $this->_config['title_separator'] . $this->_globals['title'], TRUE);
		}

		return $this;
	}

	/**
	 * Set title separator
	 *
	 * @access	public
	 * @param 	string	separator
	 * @return	object 	instance of this class
	 */

	public function set_title_separator($separator = ' | ')
	{
		$this->_config['title_separator'] = $separator;

		return $this;
	}

	/**
	 * Set meta data
	 *
	 * @access	public
	 * @param 	string	name
	 * @param	string	value
	 * @param	string	(optional) name of the meta tag attribute
	 * @return	object 	instance of this class
	 */

	public function meta($name, $value, $attribute = 'name')
	{
		$this->_meta[$name] = array('name' => $name, 'value' => $value, 'attribute' => $attribute);

		return $this;
	}

	/**
	 * Unset meta data
	 *
	 * @access	public
	 * @param 	string	(optional) name of the meta tag
	 * @return	object	instance of this class
	 */

	public function unset_meta()
	{
		if(func_num_args() > 0)
		{
			$args = func_get_args();

			foreach($args as $arg)
			{
				if(array_key_exists($arg, $this->_meta)) unset($this->_meta[$arg]);
			}
		}
		else
		{
			$this->_meta = array();
		}

		return $this;
	}

	/**
	 * Register a function in Twig environment
	 *
	 * @access	public
	 * @param 	string	the name of an existing function
	 * @return	object	instance of this class
	 */

	public function register_function($name)
	{
		$this->_twig->addFunction($name, new Twig_Function_Function($name));

		return $this;
	}

	/**
	 * Register a filter in Twig environment
	 *
	 * @access	public
	 * @param 	string	the name of an existing function
	 * @return	object	instance of this class
	 */

	public function register_filter($name)
	{
		$this->_twig->addFilter($name, new Twig_Filter_Function($name));

		return $this;
	}

	/**
	* Load theme
	*
	* @access	public
	* @param 	string	name of theme to load
	* @return	object	instance of this class
	*/

	public function theme($theme)
	{
		if(!is_dir(realpath($this->_themes_base_dir. $theme)))
		{
			log_message('error', 'Twiggy: requested theme '. $theme .' has not been loaded because it does not exist.');
			show_error("Theme does not exist in {$this->_themes_base_dir}{$theme}.");
		}

		$this->_theme = $theme;
		$this->_set_template_locations($theme);

		return $this;
	}

	/**
	 * Set layout
	 *
	 * @access	public
	 * @param 	string	name of the layout
	 * @return	object	instance of this class
	 */

	public function layout($name)
	{
		$this->_layout = $name;
		$this->_twig->addGlobal('_layout', '_layouts/'. $this->_layout . $this->_config['template_file_ext']);

		return $this;
	}

	/**
	 * Set template
	 *
	 * @access	public
	 * @param 	string	name of the template file
	 * @return	object	instance of this class
	 */

	public function template($name)
	{
		$this->_template = $name;

		return $this;
	}

	/**
	 * Compile meta data into pure HTML
	 *
	 * @access	private
	 * @return	string	HTML
	 */

	private function _compile_metadata()
	{
		$html = '';

		foreach($this->_meta as $meta)
		{
			$html .= $this->_meta_to_html($meta);
		}

		return $html;
	}

	/**
	 * Convert meta tag array to HTML code
	 *
	 * @access	private
	 * @param 	array 	meta tag
	 * @return	string	HTML code
	 */

	private function _meta_to_html($meta)
	{
		return "<meta " . $meta['attribute'] . "=\"" . $meta['name'] . "\" content=\"" . $meta['value'] . "\">\n";
	}

	/**
	 * Load template and return output object
	 *
	 * @access	private
	 * @return	object	output
	 */

	private function _load()
	{
		// Auto-register functions.		
		$function = get_defined_functions();
		$function = array_values($function['user']);
		$function = array_filter($function, function ($value) {
			//remove internal twig function
			return ( (strpos($value, 'twig_') === 0) OR (strpos($value, '_twig') === 0) ) ?  FALSE : TRUE;
		});

		$this->_twig->registerUndefinedFunctionCallback(function ($name) use ($function) {			
	    	if (in_array($name, $function))
	    	{	    		
	        	return new Twig_Function($name, $name);
	    	}	    	
	    	return false;
		});

		//free memory
		unset($function);

		if(isset($this->_config['register_functions'][0])) {		
			$this->_twig->registerUndefinedFunctionCallback(function ($name) {
		    	if (in_array($name, $this->_config['register_functions']))
		    	{
		        	return new Twig_Function($name, $name);
		    	}
		    	return false;
			});
		}

		if(isset($this->_config['register_filters'][0]))
		{			
			$this->_twig->registerUndefinedFilterCallback(function ($name) {
		    	if (in_array($name, $this->_config['register_filters']))
		    	{
		        	return new Twig_Filter($name, $name);
		    	}
		    	return false;
			});
		}

		$this->set('meta', $this->_compile_metadata(), TRUE);
		//load all global
		foreach ($this->_globals as $key => $value) {
			$this->_twig->addGlobal($key, $value);
		}
		
		$this->_rendered = TRUE;

		$this->_twig->setLexer(new Twig_Lexer($this->_twig, $this->_config['delimiters']));	

		return $this->_twig->load($this->_template . $this->_config['template_file_ext']);
	}

	/**
	 * Render and return compiled HTML
	 *
	 * @access	public
	 * @param 	string	(optional) template file
	 * @return	string	compiled HTML
	 */

	public function render($template = '')
	{		
		if(!empty($template)) $this->template($template);

		try
		{
			return $this->_load()->render($this->_data);
		}
		catch(Twig_Error_Loader $e)
		{
			show_error($e->getRawMessage());
		}
	}

	/**
	 * Display the compiled HTML content
	 *
	 * @access	public
	 * @param 	string	(optional) template file
	 * @return	void
	 */

	public function display($template = '')
	{		
		if(!empty($template)) $this->template($template);

		try
		{
			$this->_load()->display($this->_data);
		}
		catch(Twig_Error_Loader $e)
		{
			show_error($e->getRawMessage());
		}
	}

	/**
	* Set template locations
	*
	* @access	private
	* @param 	string	name of theme to load
	* @return	void
	*/

	private function _set_template_locations($theme, $reset=FALSE)
	{
		if ($reset) {
			$this->_template_locations = array();
		}
		$full_path = $this->_themes_base_dir . $theme;
		//exist ?
		if(in_array($full_path, $this->_template_locations)) {
			return;
		}

		// Check if HMVC is installed.
		// NOTE: there may be a simplier way to check it but this seems good enough.
		$use_module = FALSE;
		if(method_exists($this->CI->router, 'fetch_module'))
		{
			$this->_module = $this->CI->router->fetch_module();

			// Only if the current page is served from a module do we need to add extra template locations.
			if(!empty($this->_module))
			{
				$use_module = TRUE;
				$module_locations = Modules::$locations;

				foreach($module_locations as $loc => $offset)
				{
					/* Only add the template location if the same exists, otherwise
					you'll need always a directory for your templates, even your module
					won't use templates */
					$theme_dir = $loc . $this->_module . '/' . $this->_config['themes_base_dir'] . $theme;
					if ( is_dir($theme_dir) ) {
						$this->_template_locations[] = $theme_dir;
					}
				}
			}
		}
		
		if ($use_module) {
			// add selected theme as backup (last order)
			$this->_template_locations[] = $full_path;
		} else {
			//selected theme should be number 1
			array_unshift($this->_template_locations, $full_path);
		}
		
		// Reset the paths if needed.
		if(is_object($this->_twig_loader))
		{			
			$this->_twig_loader->setPaths($this->_template_locations);
		}
	}

	/**
	* Get current theme
	*
	* @access	public
	* @return	string	name of the currently loaded theme
	*/

	public function get_theme()
	{
		return $this->_theme;
	}

	/**
	* Get current layout
	*
	* @access	public
	* @return	string	name of the currently used layout
	*/

	public function get_layout()
	{
		return $this->_layout;
	}

	/**
	* Get template
	*
	* @access	public
	* @return	string	name of the loaded template file (without the extension)
	*/

	public function get_template()
	{
		return $this->_template;
	}

	/**
	* Get metadata
	*
	* @access	public
	* @param 	string 	(optional) name of the meta tag
	* @param 	boolean	whether to compile to html
	* @return	mixed  	array of tag(s), string (HTML) or FALSE
	*/

	public function get_meta($name = '', $compile = FALSE)
	{
		if(empty($name))
		{
			return ($compile) ? $this->_compile_metadata() : $this->_meta;
		}
		else
		{
			if(array_key_exists($name, $this->_meta))
			{
				return ($compile) ? $this->_meta_to_html($this->_meta[$name]) : $this->_meta[$name];
			}

			return FALSE;
		}
	}

	/**
	* Check if template is already rendered
	*
	* @access	public
	* @return	boolean
	*/

	public function rendered()
	{
		return $this->_rendered;
	}

	/**
	* Magic method __get()
	*/

	public function __get($variable)
	{
		if($variable == 'twig') return $this->_twig;

		if(array_key_exists($variable, $this->_globals))
		{
			return $this->_globals[$variable];
		}
		elseif(array_key_exists($variable, $this->_data))
		{
			return $this->_data[$variable];
		}

		return FALSE;
	}
}
// End Class
