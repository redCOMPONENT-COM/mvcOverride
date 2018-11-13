<?php
/**
 * @package     RedCORE.Plugin
 * @subpackage  System.MVCOverride
 *
 * @copyright   Copyright (C) 2008 - 2015 redCOMPONENT.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');
jimport('joomla.filesystem.folder');

JLoader::import('system.mvcoverride.helper.override', JPATH_PLUGINS);
JLoader::import('system.mvcoverride.helper.codepool', JPATH_PLUGINS);
JLoader::import('system.mvcoverride.helper.mvcloader', JPATH_PLUGINS);

/**
 * PlgSystemMVCOverride class.
 *
 * @extends JPlugin
 * @since  1.4
 */
class PlgSystemMVCOverride extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  1.4
	 */
	protected $autoloadLanguage = true;

	/**
	 * @var array
	 * @since 1.6
	 */
	protected $pathwaysFromTemplate = [];

	/**
	 * Constructor
	 *
	 * @param   object  $subject  The object to observe
	 * @param   array   $config   An optional associative array of configuration settings.
	 *                            Recognized key values include 'name', 'group', 'params', 'language'
	 *                            (this list is not meant to be comprehensive).
	 * @since  1.4
	 */
	public function __construct(&$subject, $config = array())
	{
		JPlugin::loadLanguage('plg_system_mvcoverride');

		parent::__construct($subject, $config);

		if (JPluginHelper::isEnabled('system', 'redcore'))
		{
			$redcoreLoader = JPATH_LIBRARIES . '/redcore/bootstrap.php';

			// Try to support redCORE RLoader if exist
			if (file_exists($redcoreLoader))
			{
				require_once $redcoreLoader;
				RBootstrap::bootstrap(false);
			}
		}

		MVCLoader::setupOverrideLoader(
			$this->params->get('changePrivate', 0),
			$this->params->get('extendPrefix', ''),
			$this->params->get('extendSuffix', 'Default')
		);
		MVCOverrideHelperCodepool::initialize();

		$includedPathways = $this->params->get('includePath', '{JPATH_BASE}/code,{JPATH_THEMES}/{template}/code');

		$includedPathways = str_replace(
			['{JPATH_BASE}', '{JPATH_THEMES}'],
			[JPATH_BASE, JPATH_THEMES],
			$includedPathways
		);

		$includedPathways = explode(',', $includedPathways);

		foreach ($includedPathways as $key => $includedPatch)
		{
			if (strpos($includedPatch, '{template}') !== false)
			{
				// We will attach it in onAfterRoute
				$this->pathwaysFromTemplate[] = $includedPatch;
				unset($includedPathways[$key]);
			}
		}

		$this->initialiseComponents($includedPathways);
	}

	/**
	 * @param   array|string  $includedPathways  Included pathways
	 *
	 * @since 1.6.0
	 * @return  void
	 */
	protected function initialiseComponents($includedPathways)
	{
		if (empty($includedPathways))
		{
			return;
		}

		// Add override paths for the current component files
		foreach ((array) $includedPathways as $codePool)
		{
			if (!JFolder::exists($codePool))
			{
				continue;
			}

			MVCOverrideHelperCodepool::addCodePath($codePool);

			$components = JFolder::folders($codePool);

			if (!empty($components))
			{
				foreach ($components as $key => $component)
				{
					if (strpos($component, 'com_') !== 0)
					{
						unset($components[$key]);
						continue;
					}

					$this->addOverrideFiles($codePool, $component);
				}
			}

			if (version_compare(JVERSION, '3.8', '>='))
			{
				if (!empty($components))
				{
					foreach ($components as $option)
					{
						Joomla\CMS\MVC\View\HtmlView::addViewHelperPath($codePool . '/' . $option);
						Joomla\CMS\MVC\View\HtmlView::addViewTemplatePath($codePool . '/' . $option);
						Joomla\CMS\Table\Table::addIncludePath($codePool . '/' . $option . '/tables');
						Joomla\CMS\MVC\Model\FormModel::addComponentFormPath($codePool . '/' . $option . '/models/forms');
						Joomla\CMS\MVC\Model\FormModel::addComponentFieldPath($codePool . '/' . $option . '/models/fields');
						Joomla\CMS\MVC\Model\ListModel::addComponentFormPath($codePool . '/' . $option . '/models/forms');
						Joomla\CMS\MVC\Model\ListModel::addComponentFieldPath($codePool . '/' . $option . '/models/fields');
					}
				}

				Joomla\CMS\Helper\ModuleHelper::addIncludePath($codePool . '/modules');
			}
			elseif (version_compare(JVERSION, '3.0', '>='))
			{
				if (!empty($components))
				{
					foreach ($components as $option)
					{
						JViewLegacy::addViewHelperPath($codePool . '/' . $option);
						JViewLegacy::addViewTemplatePath($codePool . '/' . $option);
						JTable::addIncludePath($codePool . '/' . $option . '/tables');
						JModelForm::addComponentFormPath($codePool . '/' . $option . '/models/forms');
						JModelForm::addComponentFieldPath($codePool . '/' . $option . '/models/fields');
					}
				}

				JModuleHelper::addIncludePath($codePool . '/modules');
			}
			else
			{
				if (!empty($components))
				{
					foreach ($components as $option)
					{
						JView::addViewHelperPath($codePool . '/' . $option);
						JView::addViewTemplatePath($codePool . '/' . $option);
						JTable::addIncludePath($codePool . '/' . $option . '/tables');
						JModelForm::addComponentFormPath($codePool . '/' . $option . '/models/forms');
						JModelForm::addComponentFieldPath($codePool . '/' . $option . '/models/fields');
					}
				}

				JModuleHelper::addIncludePath($codePool . '/modules');
			}
		}
	}

	/**
	 * onAfterRoute function.
	 *
	 * @return void
	 * @since  1.4
	 * @throws Exception
	 */
	public function onAfterRoute()
	{
		$app = JFactory::getApplication();

		if (!empty($this->pathwaysFromTemplate))
		{
			foreach ($this->pathwaysFromTemplate as &$pathwayFromTemplate)
			{
				$pathwayFromTemplate = str_replace(
					array('{template}'),
					array($app->getTemplate()),
					$pathwayFromTemplate
				);
			}
		}

		// Register additional include paths for code replacements from plugins
		$app->triggerEvent('onMVCOverrideIncludePaths', array(&$this->pathwaysFromTemplate));

		$this->initialiseComponents($this->pathwaysFromTemplate);
	}

	/**
	 * Add new files
	 *
	 * @param   string  $includePath  Path for check inner files
	 * @param   string  $component    Component name folder
	 *
	 * @return void
	 * @since  1.4
	 */
	protected function addOverrideFiles($includePath, $component)
	{
		$types         = array('controllers', 'models', 'helpers', 'views');
		$currentFormat = JFactory::getDocument()->getType();

		foreach ($types as $type)
		{
			$searchFolder = $includePath . '/' . $component . '/' . $type;

			if (!JFolder::exists($searchFolder))
			{
				continue;
			}

			$componentName = str_replace('com_', '', $component);

			switch ($type)
			{
				case 'helpers':
					$listFiles = JFolder::files($searchFolder, '.php', false, true);

					if (!empty($listFiles))
					{
						foreach ($listFiles as $file)
						{
							$fileName  = JFile::stripExt(basename($file));
							$indexName = $componentName . 'helper' . $fileName;
							$this->getOverrideFileInfo($includePath, $component, $file, $type, $indexName);
						}
					}
					break;

				case 'views':
					// Reading view folders
					$views = JFolder::folders($searchFolder);

					if (!empty($views))
					{
						foreach ($views as $view)
						{
							// Get view formats files
							$listFiles = JFolder::files($searchFolder . '/' . $view, '.' . $currentFormat . '.php', false, true);

							if (!empty($listFiles))
							{
								foreach ($listFiles as $file)
								{
									$this->getOverrideFileInfo($includePath, $component, $file, $type);
								}
							}
						}
					}
					break;

				default:
					$listFiles = JFolder::files($searchFolder, '.php', false, true);

					if (!empty($listFiles))
					{
						foreach ($listFiles as $file)
						{
							$this->getOverrideFileInfo($includePath, $component, $file, $type);
						}
					}
			}
		}
	}

	/**
	 * Get file info
	 *
	 * @param   string  $includePath  Path for check inner files
	 * @param   string  $component    Component name folder
	 * @param   string  $filePath     Checking file path
	 * @param   string  $type         Type file
	 * @param   string  $indexName    Name usage for helper index
	 *
	 * @return  void
	 * @since  1.4
	 */
	protected function getOverrideFileInfo($includePath, $component, $filePath, $type = '', $indexName = '')
	{
		$filePath         = JPath::clean($filePath);
		$sameFolderPrefix = $component . '/' . $type;

		if ($type == 'helpers')
		{
			$baseName = basename($filePath);
			$prefix   = substr($baseName, 0, 5);

			if ($prefix != 'admin')
			{
				$realPath = JPATH_SITE . '/components' . substr($filePath, strlen($includePath));
			}
			else
			{
				$realPath = JPATH_ADMINISTRATOR . '/components/' . $sameFolderPrefix . '/' . substr($baseName, 5);
			}
		}
		else
		{
			$realPath = JPATH_BASE . '/components/' . substr($filePath, strlen($includePath));
		}

		$realPath = JPath::clean($realPath);

		if (!JFile::exists($realPath))
		{
			return;
		}

		$forOverrideFile = file_get_contents($realPath);
		$originalClass   = MVCOverrideHelperOverride::getOriginalClass($forOverrideFile);
		unset($forOverrideFile);

		if ($type == 'helpers')
		{
			JLoader::register($indexName, $filePath);
		}
		else
		{
			// Set path for new file
			MVCLoader::setOverrideFile($originalClass, $filePath);
		}

		// Set path for override file
		MVCLoader::setOverrideFile(
			$originalClass,
			$realPath,
			true,
			$this->params->get('extendPrefix', ''),
			$this->params->get('extendSuffix', 'Default')
		);
	}
}
