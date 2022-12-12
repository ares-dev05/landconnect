<?php

class WebhookProcessor {

    /**
     * @param Braintree_WebhookNotification $notification
     * @return bool indicating success
     */
    public static function parseWebhookNotification( $notification )
    {
        switch ( $notification->kind ) {
            case Braintree_WebhookNotification::SUBSCRIPTION_CHARGED_SUCCESSFULLY:
                /**
                 * @var $subscription Braintree_Subscription
                 */
                $subscription = $notification->subscription;
                $transactions = $subscription->transactions;

                if ( sizeof( $transactions ) ) {
                    /**
                     * @var $mostRecent Braintree_Transaction
                     * @var $account LcAccount
                     */
                    $mostRecent = $transactions[0];
                    $account    = LcAccount::getForSubscription( $subscription->id );

                    if ( $mostRecent && $account ) {
                        // update the account with the latest data from the braintree subscription
                        if ($account->getSubscription() ) {
                            $account->getSubscription()->updateFromBraintree( $subscription );
                        }

                        // generate an invoice for this subscription transaction
                        $invoice    = LcSubscriptionInvoice::loadForTransaction( $account, $mostRecent );

                        LcMailer::sendReceipt( $mostRecent, $account, $invoice );
                    }   else {
                        // log the email failure
                        $message =
                            "No transaction found or couldn't load account details for ".$notification->kind." \n "
                            . "Notification: " . print_r( $notification, true ) . " \n "
                            . "Recent transaction: " . print_r( $mostRecent, true ) . " \n "
                            . "Account: " . print_r( $account, true ) . "\n";
                        file_put_contents(__DIR__."/../log/webhook-process.log", $message, FILE_APPEND);
                    }
                    // $account    = LcAccount::getForCompany( null, )
                }   else {
                    $message =
                        "Subscription has no transactinos on record \n "
                        . "Notification: " . print_r( $notification, true ) . " \n ";
                    file_put_contents(__DIR__."/../log/webhook-process.log", $message, FILE_APPEND);
                }
                break;

            case Braintree_WebhookNotification::SUBSCRIPTION_WENT_PAST_DUE:
                // @TODO: send a notification to the company letting them know that the payment
                // @TODO is past due and access will be cut off soon
                break;

            case Braintree_WebhookNotification::SUBSCRIPTION_WENT_ACTIVE:
                // @TODO: send a notification to the company letting them know that their subscription
                // @TODO is active (after possibly being past due)
                break;

            // @TODO: handle all other subscription kinds as needed
        }

        // @TODO: return false for failures
        return true;

        /*
        $btSub          = $notification->subscription;
        $subId          = self::braintreeSubId( $btSub->id );
        $subscription   = self::find( $subId );

        if ( $subscription && $subscription->isValid() )
        {
            $hookDT     = $notification->timestamp->format( self::SQL_DATETIME );
            $hookKind   = $notification->kind;

            // check that we didn't process a more recent webhook already on the same subscription
            $result = do_sql(
                "SELECT subscription_id
                 FROM " . self::webhooksTableName . "
                 WHERE subscription_id='{$subId}' AND timestamp>'$hookDT'",
                __FILE__ . '::' . __FUNCTION__,
                true
            );

            if ( $result!==FALSE && !mysql_num_rows($result) ) {
                // there is no more recent webhook that has already been processed on this subscription

                if ( $subscription->updateFromBraintree($btSub) ) {
                    // we want to make sure that the success of this webhook gets recorded in the DB, otherwise
                    // it may be overwritten by an older, pending webhook.
                    return do_sql(
                        "INSERT INTO " . self::webhooksTableName . "
                         SET
                            kind = '{$hookKind}',
                            timestamp = '{$hookDT}',
                            subscription_id = '{$subId}',
                            subscription_status = '{$btSub->status}'
                        ",
                        __FILE__ . '::' . __FUNCTION__
                    );
                }   else {
                    return FALSE;
                }

                 //// compressed syntax:
                return $subscription->updateFromBraintree($btSub) &&
                do_sql(
                "INSERT INTO " . self::webhooksTableName . "
                SET
                kind = '{$hookKind}',
                timestamp = '{$hookDT}',
                subscription_id = '{$subId}',
                subscription_status = '{$btSub->status}'
                ",
                __FILE__ . '::' . __FUNCTION__
                );
                 ////
            }   else {
                // another, more recent webhook has already been processed by this
            }
        }   else {
            //error_log("The subscription with id={$subId} could not be found in the local database. Should it be constructed?");
            return false;
        }
        */
    }
}

?>