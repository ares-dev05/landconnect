<?php

require_once(__DIR__."/../../models/class.mail.php");

define('BILLING_EMAIL_ENABLED', true);
define('BILLING_EMAIL_TESTING', true);
// define('BILLING_TEST_EMAIL', "m_dersidan@yahoo.com");
// define('BILLING_TEST_EMAIL', "billing@landconnect.com.au");

class LcMailer {

    /**
     * @param $transaction Braintree_Transaction
     * @param $account LcAccount
     * @param $invoice IInvoice
     */
    public static function sendReceipt( $transaction, $account, $invoice )
    {
        $attachments = array();
        // generate a PDF file for the invoice
        $fileName = LcInvoiceOutput::generatePDF( $invoice );

        if ( $fileName !== null ) {
            $attachments[ $fileName ] = $invoice->getDisplayName().".pdf";
        }

        // send emails with the receipt for this transaction
        return self::sendEmails(
            // email template
            "/billing/receipt.html",
            // title for normal users
            "Transaction receipt",
            // hooks in the email content
            self::receiptHooks( $transaction, $account ),
            // the recipients of this email
            $account->getAdminEmails(),
            // attachments to include in the email
            $attachments
        );
    }
    /**
     * @param $transaction Braintree_Transaction
     * @param $account LcAccount
     */
    private static function receiptHooks( $transaction, $account )
    {
        $paymentType = "N/A";
        $paymentName = "";
        $paymentMethod = "";

        // prepare payment method details
        switch ( $transaction->paymentInstrumentType ) {
            case "credit_card":
                $paymentType     = $transaction->creditCardDetails->cardType;

                if ( $transaction->creditCardDetails->cardholderName && strlen($transaction->creditCardDetails->cardholderName) ) {
                    $paymentName = "Cardholder Name: ".$transaction->creditCardDetails->cardholderName;
                }   else {
                    /**
                     * @var $method Braintree_PaymentMethod
                     */
                    $method      = Braintree_PaymentMethod::find( $transaction->creditCardDetails->token );
                    $paymentName = "Cardholder Name: ".$method->cardholderName;
                }

                //$paymentName     = "Cardholder Name: ".$transaction->creditCardDetails->cardholderName." - ".
                //   $transaction->creditCardDetails->token;
                $paymentMethod   = "Credit Card Ends With: ".$transaction->creditCardDetails->last4;
                break;

            // @TOOD: implement additional payment instruments as needed
        }

        // return hooks
        return array(
            "searchStrs" => array(
                "Landconnect",
                "#TRANSACTION_AMOUNT#",
                "#TRANSACTION_DATE#",
                "#TRANSACTION_TAXES#",
                "#INVOICE_NUMBER#",
                "#ORDER_ID#",
                // "#AUTH_CODE#",
                "#TRANSACTION_STATUS#",

                "#PAYMENT_TYPE#",
                "#PAYMENT_NAME#",
                "#PAYMENT_METHOD#",

                "#CUSTOMER_ABN#",
                "#CUSTOMER_COMPANY#",
                // "#CUSTOMER_EMAIL#",
                "#ADDRESS_CUSTOMER#",
                "#ADDRESS_STREET#",
                "#ADDRESS_CITY_STATE#",
                "#ADDRESS_COUNTRY#"
            ),
            "subjectStrs" => array(
                // Company Name Rename; Will be Landconnect or Landconnect Global
                $account->getMerchantAccount()->getBusinessPartialName(),
                // "#TRANSACTION_AMOUNT#",
                LcAccount::formatCurrencyWithIsoCode(
                    $transaction->amount * 10/11,
                    $account->getCurrencyIsoCode(),
                    false
                ),
                // "#TRANSACTION_DATE#",
                $transaction->createdAt->format(Config::FULL_DATETIME),
                // "#TRANSACTION_TAXES#",
                // @TODO: change how taxes are calculated
                LcAccount::formatCurrencyWithIsoCode(
                    $transaction->amount / 11,
                    $account->getCurrencyIsoCode(),
                    false
                ),
                // "#INVOICE_NUMBER#",
                // @TODO: implement an incremental invoice number generator here
                1,
                // "#ORDER_ID#",
                $transaction->orderId,
                // "#TRANSACTION_STATUS#",
                $transaction->status,

                $paymentType,
                $paymentName,
                $paymentMethod,

                // "#CUSTOMER_ABN#",
                $account->getABN(),
                // "#CUSTOMER_COMPANY#",
                $account->name,
                // "#CUSTOMER_EMAIL#",
                // $transaction->customerDetails->email,
                // "#ADDRESS_CUSTOMER#",
                $account->name,
                // "#ADDRESS_STREET#",
                $account->addressStreet,
                // "#ADDRESS_CITY_STATE#",
                $account->addressCity.", ".$account->addressRegion." ".$account->addressPostalCode,
                // "#ADDRESS_COUNTRY#"
                $account->addressCountry

                // @TODO: run some tests to see if these details are filled in correctly, so that we can
                // @TODO  stop having to use an account
                /*
                // "#CUSTOMER_COMPANY#",
                $transaction->billingDetails->company,
                // "#CUSTOMER_EMAIL#",
                $transaction->customerDetails->email,
                // "#ADDRESS_CUSTOMER#",
                $transaction->billingDetails->company,
                // "#ADDRESS_STREET#",
                $transaction->billingDetails->streetAddress,
                // "#ADDRESS_CITY_STATE#",
                $transaction->billingDetails->region,
                // "#ADDRESS_COUNTRY#"
                $transaction->billingDetails->countryName
                */
            )
        );
    }

    /**
     * @param String $template
     * @param String $title
     * @param array $hooks
     * @param array $recipients
     * @param array $attachments
     * @return bool
     */
    private static function sendEmails( $template, $title, $hooks, $recipients, $attachments )
    {
        // echo "sendEmails()<br/>";
        $mailSender = new userCakeMail();

        // also send to the landconnect billing address
        $recipients []= "billing@landconnect.com.au";
        // also add DEV as a recipient
        $recipients []= "m_dersidan@yahoo.com";

        // If there is a mail failure, fatal error
        if(!$mailSender->newTemplateMsg($template, $hooks) || !sizeof($recipients)) {
            // echo "failed to open email template...<br/>";
            // log the email failure
            $message =
                "Failed to open email template or no recipients {$template} - {$title} \n "
                . "Hooks: " . print_r( $hooks, true ) . " \n "
                . "Recipients: " . join( ", ", $recipients ) . "\n";
            file_put_contents(__DIR__."/../log/mailer.log", $message, FILE_APPEND);
            return false;
        } else {
            // echo "sending emails to ".join(", ", $recipients)."<br/>";
            foreach ($recipients as $recipient) {
                // send the email
                if ( BILLING_EMAIL_ENABLED &&
                    !$mailSender->sendHtmlEmail( $recipient, $title, NULL, $attachments, true ) ) {
                    $message =
                        "Failed to send email to {$recipient} - {$title} \n "
                        . "Hooks: " . print_r( $hooks, true ) . " \n "
                        . "Recipients: " . join( ", ", $recipients ) . "\n";
                    file_put_contents(__DIR__."/../log/mailer.log", $message, FILE_APPEND);
                    return false;
                }
            }
        }

        return true;
    }
}

?>