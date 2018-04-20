<?php
/**
 * @package     RedCORE.Plugin
 * @subpackage  System.MVCOverride
 *
 * @copyright   Copyright (C) 2008 - 2017 redCOMPONENT.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\CMS\MVC\View;

defined('JPATH_PLATFORM') or die;

/**
 * Base class for a Joomla View
 *
 * Class holding methods for displaying presentation data.
 *
 * @since  1.5.0
 */
class HtmlView extends LIB_HtmlViewDefault
{
	/**
	 * Register new paths to helpers and templates
	 *
	 * @var array
	 * @since 1.5.0
	 */
	static private $codePaths = array('helper' => array(), 'template' => array());

	/**
	 * Load a template file -- first look in the templates folder for an override
	 *
	 * @param   string  $tpl  The name of the template source file; automatically searches the template paths and compiles as needed.
	 *
	 * @return  string  The output of the the template script.
	 * @since   1.5.0
	 */
	public function loadTemplate($tpl = null)
	{
		if (!empty(self::$codePaths['template']))
		{
			foreach (self::$codePaths['template'] as $codePool)
			{
				$this->addTemplatePath($codePool . '/views/' . $this->getName() . '/tmpl/');
			}
		}

		return parent::loadTemplate($tpl);
	}

	/**
	 * Load a helper file
	 *
	 * @param   string  $hlp  The name of the helper source file automatically searches the helper paths and compiles as needed.
	 *
	 * @return  void
	 * @since   1.5.0
	 */
	public function loadHelper($hlp = null)
	{
		if (!empty(self::$codePaths['helper']))
		{
			foreach (self::$codePaths['helper'] as $codePool)
			{
				$this->addHelperPath($codePool . '/helpers/');
			}
		}

		parent::loadHelper($hlp);
	}

	/**
	 * Add new helper path
	 *
	 * @param   string  $path  Path
	 *
	 * @return  array
	 * @since   1.5.0
	 */
	static public function addViewHelperPath($path = null)
	{
		if (is_null($path))
		{
			return self::$codePaths['helper'];
		}

		array_push(self::$codePaths['helper'], $path);

		return self::$codePaths['helper'];
	}

	/**
	 * Add new template path
	 *
	 * @param   string  $path  Path
	 *
	 * @return  array
	 * @since   1.5.0
	 */
	static public function addViewTemplatePath($path = null)
	{
		if (is_null($path))
		{
			return self::$codePaths['template'];
		}

		array_push(self::$codePaths['template'], $path);

		return self::$codePaths['template'];
	}
}
