<?php

/**
 * @file classes/core/Application.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Application
 * @ingroup core
 * @see PKPApplication
 *
 * @brief Class describing this application.
 *
 */

import('lib.pkp.classes.core.PKPApplication');

define('REQUIRES_XSL', false);

define('ASSOC_TYPE_ARTICLE',		ASSOC_TYPE_SUBMISSION); // DEPRECATED but needed by filter framework
define('ASSOC_TYPE_GALLEY',		ASSOC_TYPE_REPRESENTATION);

define('ASSOC_TYPE_JOURNAL',		0x0000100);

define('CONTEXT_JOURNAL', 1);

define('LANGUAGE_PACK_DESCRIPTOR_URL', 'http://pkp.sfu.ca/ojs/xml/%s/locales.xml');
define('LANGUAGE_PACK_TAR_URL', 'http://pkp.sfu.ca/ojs/xml/%s/%s.tar.gz');

define('METRIC_TYPE_COUNTER', 'ops::counter');

class Application extends PKPApplication {
	/**
	 * Get the "context depth" of this application, i.e. the number of
	 * parts of the URL after index.php that represent the context of
	 * the current request (e.g. Journal [1], or Conference and
	 * Scheduled Conference [2]).
	 * @return int
	 */
	public function getContextDepth() {
		return 1;
	}

	/**
	 * Get the list of context elements.
	 * @return array
	 */
	public function getContextList() {
		return array('journal');
	}

	/**
	 * Get the symbolic name of this application
	 * @return string
	 */
	public static function getName() {
		return 'ops';
	}

	/**
	 * Get the locale key for the name of this application.
	 * @return string
	 */
	public function getNameKey() {
		return('common.software');
	}

	/**
	 * Get the URL to the XML descriptor for the current version of this
	 * application.
	 * @return string
	 */
	public function getVersionDescriptorUrl() {
		return('http://pkp.sfu.ca/ojs/xml/ojs-version.xml');
	}

	/**
	 * Get the map of DAOName => full.class.Path for this application.
	 * @return array
	 */
	public function getDAOMap() {
		return array_merge(parent::getDAOMap(), array(
			'SubmissionDAO' => 'classes.submission.SubmissionDAO',
			'ArticleGalleyDAO' => 'classes.article.ArticleGalleyDAO',
			'ArticleSearchDAO' => 'classes.search.ArticleSearchDAO',
			'AuthorDAO' => 'classes.article.AuthorDAO',
			'JournalDAO' => 'classes.journal.JournalDAO',
			'JournalSettingsDAO' => 'classes.journal.JournalSettingsDAO',
			'MetricsDAO' => 'classes.statistics.MetricsDAO',
			'OAIDAO' => 'classes.oai.ojs.OAIDAO',
			'PublishedSubmissionDAO' => 'classes.article.PublishedSubmissionDAO',
			'SectionDAO' => 'classes.journal.SectionDAO',
		));
	}

	/**
	 * Get the list of plugin categories for this application.
	 * @return array
	 */
	public function getPluginCategories() {
		return array(
			// NB: Meta-data plug-ins are first in the list as this
			// will make them load (and install) first.
			// This is necessary as several other plug-in categories
			// depend on meta-data. This is a very rudimentary type of
			// dependency management for plug-ins.
			'metadata',
			'auth',
			'blocks',
			'gateways',
			'generic',
			'importexport',
			'oaiMetadataFormats',
			'paymethod',
			'pubIds',
			'reports',
			'themes'
		);
	}

	/**
	 * Get the top-level context DAO.
	 * @return ContextDAO
	 */
	public static function getContextDAO() {
		return DAORegistry::getDAO('JournalDAO');
	}

	/**
	 * Get the context settings DAO.
	 * @return SettingsDAO
	 */
	public static function getContextSettingsDAO() {
		return DAORegistry::getDAO('JournalSettingsDAO');
	}

	/**
	 * Get the submission DAO.
	 * @deprecated Just use DAORegistry::getDAO('SubmissionDAO') directly.
	 * @return SubmissionDAO
	 */
	public static function getSubmissionDAO() {
		return DAORegistry::getDAO('SubmissionDAO');
	}

	/**
	 * Get the section DAO.
	 * @return SectionDAO
	 */
	public static function getSectionDAO() {
		return DAORegistry::getDAO('SectionDAO');
	}

	/**
	 * Get the representation DAO.
	 * @return RepresentationDAO
	 */
	public static function getRepresentationDAO() {
		return DAORegistry::getDAO('ArticleGalleyDAO');
	}

	/**
	 * Get a SubmissionSearchIndex instance.
	 */
	public static function getSubmissionSearchIndex() {
		import('classes.search.ArticleSearchIndex');
		return new ArticleSearchIndex();
	}

	/**
	 * Get a SubmissionSearchDAO instance.
	 */
	public static function getSubmissionSearchDAO() {
		return DAORegistry::getDAO('ArticleSearchDAO');
	}

	/**
	 * Returns the name of the context column in plugin_settings
	 * @return string
	 */
	public static function getPluginSettingsContextColumnName() {
		if (defined('SESSION_DISABLE_INIT')) {
			$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
			$driver = $pluginSettingsDao->getDriver();
			switch ($driver) {
				case 'mysql':
				case 'mysqli':
					$checkResult = $pluginSettingsDao->retrieve('SHOW COLUMNS FROM plugin_settings LIKE ?', array('context_id'));
					if ($checkResult->NumRows() == 0) {
						return 'journal_id';
					}
					break;
				case 'postgres':
				case 'postgres64':
				case 'postgres7':
				case 'postgres8':
				case 'postgres9':
					$checkResult = $pluginSettingsDao->retrieve('SELECT column_name FROM information_schema.columns WHERE table_name = ? AND column_name = ?', array('plugin_settings', 'context_id'));
					if ($checkResult->NumRows() == 0) {
						return 'journal_id';
					}
					break;
				default: fatalError('Unknown database type!');
			}
		}
		return 'context_id';
	}

	/**
	 * Get the stages used by the application.
	 * @return array
	 */
	public static function getApplicationStages() {
		// Only one stage in OPS
		return array(
				WORKFLOW_STAGE_ID_PRODUCTION
		);
	}

	/**
	 * Returns the context type for this application.
	 * @return int ASSOC_TYPE_...
	 */
	public static function getContextAssocType() {
		return ASSOC_TYPE_JOURNAL;
	}

	/**
	 * Get the file directory array map used by the application.
	 */
	public static function getFileDirectories() {
		return array('context' => '/journals/', 'submission' => '/articles/');
	}

}
