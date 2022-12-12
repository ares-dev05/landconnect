<?php

/**
 * Class LcUserRole
 * - defines the role and permissions the currently logged-in user has on a certain billing account
 * - billing accounts are always linked for the currently logged-in user,
 * 		except for admins, which can load any billing account
 * - however, user roles are ALWAYS associated with the currently logged-in user; this is why we tie the user role with
 * 		the global $loggedInUser variable
 */
class LcUserRole
{
	// no billing access
	const BILLING_LEVEL_NONE	 = 0;
	// can make payments
	const BILLING_LEVEL_PAYMENTS = 1;
	// full access
	const BILLING_LEVEL_FULL	 = 2;

	// access restricted on the current billing account
	const ROLE_NONE		= 0;
	// scripts that run with no logged-in user to process webhooks or other automated tasks
	const ROLE_ROBOT	= 1;
	// can view and use apps on the current account
	const ROLE_GUEST	= 1;
	// can edit content in the apps (e.g. plan portal) + make payments
	const ROLE_EDITOR	= 2;
	// owner of the billing account, has access to the billing section
	const ROLE_OWNER	= 3;
	// global admin; all actions are permitted on any billing account
	const ROLE_ADMIN	= 4;

	/**
	 * restriction messages
	 */

	/**
	 * @var $loggedInUser loggedInUser	the currently logged-in user object
	 * @var $type int					the role type (one of the ROLE_ constants)
	 */
	private $loggedInUser;
	private $type;

	/**
	 * permission grants
	 */

	/**
	 * @param $level
	 * @return bool
	 */
	private function hasPrivileges( $level ) { return $this->role() >= $level; }

	/**
	 * permission to use this account's services
	 * @return bool
	 */
	public function canUse() { return self::hasPrivileges( self::ROLE_GUEST ); }
	public function useDenied() { return "You do not have access to this account."; }

	/**
	 * permission to make purchases on this account
	 * @return bool
	 */
	public function canMakePayments() { return self::hasPrivileges( self::ROLE_EDITOR ); }
	public function paymentsDenied() { return "You cannot make payments on this account. Please contact your supervisor to request access."; }

	/**
	 * permission to view the billing section
	 * @return bool
	 */
	public function canViewBilling() { return self::hasPrivileges( self::ROLE_OWNER ); }
	public function viewBillingDenied() { return "You do not have access to the billing section."; }

	/**
	 * permission to subscribe
	 */
	public function canSubscribe() { return self::hasPrivileges( self::ROLE_OWNER ); }
	public function subscribeDenied() { return "You are not authorized to purchase this service. Please contact your supervisor to request access."; }

	/**
	 * permission to edit the billing details
	 * @return bool
	 */
	public function canManageBilling() { return self::hasPrivileges( self::ROLE_OWNER); }
	public function manageBillingDenied() { return "You cannot make changes to the billing section."; }

	/**
	 * soft cancels are equivalent with disabling the auto-renewal
	 * @return bool
	 */
	public function canCancelSoft() { return self::hasPrivileges( self::ROLE_OWNER ); }
	public function softCancelDenied() { return "You cannot make changes to the billing section."; }

	/**
	 * hard cancels are equivalent with an immediate cancellation of the subscription on Braintree
	 * @return bool
	 */
	public function canCancelHard() { return self::hasPrivileges( self::ROLE_ADMIN ); }
	public function hardCancelDenied() { return "You cannot cancel this service directly. Please contact Landconnect if you wish to terminate your account."; }

	/**
	 * @return bool
	 */
	public function canEditInvoice() { return self::hasPrivileges( self::ROLE_ADMIN ); }
	public function editInvoiceDenied() { return "You cannot edit this invoice. Please contact Landconnect if you need assistance."; }

	/**
	 * @return bool
	 */
	public function canDelete() { return self::hasPrivileges( self::ROLE_ADMIN ); }
	public function deleteDenied() { return "You cannot delete this resource. Please contact Landconnect if you need assistance."; }

	/**
	 * @return bool
	 */
	public function canRefund() { return self::hasPrivileges( self::ROLE_ADMIN); }
	public function refundDenied() { return "Please contact Landconnect if you want to cancel a purchase and request a refund."; }
	
	/**
	 * @return loggedInUser
	 */
	public function user() { return $this->loggedInUser; }

	/**
	 * @return int
	 */
	public function role() { return $this->type; }

	/**
	 * LcUserRole constructor.
	 * @param loggedInUser $loggedInUser	the currently user that is logged in to the application
	 * @param bool $isOwnAccount			set to true if this is the user's billing account (false for admins)
	 */
	function __construct( $loggedInUser, $isOwnAccount )
	{
		$this->loggedInUser	= $loggedInUser;

		if ( !$loggedInUser ) {
			// robot role
			// @TODO: make sure this can't be abused by others
			$this->type = self::ROLE_ROBOT;
		}	else {
			// @TODO: update this to work correctly for single users
			if (isGlobalAdmin($this->loggedInUser->user_id)) {
				$this->type = self::ROLE_ADMIN;
			} else {
				switch ($this->loggedInUser->billing_access_level) {
					case self::BILLING_LEVEL_FULL:
						$this->type = self::ROLE_OWNER;
						break;

					case self::BILLING_LEVEL_PAYMENTS:
						$this->type = self::ROLE_EDITOR;
						break;

					case self::BILLING_LEVEL_NONE:
						$this->type = $isOwnAccount ?
							self::ROLE_GUEST :
							self::ROLE_NONE;
						break;

					default:
						$this->type = self::ROLE_NONE;
						break;
				}
			}
		}
	}
}