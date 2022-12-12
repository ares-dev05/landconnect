<?php

/**
 * Created by PhpStorm.
 * User: Latu
 * Date: 1/16/2017
 * Time: 8:17 PM
 */
interface IInvoice
{
    /**
     * @return string
     */
    static public function typeName();

    /**
     * @return bool
     */
    public function isValid();

    /**
     * @return String
     */
    public function getId();

    /**
     * @return int
     */
    public function getAccountId();

    /**
     * @return mixed
     */
    public function getDisplayId();

    /**
     * @return string
     */
    public function getDisplayName();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return String
     */
    public function getDetails();

    /**
     * @return double
     */
    public function getAmount();

    /**
     * @return double
     */
    public function getNetAmount();

    /**
     * @return double
     */
    public function getTaxAmount();

    /**
     * @return double
     */
    public function getAmountPaid();

    /**
     * @return double
     */
    public function getAmountOutstanding();

    /**
     * @return DateTime
     */
    public function getCreatedAt();

    /**
     * @return string
     */
    public function getCreatedAtString();

    /**
     * @return Braintree_Transaction
     */
    public function getTransaction();

    /**
     * @return array
     */
    public function getProducts();

    /**
     * @return string
     */
    public function getPDFFileName();

    /**
     * @return bool
     */
    // public function generatePDF();
}