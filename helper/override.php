<?php
/**
 * @package     RedCORE.Plugin
 * @subpackage  System.MVCOverride
 *
 * @copyright   Copyright (C) 2008 - 2017 redCOMPONENT.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

// No direct access
defined('_JEXEC') or die;

jimport('joomla.filesystem.file');

/**
 * Class MVCOverrideHelperOverride
 *
 * @since  1.4
 */
abstract class MVCOverrideHelperOverride
{
	/**
	 * Default suffix of class overrides
	 *
	 * @var string
	 * @since  1.4
	 */
	const SUFFIX = 'Default';

	/**
	 * Default pre-suffix of class overrides
	 *
	 * @var string
	 * @since  1.4
	 */
	const PREFIX = '';

	/**
	 * Get Original Class
	 *
	 * @param   string  $bufferContent  Buffer Content
	 *
	 * @return null|string
	 * @since  1.4
	 */
	static public function getOriginalClass($bufferContent)
	{
		$originalClass = null;
		$tokens        = token_get_all($bufferContent);

		foreach ($tokens as $key => $token)
		{
			if (is_array($token))
			{
				// Find the class declaration
				if (token_name($token[0]) == 'T_CLASS')
				{
					// Class name should be in the key+2 position
					$originalClass = $tokens[$key + 2][1];
					break;
				}
			}
		}

		return $originalClass;
	}

	/**
	 * Read source file and replace class name by adding suffix/prefix
	 *
	 * @param   string  $componentFile  Component File
	 * @param   string  $prefix         Prefix
	 * @param   string  $suffix         Suffix
	 *
	 * @return  string
	 * @since  1.4
	 */
	static public function createDefaultClass($componentFile, $prefix = null, $suffix = null)
	{
		$bufferFile = file_get_contents($componentFile);

		$originalClass = self::getOriginalClass($bufferFile);

		// Set default values if null
		if (is_null($suffix))
		{
			$suffix = self::SUFFIX;
		}

		if (is_null($prefix))
		{
			$prefix = self::PREFIX;
		}

		// Replace original class name by default
		$bufferContent = preg_replace(
			'/(final\s{1,}|.?)(class\s{1,})(' . $originalClass . ')(\s{1,})/i',
			' $2' . $prefix . '$3' . $suffix . '$4',
			$bufferFile
		);

		return $bufferContent;
	}

	/**
	 * Load buffer content
	 *
	 * @param   string  $bufferContent  Buffer Content
	 *
	 * @return  void
	 * @since  1.4
	 */
	static public function load($bufferContent)
	{
		if (!empty($bufferContent))
		{
			eval('?>' . $bufferContent . PHP_EOL . '?>');
		}
	}
}
