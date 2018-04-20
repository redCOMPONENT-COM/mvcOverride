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

/**
 * Registry codepools and intialize basic override for core classes
 *
 * @since  1.4
 */
class MVCOverrideHelperCodepool
{
	/**
	 * Register global paths to override code
	 *
	 * @var array
	 * @since  1.4
	 */
	private static $paths = array();

	/**
	 * Initialize override of some core classes
	 *
	 * @param   array  $classes  Array include classes
	 *
	 * @return void
	 * @since  1.4
	 */
	static public function initialize($classes = array('form', 'form_list', 'view', 'module'))
	{
		$pluginPath = dirname(dirname(__FILE__));
		$classes    = (array) $classes;

		if (version_compare(JVERSION, '3.8.0', '>='))
		{
			$overrideClasses = array(
				'form' => array(
					'source_file' => JPATH_LIBRARIES . '/src/MVC/Model/FormModel.php',
					'class_name' => 'FormModel',
					'namespace' => 'Joomla\CMS\MVC\Model',
					'override_file' => $pluginPath . '/core/src/MVC/Model/FormModel.php'
				),
				'form_list' => array(
					'source_file' => JPATH_LIBRARIES . '/src/MVC/Model/ListModel.php',
					'class_name' => 'ListModel',
					'namespace' => 'Joomla\CMS\MVC\Model',
					'override_file' => $pluginPath . '/core/src/MVC/Model/ListModel.php'
				),
				'view' => array(
					'source_file' => JPATH_LIBRARIES . '/src/MVC/View/HtmlView.php',
					'class_name' => 'HtmlView',
					'namespace' => 'Joomla\CMS\MVC\View',
					'override_file' => $pluginPath . '/core/src/MVC/View/HtmlView.php'
				),
				'module' => array(
					'source_file' => JPATH_LIBRARIES . '/src/Helper/ModuleHelper.php',
					'class_name' => 'ModuleHelper',
					'namespace' => 'Joomla\CMS\Helper',
					'override_file' => $pluginPath . '/core/src/Helper/ModuleHelper.php'
				)
			);
		}
		elseif (version_compare(JVERSION, '3.0', '>='))
		{
			$overrideClasses = array(
				'form' => array(
					'source_file' => JPATH_LIBRARIES . '/legacy/model/form.php',
					'class_name' => 'JModelForm',
					'override_file' => $pluginPath . '/core/model/modelform.php'
				),
				'view' => array(
					'source_file' => JPATH_LIBRARIES . '/legacy/view/legacy.php',
					'class_name' => 'JViewLegacy',
					'override_file' => $pluginPath . '/core/view/legacy.php'
				),
				'module' => array(
					'source_file' => JPATH_LIBRARIES . '/cms/module/helper.php',
					'class_name' => 'JModuleHelper',
					'override_file' => $pluginPath . '/core/module/helper.php'
				)
			);
		}
		else
		{
			$overrideClasses = array(
				'form' => array(
					'source_file' => JPATH_LIBRARIES . '/joomla/application/component/modelform.php',
					'class_name' => 'JModelForm',
					'jimport' => 'joomla.application.component.modelform',
					'override_file' => $pluginPath . '/core/model/modelform.php'
				),
				'view' => array(
					'source_file' => JPATH_LIBRARIES . '/joomla/application/component/view.php',
					'class_name' => 'JView',
					'jimport' => 'joomla.application.component.view',
					'override_file' => $pluginPath . '/core/view/view.php'
				),
				'module' => array(
					'source_file' => JPATH_LIBRARIES . '/joomla/application/module/helper.php',
					'class_name' => 'JModuleHelper',
					'jimport' => 'joomla.application.module.helper',
					'override_file' => $pluginPath . '/core/module/helper.php'
				)
			);
		}

		foreach ($classes as $class)
		{
			if (array_key_exists($class, $overrideClasses))
			{
				$overrideClass = $overrideClasses[$class];
				self::overrideClass(
					$overrideClass['source_file'],
					$overrideClass['class_name'],
					array_key_exists('jimport', $overrideClass) ? $overrideClass['jimport'] : '',
					$overrideClass['override_file'],
					array_key_exists('namespace', $overrideClass) ? $overrideClass['namespace'] : ''
				);
			}
		}
	}

	/**
	 * Override a core classes and just overload methods that need
	 *
	 * @param   string  $sourcePath   Source Path
	 * @param   string  $class        Class
	 * @param   string  $jimport      JImport path
	 * @param   string  $replacePath  Replace Path
	 * @param   string  $namespace    Namespace
	 *
	 * @return void
	 * @since  1.4
	 */
	static private function overrideClass($sourcePath, $class, $jimport, $replacePath, $namespace = '')
	{
		// Override library class
		if (!file_exists($sourcePath))
		{
			return;
		}

		MVCLoader::setOverrideFile($class, $sourcePath, true, 'LIB_', 'Default', $namespace);

		if (!empty($jimport))
		{
			jimport($jimport);
		}

		MVCLoader::setOverrideFile($class, $replacePath, false, null, null, $namespace);
	}

	/**
	 * Add a code pool to override
	 *
	 * @param   string  $path     Path
	 * @param   bool    $reverse  If true - return reverse array
	 *
	 * @return array
	 * @since  1.4
	 */
	static public function addCodePath($path = null, $reverse = false)
	{
		if (is_null($path))
		{
			if ($reverse)
			{
				return self::$paths;
			}
			else
			{
				return array_reverse(self::$paths);
			}
		}

		settype($path, 'array');

		foreach ($path as $codePool)
		{
			$codePool = JPath::clean($codePool);

			if (is_dir($codePool))
			{
				array_unshift(self::$paths, $codePool);
			}
		}

		return self::$paths;
	}
}
