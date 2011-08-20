<?php

/**
 * @key					- (string) Decoda tag
 * @tag					- (string) HTML replacement tag
 * @template			- (string) Template file to use for rendering
 * @pattern				- (string) Regex pattern that the content or default attribute must pass
 * @type				- (constant) Type of HTML element: block or inline
 * @allowed				- (constant) What types of elements are allowed to be nested
 * @attributes			- (array) Custom attributes to parse out of the Decoda markup
 * @map					- (array) Map parsed attributes to different names
 * @html				- (array) Custom HTML attributes to append to the parsed tag
 * @lineBreaks			- (boolean) Convert linebreaks within the content body
 * @autoClose			- (boolean) HTML tag is self closing
 * @preserveTags		- (boolean) Will not convert nested Decoda markup within this tag
 * @escapeContent		- (boolean) Escape HTML entities within the content body
 * @escapeAttributes	- (boolean) Escape HTML entities within the parsed attributes
 * @maxChildDepth		- (integer) Max depth for nested children of the same tag (-1 to disable)
 * @parent				- (array) List of Decoda keys that this tag can only be a direct child of
 * @children			- (array) List of Decoda keys for all the tags that can only be a direct descendant
 */
abstract class DecodaFilter extends DecodaAbstract {

	/**
	 * Type constants.
	 */
	const TYPE_NONE = 0;
	const TYPE_INLINE = 1;
	const TYPE_BLOCK = 2;
	const TYPE_BOTH = 3;

	/**
	 * Supported tags.
	 * 
	 * @access protected
	 * @var array
	 */
	protected $_tags = array();

	/**
	 * Return a tag if it exists, and merge with defaults.
	 * 
	 * @access public
	 * @param string $tag
	 * @return array
	 */
	public function tag($tag) {
		$defaults = array(
			// Meta
			'key' => $tag,
			'tag' => '',
			'template' => '',
			'pattern' => '',
			'type' => self::TYPE_BLOCK,
			'allowed' => self::TYPE_BOTH,
			
			// Attributes
			'attributes' => array(),
			'map' => array(),
			'html' => array(),
			
			// Processes
			'lineBreaks' => true,
			'autoClose' => false,
			'preserveTags' => false,
			'escapeContent' => false,
			'escapeAttributes' => true,
			'maxChildDepth' => -1,
			
			// Hierarchy
			'parent' => array(),
			'children' => array()
		);

		if (isset($this->_tags[$tag])) {
			return $this->_tags[$tag] + $defaults;
		}

		return $defaults;
	}

	/**
	 * Return a message string from the parser.
	 * 
	 * @access public
	 * @param string $key
	 * @param array $vars
	 * @return string
	 */
	public function message($key, array $vars = array()) {
		return $this->getParser()->message($key, $vars);
	}

	/**
	 * Return all tags.
	 * 
	 * @access public
	 * @return array
	 */
	public function tags() {
		return $this->_tags;
	}

	/**
	 * Parse the node and its content into an HTML tag.
	 * 
	 * @access public
	 * @param array $tag
	 * @param string $content
	 * @return string 
	 */
	public function parse(array $tag, $content) {
		$setup = $this->tag($tag['tag']);
		$xhtml = $this->getParser()->config('xhtml');
		
		if (empty($setup)) {
			return;
		}

		// If content doesn't match the pattern, don't wrap in a tag
		if (!empty($setup['pattern'])) {
			$test = !empty($tag['attributes']['default']) ? $tag['attributes']['default'] : $content;

			if (!preg_match($setup['pattern'], $test)) {
				return $content;
			}
		}
		
		// Add linebreaks
		if ($setup['lineBreaks']) {
			$content = nl2br($content, $xhtml);
		}

		// Escape entities
		if ($setup['escapeContent']) {
			$content = htmlentities($content, ENT_QUOTES, 'UTF-8');
		}

		// Use a template if it exists
		if (!empty($setup['template'])) {
			return $this->_render($tag, $content);
		}

		// Format attributes
		$attributes = array();
		$attr = '';
		
		if (!empty($tag['attributes'])) {
			foreach ($tag['attributes'] as $key => $value) {
				if (isset($setup['map'][$key])) {
					$key = $setup['map'][$key];
				}

				if ($key == 'default') {
					continue;
				}
				
				if ($setup['escapeAttributes']) {
					$attributes[$key] = htmlentities($value, ENT_QUOTES, 'UTF-8');
				} else {
					$attributes[$key] = $value;
				}
			}
		}
		
		if (!empty($setup['html'])) {
			$attributes += $setup['html'];
		}
		
		foreach ($attributes as $key => $value) {
			$attr .= ' '. $key .'="'. $value .'"';
		}

		// Build HTML tag
		$html = $setup['tag'];

		if (is_array($html)) {
			$html = $html[$xhtml];
		}

		$parsed = '<'. $html . $attr;

		if ($setup['autoClose']) {
			$parsed .= $xhtml ? '/>' : '>';
		} else {
			$parsed .= '>'. (!empty($tag['content']) ? $tag['content'] : $content) .'</'. $html .'>';
		}

		return $parsed;
	}

	/**
	 * Render the tag using a template.
	 * 
	 * @access public
	 * @param array $tag
	 * @param string $content
	 * @return string 
	 */
	protected function _render(array $tag, $content) {
		$setup = $this->tag($tag['tag']);
		$path = DECODA_TEMPLATES . $setup['template'] .'.php';

		if (!file_exists($path)) {
			throw new Exception(sprintf('Template file %s does not exist.', $setup['template']));
		}

		$vars = array();

		foreach ($tag['attributes'] as $key => $value) {
			if (isset($setup['map'][$key])) {
				$key = $setup['map'][$key];
			}

			$vars[$key] = $value;
		}

		extract($vars, EXTR_SKIP);
		ob_start();

		include $path;

		if ($setup['lineBreaks']) {
			return str_replace(array("\n", "\r"), "", ob_get_clean());
		}

		return ob_get_clean();
	}

}