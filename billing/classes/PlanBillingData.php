<?php

class PlanBillingData
{

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Class Definition

    // a plan with the status paid_freeware has no invoice associated with it in the billing system, meaning
    //   that it was paid outside of it
    const PAID_FREEWARE = 1;
    const PAID          = 2;
    const UNPAID        = 3;

    /**
     * 		Invoice settings
     * @var $tid 					int the ticket ID
     * @var $invoices				array all the invoices on this plan
     * @var $outstanding			array list of outstanding invoices
     * @var $outstandingCharges		array list of LcInvoiceProduct's - all outstanding charges on this plan
     */
    private $tid;

    public $invoices;
    public $outstanding;
    public $outstandingCharges;

    public $outstandingAmount;

    private $billingStatus;

    function __construct( $tid )
    {
        // invoice caching is used in LcInvoice::allWithProduct, which makes sure that invoice requests from
        // hundreds of plans isn't slow
        $this->tid      = $tid;
        $this->invoices = LcInvoice::allWithProduct( $tid );

        $this->outstanding          = array();
        $this->outstandingCharges   = array();
        $this->outstandingAmount    = 0;

        /**
         * @var $invoice LcInvoice
         */
        foreach ( $this->invoices as $invoice ) {
            if ( !$invoice->isPaidOrSettling() ) {
                $this->outstanding[]        = $invoice;
                if ( ( $product = $invoice->getProduct( $this->tid ) ) != null ) {
                    $this->outstandingCharges[] = $product;
                    $this->outstandingAmount   += $product->cost;
                }
            }
        }

        // prepare the billing status
        if ( !sizeof($this->invoices) )
            $this->billingStatus = self::PAID_FREEWARE;
        else if ( !sizeof($this->outstanding) )
            $this->billingStatus = self::PAID;
        else
            $this->billingStatus = self::UNPAID;
    }

    public function getBillingStatus()
    {
        return $this->billingStatus;
    }
}

?>