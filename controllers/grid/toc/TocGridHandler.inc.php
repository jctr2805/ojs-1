<?php

/**
 * @file controllers/grid/toc/TocGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TocGridHandler
 * @ingroup controllers_grid_toc
 *
 * @brief Handle TOC (table of contents) grid requests.
 */

import('lib.pkp.classes.controllers.grid.CategoryGridHandler');
import('controllers.grid.toc.TocGridCategoryRow');
import('controllers.grid.toc.TocGridRow');

use PKP\core\JSONMessage;
use PKP\submission\PKPSubmission;
use PKP\security\authorization\ContextAccessPolicy;

use APP\security\authorization\OjsIssueRequiredPolicy;
use APP\submission\Submission;

class TocGridHandler extends CategoryGridHandler
{
    public $submissionsBySectionId = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [ROLE_ID_MANAGER],
            ['fetchGrid', 'fetchCategory', 'fetchRow', 'saveSequence', 'removeArticle', 'setAccessStatus']
        );
        $this->submissionsBySectionId = [];
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $this->addPolicy(new OjsIssueRequiredPolicy($request, $args));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc CategoryGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);

        //
        // Grid columns.
        //
        import('controllers.grid.toc.TocGridCellProvider');
        $tocGridCellProvider = new TocGridCellProvider();

        // Article title
        $this->addColumn(
            new GridColumn(
                'title',
                'article.title',
                null,
                null,
                $tocGridCellProvider
            )
        );

        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
        if ($request->getJournal()->getData('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION && $issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION) {
            // Article access status
            $this->addColumn(
                new GridColumn(
                    'access',
                    'reader.openAccess',
                    null,
                    'controllers/grid/common/cell/selectStatusCell.tpl',
                    $tocGridCellProvider,
                    ['width' => 20, 'alignment' => COLUMN_ALIGNMENT_CENTER]
                )
            );
        }
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new OrderCategoryGridItemsFeature(ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS, true, $this)];
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
     */
    public function getCategoryRowIdParameterName()
    {
        return 'sectionId';
    }

    /**
     * @copydoc GridDataProvider::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
        return array_merge(
            parent::getRequestArgs(),
            ['issueId' => $issue->getId()]
        );
    }

    /**
     * Get the row handler - override the default row handler
     *
     * @return TocGridRow
     */
    protected function getRowInstance()
    {
        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
        return new TocGridRow($issue->getId());
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowInstance()
     */
    protected function getCategoryRowInstance()
    {
        return new TocGridCategoryRow();
    }

    /**
     * @copydoc CategoryGridHandler::loadCategoryData()
     *
     * @param null|mixed $filter
     */
    public function loadCategoryData($request, &$section, $filter = null)
    {
        return $this->submissionsBySectionId[$section->getId()];
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
        $submissionsInSections = Services::get('submission')->getInSections($issue->getId(), $request->getContext()->getId());
        foreach ($submissionsInSections as $sectionId => $articles) {
            foreach ($articles['articles'] as $article) {
                $this->submissionsBySectionId[$sectionId][$article->getId()] = $article;
            }
        }
        $sections = Application::get()->getSectionDao()->getByIssueId($issue->getId());
        $arrayKeySections = [];
        foreach ($sections as $section) {
            $arrayKeySections[$section->getId()] = $section;
        }
        return $arrayKeySections;
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     *
     * @param Section|Submission $object
     */
    public function getDataElementSequence($object)
    {
        if ($object instanceof Submission) {
            return $object->getCurrentPublication()->getData('seq');
        } else { // section
            $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
            $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
            $customOrdering = $sectionDao->getCustomSectionOrder($issue->getId(), $object->getId());
            if ($customOrdering === null) { // No custom ordering specified; use default section ordering
                return $object->getSequence();
            } else { // Custom ordering specified.
                return $customOrdering;
            }
        }
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $sectionId, $gridDataElement, $newSequence)
    {
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
        if (!$sectionDao->customSectionOrderingExists($issue->getId())) {
            $sectionDao->setDefaultCustomSectionOrders($issue->getId());
        }
        $sectionDao->updateCustomSectionOrder($issue->getId(), $sectionId, $newSequence);
    }

    /**
     * @copydoc CategoryGridHandler::getDataElementInCategorySequence()
     */
    public function getDataElementInCategorySequence($categoryId, &$submission)
    {
        return $submission->getCurrentPublication()->getData('seq');
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementInCategorySequence($sectionId, &$submission, $newSequence)
    {
        $publication = $submission->getCurrentPublication();
        $params = ['seq' => $newSequence];
        if ($sectionId != $publication->getData('sectionId')) {
            $params['sectionId'] = $sectionId;
        }
        $publication = Services::get('publication')->edit($publication, $params, Application::get()->getRequest());
    }

    //
    // Public handler functions
    //
    /**
     * Remove an article from the issue.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function removeArticle($args, $request)
    {
        $journal = $request->getJournal();
        $submission = Services::get('submission')->get((int) $request->getUserVar('articleId'));
        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
        if ($submission && $request->checkCSRF()) {
            foreach ((array) $submission->getData('publications') as $publication) {
                if ($publication->getData('issueId') === (int) $issue->getId()
                        && in_array($publication->getData('status'), [PKPSubmission::STATUS_SCHEDULED, PKPSubmission::STATUS_PUBLISHED])) {
                    $publication = Services::get('publication')->unpublish($publication);
                    $publication = Services::get('publication')->edit(
                        $publication,
                        ['seq' => ''],
                        $request
                    );
                }
            }
            // If the article is the only one in the section, delete the section from custom issue ordering
            $sectionId = $submission->getCurrentPublication()->getData('sectionId');
            $submissionsInSections = Services::get('submission')->getInSections($issue->getId(), $issue->getJournalId());
            if (!empty($submissionsInSections[$sectionId]) && count($submissionsInSections[$sectionId]) === 1) {
                $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
                $sectionDao->deleteCustomSection($issue->getId(), $sectionId);
            }
            return DAO::getDataChangedEvent();
        }

        // If we've fallen through, it must be a badly-specified article
        return new JSONMessage(false);
    }

    /**
     * Set access status on an article.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function setAccessStatus($args, $request)
    {
        $articleId = (int) $request->getUserVar('articleId');
        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
        $submission = Services::get('submission')->get($articleId);
        $publication = $submission ? $submission->getCurrentPublication() : null;
        if ($publication && $publication->getData('issueId') == $issue->getId() && $request->checkCSRF()) {
            $publication = Services::get('publication')->edit($publication, ['accessStatus' => $request->getUserVar('status')], $request);
            return DAO::getDataChangedEvent();
        }

        // If we've fallen through, it must be a badly-specified article
        return new JSONMessage(false);
    }
}
