<?php
/**
 *
 * phpBB Media Embed PlugIn extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2016 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\mediaembed\acp;

/**
 * phpBB Media Embed Plugin ACP module.
 */
class main_module
{
	/** @var \phpbb\cache\driver\driver_interface $cache */
	protected $cache;

	/** @var \phpbb\config\config $config */
	protected $config;

	/** @var \phpbb\config\db_text $config_text */
	protected $config_text;

	/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
	protected $container;

	/** @var \phpbb\request\request $request */
	protected $request;

	/** @var \phpbb\template\template $template */
	protected $template;

	/** @var \phpbb\language\language $language */
	protected $language;

	/** @var \phpbb\log\log $log */
	protected $log;

	/** @var \phpbb\user */
	protected $user;

	/** @var array $enabled_sites */
	protected $enabled_sites;

	/** @var string $form_key */
	protected $form_key;

	/** @var string $page_title */
	public $page_title;

	/** @var string $tpl_name */
	public $tpl_name;

	/** @var string $u_action */
	public $u_action;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $phpbb_container;

		$this->container   = $phpbb_container;
		$this->cache       = $this->container->get('cache');
		$this->config      = $this->container->get('config');
		$this->config_text = $this->container->get('config_text');
		$this->language    = $this->container->get('language');
		$this->log         = $this->container->get('log');
		$this->request     = $this->container->get('request');
		$this->template    = $this->container->get('template');
		$this->user        = $this->container->get('user');
		$this->form_key    = 'phpbb/mediaembed';

		$this->language->add_lang('acp', 'phpbb/mediaembed');
	}

	/**
	 * Main ACP module
	 *
	 * @param int    $id   The module ID
	 * @param string $mode The module mode
	 */
	public function main($id, $mode)
	{
		add_form_key($this->form_key);

		if ($this->request->is_set_post('submit'))
		{
			$this->{'save_' . strtolower($mode)}();
		}

		switch ($mode)
		{
			case 'manage':
				$this->display($mode, ['MEDIA_SITES' => $this->get_sites()]);
			break;

			case 'settings':
				$this->display($mode, ['S_MEDIA_EMBED_BBCODE' => $this->config['media_embed_bbcode']]);
			break;
		}
	}

	/**
	 * Display data in the ACP module
	 *
	 * @param string $mode The ACP module mode (manage|settings)
	 * @param array  $data Array of data to assign to the template
	 */
	protected function display($mode, $data)
	{
		$this->tpl_name   = 'acp_phpbb_mediaembed_' . strtolower($mode);
		$this->page_title = $this->language->lang('ACP_MEDIA_' . strtoupper($mode));

		$this->template->assign_vars(array_merge($data, [
			'U_ACTION'	=> $this->u_action,
		]));
	}

	/**
	 * Get a list of available sites
	 *
	 * @return array An array of available sites
	 */
	protected function get_sites()
	{
		$sites = [];

		$configurator = $this->container->get('text_formatter.s9e.factory')->get_configurator();
		foreach ($configurator->MediaEmbed->defaultSites as $siteId => $siteConfig)
		{
			if (isset($configurator->BBCodes[$siteId]))
			{
				continue;
			}

			$sites[] = [
				'id'		=> $siteId,
				'name'		=> $siteConfig['name'],
				'enabled'	=> in_array($siteId, $this->get_enabled_sites()),
			];
		}

		return $sites;
	}

	/**
	 * Get enabled media sites stored in the database
	 *
	 * @return array An array of enabled sites
	 */
	protected function get_enabled_sites()
	{
		if ($this->enabled_sites === null)
		{
			$sites = json_decode($this->config_text->get('media_embed_sites'), true);
			$this->enabled_sites = is_array($sites) ? $sites : [];
		}

		return $this->enabled_sites;
	}

	/**
	 * Save site managed data to the database
	 */
	protected function save_manage()
	{
		$this->check_form_key();

		$this->config_text->set('media_embed_sites', json_encode($this->request->variable('mark', [''])));

		$this->cache->destroy($this->container->getParameter('text_formatter.cache.parser.key'));
		$this->cache->destroy($this->container->getParameter('text_formatter.cache.renderer.key'));

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PHPBB_MEDIA_EMBED_MANAGE');

		trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
	}

	/**
	 * Save settings data to the database
	 */
	protected function save_settings()
	{
		$this->check_form_key();

		$this->config->set('media_embed_bbcode', $this->request->variable('media_embed_bbcode', 0));

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PHPBB_MEDIA_EMBED_SETTINGS');

		trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
	}

	/**
	 * Check the form key, trigger error if invalid
	 */
	protected function check_form_key()
	{
		if (!check_form_key($this->form_key))
		{
			trigger_error('FORM_INVALID');
		}
	}
}
