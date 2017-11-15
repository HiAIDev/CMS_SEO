<?php

namespace Craft;

/**
 * SEO for Craft CMS
 *
 * @author    Ether Creative <hello@ethercreative.co.uk>
 * @copyright Copyright (c) 2016, Ether Creative
 * @license   http://ether.mit-license.org/
 * @since     1.0
 */
class SeoPlugin extends BasePlugin {

	// Variables
	// =========================================================================

	public static $commerceInstalled = false;

	// Details
	// =========================================================================

	public function getName()
	{
		return 'SEO';
	}

	public function getDescription()
	{
		return 'Search engine optimization utilities';
	}

	public function getVersion()
	{
		return '2.0.0-beta';
	}

	public function getSchemaVersion()
	{
		return '0.1.3';
	}

	public function getDeveloper()
	{
		return 'Ether Creative';
	}

	public function getDeveloperUrl()
	{
		return 'http://ethercreative.co.uk';
	}

	public function getReleaseFeedUrl()
	{
		return 'https://raw.githubusercontent.com/ethercreative/seo/master/releases.json';
	}

	// Routes
	// =========================================================================

	public function hasCpSection()
	{
		return !craft()->isConsole() && (
			craft()->userSession->isAdmin() ||
			craft()->userSession->checkPermission('accessPlugin-seo')
		);
	}

	public function registerCpRoutes ()
	{
		return [
			'seo' => ['action' => 'seo/index'],
			'seo/sitemap' => ['action' => 'seo/sitemapPage'],
			'seo/redirects' => ['action' => 'seo/redirectsPage'],
			'seo/settings' => ['action' => 'seo/settings'],
			'seo/ab' => ['action' => 'seo/ab']
		];
	}

	public function registerSiteRoutes ()
	{
		return array(
			$this->getSettings()->sitemapName . '.xml' =>
				['action' => 'seo/sitemap/index'],
			$this->getSettings()->sitemapName . '_custom.xml' =>
				['action' => 'seo/sitemap/custom'],
			$this->getSettings()->sitemapName . '_(?P<section>\w*)_(?P<id>\d*)_(?P<page>\d*)\.xml' =>
				['action' => 'seo/sitemap/sitemap'],
		);
	}

	// Settings
	// =========================================================================

	protected function defineSettings()
	{
		return array(
			// Sitemap Settings
			'sitemapName'  => [AttributeType::String, 'default' => 'sitemap'],
			'sitemapLimit' => [AttributeType::Number, 'default' => 1000],

			// Redirect Settings
			'publicPath'   => [AttributeType::String],

			// Fieldtype Settings
			'titleSuffix'  => [AttributeType::String],
			'metaTemplate' => [AttributeType::String],
		);
	}

	public function getSettingsUrl()
	{
		return 'seo/settings';
	}

	public function prepSettings($settings)
	{
		return parent::prepSettings($settings);
	}

	// Initializer
	// =========================================================================

	public function init()
	{
		// Check if commerce is installed
		SeoPlugin::$commerceInstalled =
			craft()->plugins->getPlugin('commerce') != null;

		// TODO: On category / section update, update sitemap

		// Site requests (not live preview)
		// ---------------------------------------------------------------------
		if (
			craft()->request->isSiteRequest()
			&& !craft()->request->isLivePreview()
		) {
			// If request 404s, try to redirect
			craft()->onException = function(\CExceptionEvent $event) {
				craft()->seo_redirect->onException($event);
			};

			// Include Meta Markup in head via `{% hook 'seo' %}`
			craft()->templates->hook(
				'seo',
				function(&$context) {
				return craft()->seo->hook($context);
			}
			);

			// Inject A/B
			craft()->on(
				'elements.onPopulateElements',
				[$this, 'onPopulateElements']
			);
		}

		// CP Requests (not ajax)
		// ---------------------------------------------------------------------
		if (
			craft()->request->isCpRequest()
			&& !craft()->request->isAjaxRequest()
		) {
			// Hook into edit templates
			$hooks = [
				'cp.entries.edit.right-pane',
				'cp.categories.edit.right-pane',
				'cp.commerce.product.edit.right-pane',
			];

			foreach ($hooks as $hook) {
				craft()->templates->hook(
					$hook,
					function (&$context) {
						return craft()->seo_ab->injectElementEdit($context);
					}
				);
			}

			// Listen for element save
			craft()->on(
				'elements.onSaveElement',
				[$this, 'onSaveElement']
			);
		}
	}

	// Events
	// =========================================================================

	public function onPopulateElements (Event $event) {
		craft()->seo_ab->injectBElement($event->params['elements']);
	}

	public function onSaveElement (Event $event) {
		if (craft()->request->getPost("seoAbCapable")) {
			/** @var BaseElementModel $element */
			$element = $event->params["element"];
			craft()->seo_ab->onSaveB($element);
		}
	}

	// Hooks
	// =========================================================================

	public function registerUserPermissions()
	{
		return array(
			'manageSitemap' => array('label' => Craft::t('Manage Sitemap')),
			'manageRedirects' => array('label' => Craft::t('Manage Redirects')),
		);
	}

}
