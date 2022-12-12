////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// PayWall functions

/**
 * called when the user is (already) subscribed and has full access to the account
 */
function payWallUserSubscribed()
{
    console.log("@TODO: already subscribed...");
    payWallSuccess();
}

/**
 * called when the pay wall encounters an error
 */
function payWallError( $content, $title )
{
    payWallInfoShow({
        "title"     : ( typeof $title === undefined ) ? "Oops" : $title,
        "content"   : $content,
        "error"     : true
    });
}

function payWallLoading()
{
    payWallInfoShow({
        "loading"     : true
    });
}

/**
 * display the pay wall info overlay, which covers the entire page
 * @param $params settings for the info to show:
 {
    title: "title",
    content: "content",
    error: true/false,
    loading: true/false
 }
 * @param $content
 * @param $title
 * @param $asError
 */
function payWallInfoShow( $params )
{
    // setup the content of the info overlay
    $("#infoPanelTitle").removeClass().html(
        'title' in $params ? $params['title'] : ''
    );
    $("#infoPanelContent").html(
        'content' in $params ? $params['content'] : ''
    );

    // error overlay
    if ( 'error' in $params && $params['error'] ) {
        $("#infoPanelTitle").addClass('error');
    }
    // loading overlay
    if ( 'loading' in $params && $params['loading'] ) {
        $("#infoPanelLoading").show();
    }   else {
        $("#infoPanelLoading").hide();
    }

    // show the overlay
    $("#infoPanel").show();
}

function payWallInfoHide()
{
    $("#infoPanel").hide();
}

// called to cancel/close the paywall (if possible)
function payWallCancel()
{
    if ( window.parent ) {
        window.parent.cancelPayWall();
    }   else {
        // @TODO: redirect to index/logout
        window.top.location.reload(false);
    }
}

function payWallSuccess()
{
    if ( window.parent ) {
        window.parent.onPayWallSuccess();
    }   else {
        window.top.location.reload(false);
    }
}