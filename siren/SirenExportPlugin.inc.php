<?php

/**
 * @file plugins/importexport/siren/SirenExportPlugin.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SirenExportPlugin
 * @ingroup plugins_importexport_siren
 *
 * @brief Siren/MEDLINE XML metadata export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

class SirenExportPlugin extends ImportExportPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'SirenExportPlugin';
	}

	/**
	 * Get the display name.
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.importexport.siren.displayName');
	}

	/**
	 * Get the display description.
	 * @return string
	 */
	function getDescription() {
		return __('plugins.importexport.siren.description');
	}

	/**
	 * Display the plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		parent::display($args, $request);
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();
		switch (array_shift($args)) {
			case 'index':
			case '':
				$exportSubmissionsListPanel = new \PKP\components\listPanels\PKPSelectSubmissionsListPanel(
					'exportSubmissionsListPanel',
					__('plugins.importexport.native.exportSubmissionsSelect'),
					[
						'apiUrl' => $request->getDispatcher()->url(
							$request,
							ROUTE_API,
							$context->getPath(),
							'_submissions'
						),
						'canSelect' => true,
						'canSelectAll' => true,
						'lazyLoad' => true,
						'selectorName' => 'selectedSubmissions[]',
					]
				);
				$templateMgr->assign('exportSubmissionsListData', [
					'components' => [
						'exportSubmissionsListPanel' => $exportSubmissionsListPanel->getConfig()
					]
				]);
				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;
			case 'exportSubmissions':
				$exportXml = $this->exportSubmissions(
					(array) $request->getUserVar('selectedSubmissions'),
					$request->getContext(),
					$request->getUser()
				);
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$exportFileName = $this->getExportFileName($this->getExportPath(), 'articles', $context, '.xml');
				$fileManager->writeFile($exportFileName, $exportXml);
				$fileManager->downloadByPath($exportFileName);
				$fileManager->deleteByPath($exportFileName);
				break;
			case 'exportIssues':
				$exportXml = $this->exportIssues(
					(array) $request->getUserVar('selectedIssues'),
					$request->getContext(),
					$request->getUser()
				);
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$exportFileName = $this->getExportFileName($this->getExportPath(), 'issues', $context, '.xml');
				$fileManager->writeFile($exportFileName, $exportXml);
				$fileManager->downloadByPath($exportFileName);
				$fileManager->deleteByPath($exportFileName);
				break;
			default:
				$dispatcher = $request->getDispatcher();
				$dispatcher->handle404();
		}
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix() {
		return 'siren';
	}

	function exportSubmissions($submissionIds, $context, $user) {
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$sirenExportFilters = $filterDao->getObjectsByGroup('article=>siren-xml');
		assert(count($sirenExportFilters) == 1); // Assert only a single serialization filter
		$exportFilter = array_shift($sirenExportFilters);
		$submissions = array();
		foreach ($submissionIds as $submissionId) {
			$submission = $submissionDao->getById($submissionId);
			if ($submission && $submission->getData('contextId') == $context->getId()) $submissions[] = $submission;
		}
		libxml_use_internal_errors(true);
		$submissionXml = $exportFilter->execute($submissions, true);
		$xml = $submissionXml->saveXml();
		$errors = array_filter(libxml_get_errors(), function($a) {
			return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
		});
		if (!empty($errors)) {
			$this->displayXMLValidationErrors($errors, $xml);
		}
		return $xml;
	}

	/**
	 * Get the XML for a set of issues.
	 * @param $issueIds array Array of issue IDs
	 * @param $context Context
	 * @param $user User
	 * @return string XML contents representing the supplied issue IDs.
	 */
	function exportIssues($issueIds, $context, $user) {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$sirenExportFilters = $filterDao->getObjectsByGroup('article=>siren-xml');
		assert(count($sirenExportFilters) == 1); // Assert only a single serialization filter
		$exportFilter = array_shift($sirenExportFilters);
		$submissionsIterator = Services::get('submission')->getMany([
			'contextId' => $context->getId(),
			'issueIds' => $issueIds,
		]);
		libxml_use_internal_errors(true);
		$submissionXml = $exportFilter->execute(iterator_to_array($submissionsIterator), true);
		$xml = $submissionXml->saveXml();
		$errors = array_filter(libxml_get_errors(), function($a) {
			return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
		});
		if (!empty($errors)) {
			$this->displayXMLValidationErrors($errors, $xml);
		}
		return $xml;
	}

	/**
	 * Execute import/export tasks using the command-line interface.
	 * @param $args Parameters to the plugin
	 */
	function executeCLI($scriptName, &$args) {
//		$command = array_shift($args);
		$xmlFile = array_shift($args);
		$journalPath = array_shift($args);

		$journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
		$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */

		$journal = $journalDao->getByPath($journalPath);

		if (!$journal) {
			if ($journalPath != '') {
				echo __('plugins.importexport.siren.cliError') . "\n";
				echo __('plugins.importexport.siren.error.unknownJournal', array('journalPath' => $journalPath)) . "\n\n";
			}
			$this->usage($scriptName);
			return;
		}

		if ($xmlFile != '') switch (array_shift($args)) {
			case 'articles':
				$articleSearch = new ArticleSearch();
				$results = $articleSearch->formatResults($args);
				if (!$this->exportArticles($results, $xmlFile)) {
					echo __('plugins.importexport.siren.cliError') . "\n";
					echo __('plugins.importexport.siren.export.error.couldNotWrite', array('fileName' => $xmlFile)) . "\n\n";
				}
				return;
			case 'issue':
				$issueId = array_shift($args);
				$issue = $issueDao->getByBestId($issueId, $journal->getId());
				if ($issue == null) {
					echo __('plugins.importexport.siren.cliError') . "\n";
					echo __('plugins.importexport.siren.export.error.issueNotFound', array('issueId' => $issueId)) . "\n\n";
					return;
				}
				$issues = array($issue);
				if (!$this->exportIssues($journal, $issues, $xmlFile)) {
					echo __('plugins.importexport.siren.cliError') . "\n";
					echo __('plugins.importexport.siren.export.error.couldNotWrite', array('fileName' => $xmlFile)) . "\n\n";
				}
				return;
		}
		$this->usage($scriptName);

	}

	/**
	 * Display the command-line usage information
	 */
	function usage($scriptName) {
		echo __('plugins.importexport.siren.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";
	}
}


