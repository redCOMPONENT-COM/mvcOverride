<?php
/**
 * @package     RedCORE.Plugin
 * @subpackage  System.MVCOverride
 *
 * @copyright   Copyright (C) 2008 - 2017 redCOMPONENT.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\CMS\Helper;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;
use Joomla\Filesystem\Path;

/**
 * Module helper class
 *
 * @since  1.5
 */
abstract class ModuleHelper extends LIB_ModuleHelperDefault
{
	/**
	 * An array to hold included paths
	 *
	 * @var    array
	 * @since  1.5
	 */
	protected static $includePaths = array();

	/**
	 * Render the module.
	 *
	 * @param   object  $module   A module object.
	 * @param   array   $attribs  An array of attributes for the module (probably from the XML).
	 *
	 * @return  string  The HTML content of the module output.
	 *
	 * @since   1.5
	 */
	public static function renderModule($module, $attribs = array())
	{
		static $chrome;

		// Check that $module is a valid module object
		if (!is_object($module) || !isset($module->module) || !isset($module->params))
		{
			if (JDEBUG)
			{
				\JLog::addLogger(array('text_file' => 'jmodulehelper.log.php'), \JLog::ALL, array('modulehelper'));
				\JLog::add('ModuleHelper::renderModule($module) expects a module object', \JLog::DEBUG, 'modulehelper');
			}

			return '';
		}

		if (JDEBUG)
		{
			\JProfiler::getInstance('Application')->mark('beforeRenderModule ' . $module->module . ' (' . $module->title . ')');
		}

		$app = \JFactory::getApplication();

		// Record the scope.
		$scope = $app->scope;

		// Set scope to component name
		$app->scope = $module->module;

		// Get module parameters
		$params = new Registry($module->params);

		// Get the template
		$template = $app->getTemplate();

		// Get module path
		$module->module = preg_replace('/[^A-Z0-9_\.-]/i', '', $module->module);
		$path           = JPATH_BASE . '/modules/' . $module->module . '/' . $module->module . '.php';

		// Load the module
		if (file_exists($path))
		{
			$lang = \JFactory::getLanguage();

			$coreLanguageDirectory      = JPATH_BASE;
			$extensionLanguageDirectory = dirname($path);

			$langPaths = $lang->getPaths();

			// Only load the module's language file if it hasn't been already
			if (!$langPaths || (!isset($langPaths[$coreLanguageDirectory]) && !isset($langPaths[$extensionLanguageDirectory])))
			{
				// 1.5 or Core then 1.6 3PD
				$lang->load($module->module, $coreLanguageDirectory, null, false, true) ||
				$lang->load($module->module, $extensionLanguageDirectory, null, false, true);
			}

			$paths    = array(JPATH_BASE . '/modules');
			$paths    = array_merge(self::addIncludePath(), $paths);
			$filePath = Path::find($paths, $module->module . '/' . $module->module . '.php');

			if ($filePath)
			{
				$content = '';
				ob_start();
				include $filePath;
				$module->content = ob_get_contents() . $content;
				ob_end_clean();
			}
		}

		// Load the module chrome functions
		if (!$chrome)
		{
			$chrome = array();
		}

		include_once JPATH_THEMES . '/system/html/modules.php';
		$chromePath = JPATH_THEMES . '/' . $template . '/html/modules.php';

		if (!isset($chrome[$chromePath]))
		{
			if (file_exists($chromePath))
			{
				include_once $chromePath;
			}

			$chrome[$chromePath] = true;
		}

		// Check if the current module has a style param to override template module style
		$paramsChromeStyle = $params->get('style');

		if ($paramsChromeStyle)
		{
			$attribs['style'] = preg_replace('/^(system|' . $template . ')\-/i', '', $paramsChromeStyle);
		}

		// Make sure a style is set
		if (!isset($attribs['style']))
		{
			$attribs['style'] = 'none';
		}

		// Dynamically add outline style
		if ($app->input->getBool('tp') && ComponentHelper::getParams('com_templates')->get('template_positions_display'))
		{
			$attribs['style'] .= ' outline';
		}

		// If the $module is nulled it will return an empty content, otherwise it will render the module normally.
		$app->triggerEvent('onRenderModule', array(&$module, &$attribs));

		if ($module === null || !isset($module->content))
		{
			return '';
		}

		foreach (explode(' ', $attribs['style']) as $style)
		{
			$chromeMethod = 'modChrome_' . $style;

			// Apply chrome and render module
			if (function_exists($chromeMethod))
			{
				$module->style = $attribs['style'];

				ob_start();
				$chromeMethod($module, $params, $attribs);
				$module->content = ob_get_contents();
				ob_end_clean();
			}
		}

		// Revert the scope
		$app->scope = $scope;

		$app->triggerEvent('onAfterRenderModule', array(&$module, &$attribs));

		if (JDEBUG)
		{
			\JProfiler::getInstance('Application')->mark('afterRenderModule ' . $module->module . ' (' . $module->title . ')');
		}

		return $module->content;
	}

	/**
	 * Get the path to a layout for a module
	 *
	 * @param   string  $module  The name of the module
	 * @param   string  $layout  The name of the module layout. If alternative layout, in the form template:filename.
	 *
	 * @return  string  The path to the module layout
	 *
	 * @since   1.5
	 */
	public static function getLayoutPath($module, $layout = 'default')
	{
		$template      = \JFactory::getApplication()->getTemplate();
		$defaultLayout = $layout;
		$templatePaths = array();

		if (strpos($layout, ':') !== false)
		{
			// Get the template and file name from the string
			$temp          = explode(':', $layout);
			$template      = $temp[0] === '_' ? $template : $temp[0];
			$layout        = $temp[1];
			$defaultLayout = $temp[1] ?: 'default';
		}

		foreach (self::addIncludePath() as $onePath)
		{
			$templatePaths[] = $onePath . '/' . $module;
		}

		$templatePaths[] = JPATH_THEMES . '/' . $template . '/html/' . $module;
		$templatePaths[] = JPATH_BASE . '/modules/' . $module;

		// If the template has a layout override use it
		if ($tPath = Path::find($templatePaths, $layout . '.php'))
		{
			return $tPath;
		}
		elseif ($tPath = Path::find($templatePaths, 'tmpl/' . $defaultLayout . '.php'))
		{
			return $tPath;
		}
		else
		{
			return JPATH_BASE . '/modules/' . $module . '/tmpl/default.php';
		}
	}

	/**
	 * Add a directory where JModuleHelper should search for module. You may
	 * either pass a string or an array of directories.
	 *
	 * @param   string  $path  A path to search.
	 *
	 * @return  array  An array with directory elements
	 *
	 * @since   1.5
	 */
	public static function addIncludePath($path = '')
	{
		if ($path)
		{
			// Force path to array
			settype($path, 'array');

			// Loop through the path directories
			foreach ($path as $dir)
			{
				if (!empty($dir) && !in_array(Path::clean($dir), self::$includePaths))
				{
					array_unshift(self::$includePaths, Path::clean($dir));
				}
			}
		}

		return self::$includePaths;
	}
}
