<?php
/**
 * @file classes/security/authorization/OjsJournalMustPublishPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OjsJournalMustPublishPolicy
 * @ingroup security_authorization
 *
 * @brief Access policy to limit access to journals that do not publish online.
 */

namespace APP\security\authorization;

use PKP\security\authorization\PolicySet;
use PKP\security\authorization\AuthorizationPolicy;

class OjsJournalMustPublishPolicy extends AuthorizationPolicy
{
    public $_context;

    /**
     * Constructor
     *
     * @param $request PKPRequest
     */
    public function __construct($request)
    {
        parent::__construct('user.authorization.journalDoesNotPublish');
        $this->_context = $request->getContext();
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    public function effect()
    {
        if (!$this->_context) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Certain roles are allowed to see unpublished content.
        $userRoles = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        if (count(array_intersect(
            $userRoles,
            [
                ROLE_ID_MANAGER,
                ROLE_ID_SITE_ADMIN,
                ROLE_ID_ASSISTANT,
                ROLE_ID_SUB_EDITOR,
                ROLE_ID_SUBSCRIPTION_MANAGER,
            ]
        )) > 0) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        if ($this->_context->getData('publishingMode') == PUBLISHING_MODE_NONE) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\security\authorization\OjsJournalMustPublishPolicy', '\OjsJournalMustPublishPolicy');
}
