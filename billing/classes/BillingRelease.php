<?php

/**
 * Created by PhpStorm.
 * User: Mihai
 * Date: 10/22/2016
 * Time: 8:08 PM
 *
 * Enabling Billing
 */
class BillingRelease
{
    const ENABLE_PORTAL_PAYMENTS    = true;

    const INVOICE_DOWNLOAD_READY    = true;
    const INVOICE_PRINT_READY       = true;

    // @DEFAULT false
    // set this to true to show invoices when the billing accounts have not been set up yet (i.e. no payment methods exist on the account)
    const SHOW_INVOICES_ON_PENDING_ACCOUNT  = false;

    static $companyMap;
    static $enforcePayWall;

    private static function init()
    {
        if ( !self::$companyMap ) {
            // map for enabling billing by builder
            self::$companyMap = array(
                // Landconnect
                1   => true,
                // Simonds Homes
                2   => true,
                // Burbank Homes
                3   => true,
                // Porter Davis
                4   => true,
                // Admin/Batch
                5   => true,
                // Eight Homes
                6   => true,
                // Urban Edge
                7   => false,
                // Sherridon Homes
                8   => false,
                // Orbit Homes
                9   => true,
                // Dennis Family Homes
                10  => false,
                // Boutique Homes
                11  => false,
                // Style Master
                12  => false,
                // Eden Brae
                13  => false,
                // Watersun Homes
                14  => false,
                // Bold Properties
				15	=> true,
                // Burbank QLD (beta)
				16  => false,
                // Mega Homes
                17 => true,
				// Mimosa Homes
				18 => true,
                // Dale Alcock Test
				19 => false,
                // Beechwood
				20 => true,
                // Kingsbridge
                24 => true,
                // Invision
                26 => true,
                // HomeCorp
                27 => true,
                // Unison Homes
                31 => true,
                // True Value Homes
                32 => true,
				// Rawdon Hill
				33 => true,
				// Arli Homes
				34 => true,
                // Aston Homes
                35 => true,
                // Aplace
                36 => true,
                // UHomes
                37 => true,
				// CJHomes
				38 => true,
                // @TODO Important Info: You have to create the Braintree Customer for a new client manually!
                // Billing Test Company
                999 => true
            );
        }

        if ( !self::$enforcePayWall ) {
            // don't enforce the paywall before all the billing integration becomes stable
            self::$enforcePayWall = array(
                1   => false,
                2   => false,
                3   => false,
                4   => false,
                5   => false,
                6   => false,
                7   => false,
                8   => false,
                9   => false,
                10  => false,
                11  => false,
                12  => false,
                13  => false,
                14  => false,
                15	=> false,
                16  => false,
                17  => false,
                18  => false,
                19  => false,
                20  => false,
                24  => false,
                25  => false,
                26  => false,
                27  => false,
                31  => false,
                32  => false,
				33  => false,
				34  => false,
				35  => false,
				36  => false,
				37	=> false,
				38	=> false,
				39	=> false,
                // @TODO Important Info: You have to create the Braintree Customer for a new client manually!
                999 => false
            );
        }
    }

    /**
     * return true if billing is enabled for a certain company
     * @param $companyId
     * @return bool
     */
    public static function enabledFor( $companyId )
    {
        self::init();

        // @TEMP
        if ($companyId==20 || intval($companyId)==20)
            return true;

        if ( isset(self::$companyMap[intval($companyId)]) && self::$companyMap[ intval($companyId) ] ) {
            return true;
        }

        return false;
    }

    /**
     * return true if the PayWall should be inforced for the indicated company
     * @param $companyId
     * @return bool
     */
    public static function enforcePayWallFor($companyId )
    {
        return self::enabledFor( $companyId ) &&
            isset(self::$enforcePayWall[ $companyId ]) &&
        self::$enforcePayWall[ $companyId ];
    }
}