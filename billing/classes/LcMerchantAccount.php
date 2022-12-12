<?php

/**
 * Created by PhpStorm.
 * User: Mihai
 * Date: 7/10/2017
 * Time: 3:49 PM
 */
class LcMerchantAccount
{
    // unique merchant account ID, as defined on Braintree
    private $id;

    //
    // Location / Currency / Tax settings
    //
    // country that this merchant account is associated with
    private $country;
    // use the imperial measurements system
    private $useImperial;
    // e.g. $, €, £
    private $currencyPrefix;
    // e.g. USD, AUD, NZD
    private $currencyISOCode;
    // applied tax rate in the current country, between 0 and 1
    private $taxRate;

    // unique Braintree Subscription plan ID
    private $subscriptionPlanId;

    // ABN
    private $businessName;
    private $businessPartialName;
    private $businessABN;
    private $businessAddress;
    private $businessCity;
    private $businessCountry;


    public function __construct(
        $id,
        $country,
        $useImperial,
        $currencyPrefix,
        $currencyISOCode,
        $taxRate,
        $subscriptionPlanId,
        $businessName,
        $businessPartialName,
        $businessABN,
        $businessAddress,
        $businessCity,
        $businessCountry
    )   {
        $this->id                  = $id;
        $this->country             = $country;
        $this->useImperial         = $useImperial;
        $this->currencyPrefix      = $currencyPrefix;
        $this->currencyISOCode     = $currencyISOCode;
        $this->taxRate             = $taxRate;
        $this->subscriptionPlanId  = $subscriptionPlanId;
        $this->businessName        = $businessName;
        $this->businessPartialName = $businessPartialName;
        $this->businessABN         = $businessABN;
        $this->businessAddress     = $businessAddress;
        $this->businessCity        = $businessCity;
        $this->businessCountry     = $businessCountry;
    }

    /** @return string  */
    public function getId() { return $this->id; }

    /** @return string  */
    public function getCountry() { return $this->country; }
    /** @return int */
    public function getUseImperial() { return $this->useImperial; }
    /** @return string */
    public function getCurrencyPrefix() { return $this->currencyPrefix; }
    /** @return string  */
    public function getCurrencyISOCode() { return $this->currencyISOCode; }
    /** @return double  */
    public function getTaxRate() { return $this->taxRate; }
    /** @return bool */
    public function hasTax() { return $this->taxRate>0; }
    /** @return string - make this variable if needed*/
    public function getTaxName() { return "GST"; }

    /** @return string  */
    public function getSubscriptionPlanId() { return $this->subscriptionPlanId; }

    /** @return string */
    public function getBusinessABN()            { return $this->businessABN; }
    /** @return string */
    public function getBusinessName()           { return $this->businessName; }
    /** @return string */
    public function getBusinessPartialName()    { return $this->businessPartialName; }
    /** @return string */
    public function getBusinessAddress()        { return $this->businessAddress; }
    /** @return string */
    public function getBusinessCity()           { return $this->businessCity; }
    /** @return string */
    public function getBusinessCountry()        { return $this->businessCountry; }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Tax, Net, Gross calculations

    /**
     * samples: $44 AUD (GST included)
     * $120.3 USD
     * @param double $balance the balance to format
     * @param bool $taxIncluded flag that indicates if a 'GST included' suffix should be added
     */
    public function formatCurrency( $balance, $taxIncluded=true )
    {
        // round the balance to 3 decimals
        return $this->currencyPrefix . round( $balance, 3 ) . " " . $this->currencyISOCode .
            ( ( $this->hasTax() && $taxIncluded ) ? " (" . $this->getTaxName() . " included)" : "" );
    }

    /**
     * calculate the gross (tax included) cost from the given net amount
     * @param $net
     * @return mixed
     */
    public function grossCost( $net )
    {
        return $net * ( 1 + $this->taxRate );
    }

    /**
     * calculate the net (tax excluded) cost from the given gross amount
     * @param $gross double
     * @return double
     */
    public function netCost( $gross )
    {
        return $gross / ( 1 + $this->taxRate );
    }

    /**
     * calculate the tax amount from the given gross amount
     * @param $gross double
     * @return double
     */
    public function taxFromGross( $gross )
    {
        return $this->taxRate * $gross / ( 1+$this->taxRate );
    }

    /**
     * @param $net double
     * @return double
     */
    public function taxFromNet( $net )
    {
        return $this->taxRate * $net;
    }

    /**
     * return the tax rate as a percentage representation
     * @return double
     */
    public function taxPercent()
    {
        return 100 * $this->taxRate;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Static

    /**
     * searches for a merchant account by country
     * @param string $merchantAccountID
     * @return LcMerchantAccount
     */
    static public function getFromID($merchantAccountID)
    {
        $accounts = self::enumerateAll();
        foreach ( $accounts as $account ) {
            if ( $account->getId() === $merchantAccountID )
                return $account;
        }

        // Return the Default account
        return self::$LANDCONNECT_ACCOUNT;
    }

    /**
     * @var $_accounts array
     */
    static $_accounts;

    /**
     * @var $LANDCONNECT_ACCOUNT LcMerchantAccount
     * @var $LANDCONNECT_GLOBAL_ACCOUNT LcMerchantAccount
     */
    static public $LANDCONNECT_ACCOUNT;
    static public $LANDCONNECT_GLOBAL_ACCOUNT;

    /**
     * enumerates all the merchant accounts in the application
     * @info accepted country names: https://developers.braintreepayments.com/reference/general/countries/php#list-of-countries
     * @return array the list of merchant accounts that are open
     */
    static public function enumerateAll()
    {
        if (!self::$_accounts) {
            // Landconnect Australia / AUD account
            self::$LANDCONNECT_ACCOUNT = new LcMerchantAccount(
                "landconnectAUD",
                "Australia",
                0,
                "\$",
                "AUD",
                0.1,
                "3pzg",
                "Landconnect Pty Ltd",
                "Landconnect",
                "18 601 164 836",
                "Collins Street Tower<br/>Level 3, 480 Collins Street",
                "Melbourne, VIC 3000",
                "AUSTRALIA"
            );
			
            // Landconnect Global PTY / AUD account
            self::$LANDCONNECT_GLOBAL_ACCOUNT = new LcMerchantAccount(
                "landconnectglobalptyltdAUD",
                "Australia",
                0,
                "\$",
                "AUD",
                0.1,
                "3pzg",
                "Landconnect Global Pty Ltd",
                "Landconnect Global",
                "62 628 354 763",
                "Collins Street Tower<br/>Level 3, 480 Collins Street",
                "Melbourne, VIC 3000",
                "AUSTRALIA"
            );

            self::$_accounts = array(
                self::$LANDCONNECT_ACCOUNT,
                self::$LANDCONNECT_GLOBAL_ACCOUNT
            );
        }

        return self::$_accounts;
    }
}

// Initialize the accounts
LcMerchantAccount::enumerateAll();