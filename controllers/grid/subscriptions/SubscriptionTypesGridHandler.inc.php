<?php

/**
 * @file controllers/grid/subscriptions/SubscriptionTypesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubscriptionTypesGridHandler
 * @ingroup controllers_grid_subscriptions
 *
 * @brief Handle subscription type grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');

import('controllers.grid.subscriptions.SubscriptionTypesGridCellProvider');
import('controllers.grid.subscriptions.SubscriptionTypesGridRow');
import('controllers.grid.subscriptions.SubscriptionTypeForm');

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\core\JSONMessage;
use PKP\notification\PKPNotification;

use APP\notification\NotificationManager;

class SubscriptionTypesGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                ROLE_ID_MANAGER, ROLE_ID_SUBSCRIPTION_MANAGER],
            ['fetchGrid', 'fetchRow', 'editSubscriptionType', 'updateSubscriptionType',
                'deleteSubscriptionType', 'addSubscriptionType']
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Load user-related translations.
        AppLocale::requireComponents(
            LOCALE_COMPONENT_APP_MANAGER
        );

        // Basic grid configuration.
        $this->setTitle('subscriptionManager.subscriptionTypes');

        // Grid actions.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'addSubscriptionType',
                new AjaxModal(
                    $router->url($request, null, null, 'addSubscriptionType', null, null),
                    __('manager.subscriptionTypes.create'),
                    'modal_add_subscription_type',
                    true
                ),
                __('manager.subscriptionTypes.create'),
                'add_subscription_type'
            )
        );

        //
        // Grid columns.
        //
        $cellProvider = new SubscriptionTypesGridCellProvider();

        $this->addColumn(
            new GridColumn(
                'name',
                'common.name',
                null,
                null,
                $cellProvider
            )
        );
        $this->addColumn(
            new GridColumn(
                'type',
                'manager.subscriptionTypes.subscriptions',
                null,
                null,
                $cellProvider
            )
        );
        $this->addColumn(
            new GridColumn(
                'duration',
                'manager.subscriptionTypes.duration',
                null,
                null,
                $cellProvider
            )
        );
        $this->addColumn(
            new GridColumn(
                'cost',
                'manager.subscriptionTypes.cost',
                null,
                null,
                $cellProvider
            )
        );
    }


    //
    // Implement methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return SubscriptionTypesGridRow
     */
    protected function getRowInstance()
    {
        return new SubscriptionTypesGridRow();
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
        return [new PagingFeature()];
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        // Get the context.
        $journal = $request->getContext();

        $subscriptionTypeDao = DAORegistry::getDAO('SubscriptionTypeDAO'); /* @var $subscriptionTypeDao SubscriptionTypeDAO */
        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());
        return $subscriptionTypeDao->getByJournalId($journal->getId(), $rangeInfo);
    }


    //
    // Public grid actions.
    //
    /**
     * Add a new subscription type.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function addSubscriptionType($args, $request)
    {
        // Calling editSubscription with an empty row id will add a new subscription type.
        return $this->editSubscriptionType($args, $request);
    }

    /**
     * Edit an existing subscription type.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function editSubscriptionType($args, $request)
    {
        // Form handling.
        $subscriptionTypeForm = new SubscriptionTypeForm($request->getJournal()->getId(), $request->getUserVar('rowId'));
        $subscriptionTypeForm->initData();
        return new JSONMessage(true, $subscriptionTypeForm->fetch($request));
    }

    /**
     * Update an existing subscription type.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function updateSubscriptionType($args, $request)
    {
        $subscriptionTypeId = (int) $request->getUserVar('typeId');
        // Form handling.
        $subscriptionTypeForm = new SubscriptionTypeForm($request->getJournal()->getId(), $subscriptionTypeId);
        $subscriptionTypeForm->readInputData();

        if ($subscriptionTypeForm->validate()) {
            $subscriptionTypeForm->execute();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS);
            // Prepare the grid row data.
            return DAO::getDataChangedEvent($subscriptionTypeId);
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Delete a subscription type.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function deleteSubscriptionType($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $context = $request->getContext();
        $user = $request->getUser();

        // Identify the subscription type ID.
        $subscriptionTypeId = $request->getUserVar('rowId');
        $subscriptionTypeDao = DAORegistry::getDAO('SubscriptionTypeDAO'); /* @var $subscriptionTypeDao SubscriptionTypeDAO */
        $subscriptionTypeDao->deleteById($subscriptionTypeId, $context->getId());
        return DAO::getDataChangedEvent($subscriptionTypeId);
    }
}
