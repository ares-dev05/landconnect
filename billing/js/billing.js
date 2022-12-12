////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Global data

var nonceSubmitted      = false;
var fnOnNonceReceived   = null;
var payButtonName       = "MAKE PAYMENT";
var endOnSubscribed     = true;
var invoiceId           = 0;

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Subscription payments

/**
 * calls the subscription API to attempt to subscribe the user
 * @param event
 * @param nonce
 */
function subscribeOnNonceReceived(event, nonce)
{
    if ( nonceSubmitted ) {
        // the pay button was probably clicked multiple times
        return;
    }
    nonceSubmitted = true;
    // show a 'loading' content
    payWallLoading('Subscribing you...');

    // disable the purchase button
    $( "#payBtn" ).prop( "disabled", true ).prop( "value", "Please Wait..." ).addClass( "btnGray" );

    // clear the feedback message
    $( "#payment_feedback" ).html("");

    // ajax this to the server to receive a response
    subscribeUser(
        // @TODO: support multiple plan IDs for single users
        // plan ID
        "none",
        // payment method nonce
        nonce,
        // on success
        onSubscribeSuccess,
        // on failure
        onPaymentFailure
    );
}

/**
 * callback for a successful subscription API call
 * @param data
 */
function onSubscribeSuccess( data )
{
    // hide the loading info
    payWallInfoHide();

    if ( data ) {
        if ( data.subscription ) {
            var subData = data.subscription;

            /*
             status = Pending
             endsAtString = '30th of September 2016
             startsAt.date = '2016-09-01 00:00:00'
             */

            // we have no more use for the payment section; hide it (or remove it completely);
            $("#payment_section").hide().remove();

            var result = "";
            if (subData.status == "Active") {
                // the first payment was received, subscription is immediately active
                result =
                    "<h1>Thank you!</h1>" +
                    "<p>You subscription has now been activated.</p>";
                    // "<p>Your subscription will renew automatically on the " + subData.endsAtString + "</p>"
            } else if (subData.status == "Pending") {
                // the first payment has not been received yet - subscription will start later
                result =
                    "<h1>Thank you!</h1>" +
                    "<p>You subscription has now been activated.</p>";
                    // "<p>The first payment for your account will be processed on the " + subData.startsAtString + "</p>" +
                    // "<p>Your subscription will automatically monthly.</p>"
            }
            // show the payment success
            $("#payment_result").show().html(
                result +
                "<input type='button' class='btn btn-success' id='continue' value='Back To Billing' />"
            );

            // reload the whole page on continue; if the subscription is in order the PayWall shouldn't trigger anymore
            $("#continue").click(function () {
                payWallSuccess();
            });

            return;
        }
    }

    // end the paywall
    payWallSuccess();
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Overdue payment retries

/**
 * calls the subscription API to attempt to subscribe the user
 * @param event
 * @param nonce
 */
function retryOverdueOnNonceReceived(event, nonce) {
    if ( nonceSubmitted ) {
        // the pay button was probably clicked multiple times
        return;
    }
    nonceSubmitted = true;
    // show a 'loading' content
    payWallLoading('Making payment...');

    // disable the purchase button
    $( "#payBtn" ).prop( "disabled", true ).prop( "value", "Please Wait..." ).addClass( "btnGray" );

    // clear the feedback message
    $( "#payment_feedback" ).html("");

    // ajax this to the server to receive a response
    retryPastDuePayment(
        // payment method nonce
        nonce,
        // on success
        onSubscribeSuccess,
        // on failure
        onPaymentFailure
    );
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Invoice Payments

/**
 * Setup the invoice payment form
 */
function loadInvoicePaymentForm()
{
    // hide the 'loading' screen
    payWallInfoHide();

    // generate the payment form
    $( "#payment_form" ).html(
        "<div class='navigation' style='padding-top:36px;'>"+
        "<input id='payBtn' class='btn btn-success' type='submit' value='MAKE PAYMENT' style='width:200px;'>"+
        "</div>"
    );

    // make the payment when the 'make payment' button is clicked
    $( "#payBtn" ).click( payInvoiceOnNonceReceived );
}

/**
 * calls the subscription API to attempt to subscribe the user
 * @TODO: see if we do need a NONCE here
 */
function payInvoiceOnNonceReceived( ) {
    if ( nonceSubmitted ) {
        // the pay button was probably clicked multiple times
        return;
    }
    nonceSubmitted = true;
    // show a 'loading' content
    payWallLoading('Making payment...');

    // disable the purchase button
    $( "#payBtn" ).prop( "disabled", true ).prop( "value", "Please Wait..." ).addClass( "btnGray" );

    // clear the feedback message
    $( "#payment_feedback" ).html("");

    // ajax this to the server to receive a response
    makeInvoicePayment(
        // payment method nonce
        // nonce,
        // invoice id
        invoiceId,
        // on success
        onInvoicePaymentSuccess,
        // on failure
        onInvoicePaymentFailure
    );
}

/**
 * callback for payment API failures
 * @param errorMessage
 */
function onInvoicePaymentFailure( errorMessage )
{
    outputError(
        errorMessage,
        loadInvoicePaymentForm
    )
}

/**
 * callback for a successful invoice payment API
 * @param data
 */
function onInvoicePaymentSuccess( data )
{
    // hide the loading info
    payWallInfoHide();

    if ( data ) {
        /*
        if ( data.subscription ) {
            var subData = data.subscription;

            // we have no more use for the payment section; hide it (or remove it completely);
            $("#payment_section").hide().remove();

            var result = "";
            if (subData.status == "Active") {
                // the first payment was received, subscription is immediately active
                result =
                    "Thank you! Your payment has been received and you now have access to Footprints.<br/>" +
                    "Your subscription will renew automatically on the " + subData.endsAtString + "<br/>"
            } else if (subData.status == "Pending") {
                // the first payment has not been received yet - subscription will start later
                result =
                    "Thank you! You have successfully subscribed to Footprints.<br/>" +
                    "The first payment for your account will be processed on the " + subData.startsAtString + "<br/>" +
                    "Your subscription will automatically monthly.<br/>"
            }
            // show the payment success
            $("#payment_result").show().html(
                result +
                "<input type='button' id='continue' value='Continue' />"
            );

            // reload the whole page on continue; if the subscription is in order the PayWall shouldn't trigger anymore
            $("#continue").click(function () {
                window.location.reload(false);
            });

            return;
        }
        */
    }

    // end the paywall
    payWallSuccess();
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Edit default Subscription payment method

/**
 * calls the subscription API to attempt to subscribe the user
 * @param event
 * @param nonce
 */
function editSubscriptionOnNonceReceived(event, nonce) {
    if ( nonceSubmitted ) {
        // the pay button was probably clicked multiple times
        return;
    }
    nonceSubmitted = true;
    // show a 'loading' content
    payWallLoading('Changing your payment method...');

    // disable the purchase button
    $( "#payBtn" ).prop( "disabled", true ).prop( "value", "Please Wait..." ).addClass( "btnGray" );

    // clear the feedback message
    $( "#payment_feedback" ).html("");

    // ajax this to the server to receive a response
    changePaymentMethod(
        // payment method nonce
        nonce,
        // on success
        // @TODO: do we need another success callback? the payment methods are changed through an iframe
        onSubscribeSuccess,
        // on failure
        onPaymentFailure
    );
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// General payment flow callbacks + utils

function outputError( errorMessage, fnOnClose )
{
    // parse the error message
    var baseMessage, prefix="Your transaction could not be processed: ";
    if ( errorMessage.indexOf(prefix) >=0 )
        baseMessage = errorMessage.substr(prefix.length);
    else if ( errorMessage.length < 100 )
        baseMessage = errorMessage;
    else
        baseMessage = "";

    $( "#payment_feedback" ).html(
        errorMessage +
        "<div style='text-align: left; padding: 15px 0 25px '>"+
        // "    <a target='_blank' href='/contactus?e="+encodeURIComponent(baseMessage).replace(/'/g, '%27')+"'>[Contact Landconnect Support]</a>"+
        "    <a href='mailto:support@landconnect.com.au'>[Contact Landconnect Support]</a>"+
        "    </div>"+
        "<div class='navigation'>"+
        "    <input id='closeBtn' class='btn btn-default' type='submit' value='BACK' style='width:200px;'>"+
        "</div>"
    );

    hidePaymentForm();

    $("#closeBtn").click(function(){
        $( "#payment_feedback" ).html("");
        showPaymentForm();
    });

    // continue
    if ( !fnOnClose ) {
        payWallError("This page is not set up correctly. Please contact Landconnect Support for assistance.");
        console.log(fnOnClose);
    }   else {
        fnOnClose();
    }
}

/**
 * callback for payment API failures
 * @param errorMessage
 */
function onPaymentFailure( errorMessage )
{
    outputError(
        errorMessage,
        reloadPaymentForm
    );
    /*
    // @TODO: display all the errors in the PayWall error container ? or there's no need?
    // parse the error message
    var baseMessage, prefix="Your transaction could not be processed: ";
    if ( errorMessage.indexOf(prefix) >=0 )
        baseMessage = errorMessage.substr(prefix.length);
    else if ( errorMessage.length < 100 )
        baseMessage = errorMessage;
    else
        baseMessage = "";

    $( "#payment_feedback" ).html(
        errorMessage +
        "<div style='text-align: left; padding: 15px 0 25px '>\
            <a target='_blank' href='/contactus?e="+encodeURIComponent(baseMessage).replace(/'/g, '%27')+"'>[Contact Landconnect Support]</a>\
            </div>"+
        "<div class='navigation'>\
            <input id='closeBtn' type='submit' value='BACK' style='width:200px;'>\
        </div>"
    );

    hidePaymentForm();

    $("#closeBtn").click(function(){
        $( "#payment_feedback" ).html("");
        showPaymentForm();
    });
    // silently reload the payment form
    reloadPaymentForm( );
    */
}

/**
 * visually hides the payment form and empties its current contents
 */
function hidePaymentForm() {
    $( "#payment_form" ).hide( );
    $( "#payment_details").hide( );
    $( "#retryCancelDiv" ).hide( );
}

/**
 * displays the payment form
 */
function showPaymentForm() {
    // re-enable the nonce submit
    nonceSubmitted = false;
    // show the payment content
    $( "#payment_form" ).show( );
    $( "#payment_details").show( );
    $( "#retryCancelDiv" ).show( );
}

/**
 * reload the payment form after a failed subscription attempt
 */
function reloadPaymentForm( )
{
    // clear its current contents
    $( "#payment_form" ).html("");
    // restart it
    loadUserAndShowContent( true );
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// User/customer management and payment flow functions

/**
 * Load details for the currently logged-in user and start the payment flow
 * @param showAfterError
 */
function loadUserAndShowContent( showAfterError )
{
    console.log("loadUserAndShowContent");

    showAfterError = ( typeof showAfterError !== "undefined" ? showAfterError : false );
    payWallInfoHide();

    loadUserInfo(
        // onSuccess
        function( customer ) {
            if ( customer.isSubscribed && endOnSubscribed ) {
                // user is already subscribed; notify the paywall
                payWallUserSubscribed();
            }
            else {
                // init the braintree payment form
                setupDropinForm( customer.clientToken );
            }
        },
        // onFailure
        function( error ) {
            payWallError( "We couldn't load your profile data. Please try again later, and make sure you are still logged in to Landconnect." );
        }
    );
}

/**
 * Initialize the braintree payment form
 */
function setupDropinForm( clientToken )
{
    // this will prevent from submitting to the target
    $("#subscribe").submit(function (e) { e.preventDefault(); });

    // initiate the braintree environment
    initEnvironment();

    // generate the payment form
    $( "#payment_form" ).html(
                    "<form id='subscribe' method='post' action='/subscribe'>"+
                    "    <input type='hidden' name='payment_method_nonce' id='payment-method-nonce' />"+
                    "    <div id='paypal-container'></div>"+
                    "    <div id='payment_renewal_info'>"+
                    "        <div id='cc_image'>"+
                    "            <img style='margin:0 auto; display:block' src='content/img/card_types_50.png' />"+
                    "        </div>"+
                    "        <div id='renewal_details'>"+
                    "        </div>"+
                    "    </div>"+
                    "    <div class='navigation' style='padding-top:36px;'>"+
                    "        <input id='payBtn' type='submit' value='"+payButtonName+"' class='btn btn-success' style='width:200px;'>"+
                    "    </div>"+
                    "</form>"
    );

    if ( ieVersion() == 7 || ieVersion() == 8 ) {
        alert( "You may experience problems upgrading from this version of Internet Explorer. If you are unable to upgrade, please try from another web browser (such as Chrome, Firefox or Safari) or from another computer. Once upgraded you will be able to use all Premium features from this web browser." );
    }

    // make sure that the page is initialized correctly
    if ( !fnOnNonceReceived ) {
        payWallError("This page is not set up correctly, please contact Landconnect Support for assistance.");
    }   else {
        // setup braintree
        setupBrainTree(
            // client token
            clientToken,
            // custom callback for nonce received events
            fnOnNonceReceived
        );

        payWallInfoHide();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Billing API calls

function loadUserInfo( fnOnSuccess, fnOnFailure )
{
    console.log("loadUserInfo");
    $.ajax({
        type: "POST",
        url: BILLING_API,
        cache: false,
        data: {
            method  : "getcustomer"
        }
    }).done(function(result) {
        response = processJSONResult(result);

        if ( response.success && response.data && response.data.clientToken ) {
            fnOnSuccess( response.data );
        }
        else {
            if ( fnOnFailure != null )
                fnOnFailure( response.errors ? response.errors.join('<br/>') : "An unexpected error was encountered. If you continue to receive this error, please contact Landconnect Support." );
        }
    });
}

/**
 * subscribe the user to a certain plan
 * @param planId
 * @param nonce
 * @param fnOnSuccess
 * @param fnOnFailure
 */
function subscribeUser( planId, nonce, fnOnSuccess, fnOnFailure )
{
    console.log("subscribing "+planId+", "+nonce);
    $.ajax({
        type: "POST",
        url: BILLING_API,
        cache: false,
        data: {
            method               : "subscription/create",
            payment_method_nonce : nonce,
            subscription_plan    : planId
        }
    }).done(function(result) {
        var response = processJSONResult(result);
        if ( response.success ) {
            fnOnSuccess( response.data );
        }
        else {
            if ( fnOnFailure != null )
                fnOnFailure( response.errors ? response.errors.join('<br/>') : "An unexpected error was encountered. If you continue to receive this error, please contact Landconect Support." );
        }
    }).fail(function (jqXHR, textStatus) {
        console.log("subscribe.fail");
        if ( fnOnFailure != null )
            fnOnFailure( "An unexpected error was encountered ("+textStatus+"). If you continue to receive this error, please contact Landconect Support." );
    });
}

/**
 * can only edit for single-user accounts
 * @param autoRenew
 * @param fnOnSuccess
 * @param fnOnFailure
 */
function editAutoRenew( autoRenew, fnOnSuccess, fnOnFailure )
{
    $.ajax({
        type: "POST",
        url: BILLING_API,
        cache: false,
        data: {
            method   : "subscription/editrenew",
            autoRenew: autoRenew
        }
    }).done(function(result) {
        response = processJSONResult(result);

        if ( response.success ) {
            fnOnSuccess( response.data );
        }
        else {
            fnOnFailure( response.errors ? response.errors.join('<br/>') : "An unexpected error was encountered. If you continue to receive this error, please contact Landconnect Support." );
        }
    });
}

/**
 * cancel a subscription permanently
 * @param fnOnSuccess
 * @param fnOnFailure
 */
function cancelSubscription( fnOnSuccess, fnOnFailure )
{
    $.ajax({
        type: "POST",
        url: BILLING_API,
        cache: false,
        data: {
            method      : "subscription/cancel"
        }
    }).done(function(result) {
        response = processJSONResult(result);

        if ( response.success ) {
            fnOnSuccess( response.data );
        }
        else {
            fnOnFailure( response.errors ? response.errors.join('<br/>') : "An unexpected error was encountered. If you continue to receive this error, please contact Landconnect Support." );
        }
    });
}

/**
 * retry a payment for a past due subscription
 * @param nonce string
 * @param fnOnSuccess function
 * @param fnOnFailure function
 */
function retryPastDuePayment( nonce, fnOnSuccess, fnOnFailure )
{
    $.ajax({
        type: "POST",
        url: BILLING_API,
        cache: false,
        data : {
            method               : "subscription/retry",
            payment_method_nonce : nonce
        }
    }).done(function(result) {
        response = processJSONResult(result);

        if ( response.success ) {
            fnOnSuccess( response.data );
        }
        else {
            fnOnFailure( response.errors ? response.errors.join('<br/>') : "An unexpected error was encountered. If you continue to receive this error, please contact Landconnect Support." );
        }
    });
}

/**
 * change the default payment method for the current billing account
 * @param nonce
 * @param fnOnSuccess
 * @param fnOnFailure
 */
function changePaymentMethod( nonce, fnOnSuccess, fnOnFailure )
{
    $.ajax({
        type: "POST",
        url: BILLING_API,
        cache: false,
        data: {
            method               : "subscription/setpaymentmethod",
            payment_method_nonce : nonce
        }
    }).done(function(result) {
        response = processJSONResult(result);

        if ( response.success ) {
            fnOnSuccess();
        }
        else {
            if ( fnOnFailure != null )
                fnOnFailure( response.errors ? response.errors.join('<br/>') : "An unexpected error was encountered. If you continue to receive this error, please contact Landconnect Support." );
        }
    });
}

/**
 * attempt to make an invoice payment
 * @param id
 * @param fnOnSuccess
 * @param fnOnFailure
 */
function makeInvoicePayment( id, fnOnSuccess, fnOnFailure )
{
    $.ajax({
        type: "POST",
        url: BILLING_API,
       cache: false,
        data: {
            method          : "invoice/pay",
            invoice_id      : id
        }
    }).done(function(result) {
        response = processJSONResult(result);

        if ( response.success ) {
            fnOnSuccess();
        }
        else {
            if ( fnOnFailure != null )
                fnOnFailure( response.errors ? response.errors.join('<br/>') : "An unexpected error was encountered. If you continue to receive this error, please contact Landconnect Support." );
        }
    });
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Helper&Init functions

function setupBrainTree( clientToken, fnOnNonceReceived )
{
    braintree.setup(
        // client token
        clientToken,
        // type of form
        "dropin",
        // options
        {
            container: "paypal-container",
            paymentMethodNonceReceived: fnOnNonceReceived,
            paypal: {
            	billingAgreementDescription: "Landconnect.com.au Footprints subscription",
            	displayName: "landconnect.com.au"
            }
        }
    );

    if ( typeof BraintreeData !== "undefined") {
        setupBraintreeDataOnReady();
    }   else {
        window.onBraintreeDataLoad = setupBraintreeDataOnReady;
    }
}

function setupBraintreeDataOnReady() {
    initEnvironment();
    BraintreeData.setup( MERCHANT_ID,  "subscribe", ENVIRONMENT );
}

function processJSONResult(result) {
    if (!result) {
        return {"success": false};
    } else {
        try {
            if (typeof result == "string") {
                return jQuery.parseJSON(result);
            } else {
                return result;
            }
        } catch (err) {
            return {
                "success": false,
                "errors" : [
                    "An unexpected error was encountered. If you continue to receive this error, please contact Landconnect Support."
                ]
            };
        }
    }
}

function ieVersion () {
    var myNav = navigator.userAgent.toLowerCase();
    return (myNav.indexOf("msie") != -1) ? parseInt(myNav.split("msie")[1]) : false;
}