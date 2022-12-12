<?php

require_once("./classes/pm_init.php");
require_once("./views/elements.php");

// User must be logged in
if (!isUserLoggedIn()){
  header("Location: /login.php");
  exit();
}

// make sure a user is logged-in and has plan portal access
if ( !( $userInfo=getPlanManagementUserCredentials() ) ) {
    addAlert("danger", "You cannot access this page.");
    header("Location: " . getReferralPage());
    exit();
}

// fetch the cookie alerts; @TODO: implement this fully in JS
$cookieAlerts = fetchCookieAlerts();

global $loggedInUser, $billingAccount;
$admin_id			= $loggedInUser->user_id;

// fetch the state of the logged in user
$crtState           = $loggedInUser->state_id;

setReferralPage(getAbsoluteDocumentPath(__FILE__));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Plan Management</title>

    <?php require_once("../account/includes.php");  ?>

    <!-- Page Specific Plugins -->
    <link rel="stylesheet" href="../css/bootstrap-switch.min.css" type="text/css" />

    <script src="../js/date.min.js"></script>
    <script src="../js/handlebars-v1.2.0.js"></script>
    <script src="../js/bootstrap-switch.min.js"></script>
    <script src="../js/jquery.tablesorter.js"></script>
    <script src="../js/tables.js"></script>

    <!-- Include selectize -->
    <script src="../js/standalone/selectize.js"></script>
    <link rel="stylesheet" href="../css/selectize.bootstrap2.css">

    <!-- include bootstrap datepicker -->
    <script src="../js/bootstrap-datepicker.js"></script>
    <link rel="stylesheet" href="../css/bootstrap-datepicker.css">

    <!-- Page Plugin -->
    <script src="js/plan-management.js?v=<?php echo filemtime(__DIR__."/js/plan-management.js"); ?>"></script>
    <link rel="stylesheet" href="css/style.css">
    <script>(function(e,t,n){var r=e.querySelectorAll("html")[0];r.className=r.className.replace(/(^|\s)no-js(\s|$)/,"$1js$2")})(document,window,0);</script>
</head>

<body>

<div id="wrapper">

    <!-- Sidebar -->
    <nav class="app-nav" role="navigation"></nav>

    <div id="page-wrapper" class="plan-portal">
       <div class="container-fluid">
       <div class="page-header">
    	  <h1>Plan Management</h1>
		</div>
       
       <div class="row">
           <div id='display-alerts' class="col-lg-12"></div>
       </div>

       <div class="row" style="margin-bottom: 20px;">
           <div class="container" role="main">
               <!-- file uploading -->
               <form method="post" action="./api/pm_upload_plans.php?submit-on-demand" enctype="multipart/form-data" novalidate class="box">
                   <div class="box__input">
                       <svg class="box__icon" xmlns="http://www.w3.org/2000/svg" width="38" height="32" viewBox="0 0 50 43"><path d="M48.4 26.5c-.9 0-1.7.7-1.7 1.7v11.6h-43.3v-11.6c0-.9-.7-1.7-1.7-1.7s-1.7.7-1.7 1.7v13.2c0 .9.7 1.7 1.7 1.7h46.7c.9 0 1.7-.7 1.7-1.7v-13.2c0-1-.7-1.7-1.7-1.7zm-24.5 6.1c.3.3.8.5 1.2.5.4 0 .9-.2 1.2-.5l10-11.6c.7-.7.7-1.7 0-2.4s-1.7-.7-2.4 0l-7.1 8.3v-25.3c0-.9-.7-1.7-1.7-1.7s-1.7.7-1.7 1.7v25.3l-7.1-8.3c-.7-.7-1.7-.7-2.4 0s-.7 1.7 0 2.4l10 11.6z"/></svg>
                       <input type="file" name="files[]" id="file" class="box__file" data-multiple-caption="{count} files selected" multiple style="display: none;"/>
                       <label for="file"><strong>Choose a file</strong><span class="box__dragndrop"> or drag it here</span>.</label>
                       <br/>
                       <button type="submit" class="btn btn-info" disabled="disabled">Upload</button>
                   </div>

                   <div class="box__uploading">Uploading&hellip;</div>
                   <div class="box__success">Done! <a href="https://css-tricks.com/examples/DragAndDropFileUploading//?submit-on-demand" class="box__restart" role="button">Upload more?</a></div>
                   <div class="box__error">Error! <span></span>. <a href="https://css-tricks.com/examples/DragAndDropFileUploading//?submit-on-demand" class="box__restart" role="button">Try again!</a></div>
               </form>
           </div>
       </div>

       <div class="row" style="margin-top: 20px;">
            <div id='widget-plans' class="col-lg-12"></div>
        </div><!-- /.row -->
		</div><!-- /.container-fluid -->
    </div><!-- /#page-wrapper -->

</div><!-- /#wrapper -->

<script>
    function loadPlans() {
        planManageWidget('widget-plans', {
            title: 'Floorplans',
            limit: 1000,
            sort: 'asc',
            columns: {
                file_name: 'Plan',
                owner: 'Uploader',
                last_update: 'Uploaded At',
                action: 'Delete'
            },
            headers: {
                0: {sorter: 'metatext'},
                1: {sorter: 'metatext'},
                3: {sorter: 'metadate'},
                5: {sorter: false}
            },
            state: <?php echo $crtState; ?>
        });
    }
    
    $(document).ready(function() {
        // Load the header
        $('.app-nav').load('../account/header.php?area=portal', function() {
            $('.navitem-planportal').addClass('active');
        });

<?php
    // display
    if ( strlen($cookieAlerts) ) {
?>
        var alerts = '<?php echo $cookieAlerts; ?>';
        alertWidgetResponse('display-alerts', processJSONResult(alerts));
<?php
    }
?>

        loadPlans();
    });
</script>
<!--
	JQUERY DEPENDENCY
-->
<!--
<script src="jquery-v1.min.js"></script>
<script>

	'use strict';

	;( function( $, window, document, undefined )
	{
		// feature detection for drag&drop upload

		var isAdvancedUpload = function()
			{
				var div = document.createElement( 'div' );
				return ( ( 'draggable' in div ) || ( 'ondragstart' in div && 'ondrop' in div ) ) && 'FormData' in window && 'FileReader' in window;
			}();


		// applying the effect for every form

		$( '.box' ).each( function()
		{
			var $form		 = $( this ),
				$input		 = $form.find( 'input[type="file"]' ),
				$label		 = $form.find( 'label' ),
				$errorMsg	 = $form.find( '.box__error span' ),
				$restart	 = $form.find( '.box__restart' ),
				droppedFiles = false,
				showFiles	 = function( files )
				{
					$label.text( files.length > 1 ? ( $input.attr( 'data-multiple-caption' ) || '' ).replace( '{count}', files.length ) : files[ 0 ].name );
				};

			// letting the server side to know we are going to make an Ajax request
			$form.append( '<input type="hidden" name="ajax" value="1" />' );

			// automatically submit the form on file select
			$input.on( 'change', function( e )
			{
				showFiles( e.target.files );


			});


			// drag&drop files if the feature is available
			if( isAdvancedUpload )
			{
				$form
				.addClass( 'has-advanced-upload' ) // letting the CSS part to know drag&drop is supported by the browser
				.on( 'drag dragstart dragend dragover dragenter dragleave drop', function( e )
				{
					// preventing the unwanted behaviours
					e.preventDefault();
					e.stopPropagation();
				})
				.on( 'dragover dragenter', function() //
				{
					$form.addClass( 'is-dragover' );
				})
				.on( 'dragleave dragend drop', function()
				{
					$form.removeClass( 'is-dragover' );
				})
				.on( 'drop', function( e )
				{
					droppedFiles = e.originalEvent.dataTransfer.files; // the files that were dropped
					showFiles( droppedFiles );


				});
			}


			// if the form was submitted

			$form.on( 'submit', function( e )
			{
				// preventing the duplicate submissions if the current one is in progress
				if( $form.hasClass( 'is-uploading' ) ) return false;

				$form.addClass( 'is-uploading' ).removeClass( 'is-error' );

				if( isAdvancedUpload ) // ajax file upload for modern browsers
				{
					e.preventDefault();

					// gathering the form data
					var ajaxData = new FormData( $form.get( 0 ) );
					if( droppedFiles )
					{
						$.each( droppedFiles, function( i, file )
						{
							ajaxData.append( $input.attr( 'name' ), file );
						});
					}

					// ajax request
					$.ajax(
					{
						url: 			$form.attr( 'action' ),
						type:			$form.attr( 'method' ),
						data: 			ajaxData,
						dataType:		'json',
						cache:			false,
						contentType:	false,
						processData:	false,
						complete: function()
						{
							$form.removeClass( 'is-uploading' );
						},
						success: function( data )
						{
							$form.addClass( data.success == true ? 'is-success' : 'is-error' );
							if( !data.success ) $errorMsg.text( data.error );
						},
						error: function()
						{
							alert( 'Error. Please, contact the webmaster!' );
						}
					});
				}
				else // fallback Ajax solution upload for older browsers
				{
					var iframeName	= 'uploadiframe' + new Date().getTime(),
						$iframe		= $( '<iframe name="' + iframeName + '" style="display: none;"></iframe>' );

					$( 'body' ).append( $iframe );
					$form.attr( 'target', iframeName );

					$iframe.one( 'load', function()
					{
						var data = $.parseJSON( $iframe.contents().find( 'body' ).text() );
						$form.removeClass( 'is-uploading' ).addClass( data.success == true ? 'is-success' : 'is-error' ).removeAttr( 'target' );
						if( !data.success ) $errorMsg.text( data.error );
						$iframe.remove();
					});
				}
			});


			// restart the form if has a state of error/success

			$restart.on( 'click', function( e )
			{
				e.preventDefault();
				$form.removeClass( 'is-error is-success' );
				$input.trigger( 'click' );
			});

			// Firefox focus bug fix for file input
			$input
			.on( 'focus', function(){ $input.addClass( 'has-focus' ); })
			.on( 'blur', function(){ $input.removeClass( 'has-focus' ); });
		});

	})( jQuery, window, document );

</script>
-->


<!--
	NO-DEPENDENCIES (IE 10+)
-->

<script>

    'use strict';

    ;( function ( document, window, index, loadPlans )
    {
        // feature detection for drag&drop upload
        var isAdvancedUpload = function()
        {
            var div = document.createElement( 'div' );
            return ( ( 'draggable' in div ) || ( 'ondragstart' in div && 'ondrop' in div ) ) && 'FormData' in window && 'FileReader' in window;
        }();


        // applying the effect for every form
        var forms = document.querySelectorAll( '.box' );
        Array.prototype.forEach.call( forms, function( form )
        {
            var input		 = form.querySelector( 'input[type="file"]' ),
                label		 = form.querySelector( 'label' ),
                errorMsg	 = form.querySelector( '.box__error span' ),
                restart		 = form.querySelectorAll( '.box__restart' ),
                submit       = form.querySelector( 'button[type="submit"]' ),
                droppedFiles = false,
                showFiles	 = function( files )
                {
                    submit.removeAttribute('disabled');
                    label.textContent = files.length > 1 ? ( input.getAttribute( 'data-multiple-caption' ) || '' ).replace( '{count}', files.length ) : files[ 0 ].name;
                },
                triggerFormSubmit = function()
                {
                    var event = document.createEvent( 'HTMLEvents' );
                    event.initEvent( 'submit', true, false );
                    form.dispatchEvent( event );
                };

            // letting the server side to know we are going to make an Ajax request
            var ajaxFlag = document.createElement( 'input' );
            ajaxFlag.setAttribute( 'type', 'hidden' );
            ajaxFlag.setAttribute( 'name', 'ajax' );
            ajaxFlag.setAttribute( 'value', 1 );
            form.appendChild( ajaxFlag );

            // automatically submit the form on file select
            input.addEventListener( 'change', function( e )
            {
                showFiles( e.target.files );
            });

            // drag&drop files if the feature is available
            if( isAdvancedUpload )
            {
                form.classList.add( 'has-advanced-upload' ); // letting the CSS part to know drag&drop is supported by the browser

                [ 'drag', 'dragstart', 'dragend', 'dragover', 'dragenter', 'dragleave', 'drop' ].forEach( function( event )
                {
                    form.addEventListener( event, function( e )
                    {
                        // preventing the unwanted behaviours
                        e.preventDefault();
                        e.stopPropagation();
                    });
                });
                [ 'dragover', 'dragenter' ].forEach( function( event )
                {
                    form.addEventListener( event, function()
                    {
                        form.classList.add( 'is-dragover' );
                    });
                });
                [ 'dragleave', 'dragend', 'drop' ].forEach( function( event )
                {
                    form.addEventListener( event, function()
                    {
                        form.classList.remove( 'is-dragover' );
                    });
                });
                form.addEventListener( 'drop', function( e )
                {
                    droppedFiles = e.dataTransfer.files; // the files that were dropped
                    showFiles( droppedFiles );

                });
            }


            // if the form was submitted
            form.addEventListener( 'submit', function( e )
            {
                // preventing the duplicate submissions if the current one is in progress
                if( form.classList.contains( 'is-uploading' ) ) return false;

                form.classList.add( 'is-uploading' );
                form.classList.remove( 'is-error' );

                if( isAdvancedUpload ) // ajax file upload for modern browsers
                {
                    e.preventDefault();

                    // gathering the form data
                    var ajaxData = new FormData( form );
                    if( droppedFiles )
                    {
                        Array.prototype.forEach.call( droppedFiles, function( file )
                        {
                            ajaxData.append( input.getAttribute( 'name' ), file );
                        });
                    }

                    // ajax request
                    var ajax = new XMLHttpRequest();
                    ajax.open( form.getAttribute( 'method' ), form.getAttribute( 'action' ), true );

                    ajax.onload = function()
                    {
                        form.classList.remove( 'is-uploading' );
                        if( ajax.status >= 200 && ajax.status < 400 )
                        {
                            var data = JSON.parse( ajax.responseText );
                            form.classList.add( data.success > 0 ? 'is-success' : 'is-error' );

                            if ( data.success ) {
                                form.reset();
                                loadPlans();
                            }   else {
                                errorMsg.textContent = data.error;
                            }
                        }
                        else alert( 'Error. Please, contact Landconnect support!' );
                    };

                    ajax.onerror = function()
                    {
                        form.classList.remove( 'is-uploading' );
                        alert( 'Error. Please, try again!' );
                    };

                    ajax.send( ajaxData );
                }
                else // fallback Ajax solution upload for older browsers
                {
                    var iframeName	= 'uploadiframe' + new Date().getTime(),
                        iframe		= document.createElement( 'iframe' );

                    // $iframe		= $( '<iframe name="' + iframeName + '" style="display: none;"></iframe>' );

                    iframe.setAttribute( 'name', iframeName );
                    iframe.style.display = 'none';

                    document.body.appendChild( iframe );
                    form.setAttribute( 'target', iframeName );

                    iframe.addEventListener( 'load', function()
                    {
                        var data = JSON.parse( iframe.contentDocument.body.innerHTML );
                        form.classList.remove( 'is-uploading' )
                        form.classList.add( data.success == true ? 'is-success' : 'is-error' )
                        form.removeAttribute( 'target' );
                        if( !data.success ) errorMsg.textContent = data.error;
                        iframe.parentNode.removeChild( iframe );
                    });
                }
            });


            // restart the form if has a state of error/success
            Array.prototype.forEach.call( restart, function( entry )
            {
                entry.addEventListener( 'click', function( e )
                {
                    e.preventDefault();
                    form.classList.remove( 'is-error', 'is-success' );
                    input.click();
                });
            });

            // Firefox focus bug fix for file input
            input.addEventListener( 'focus', function(){ input.classList.add( 'has-focus' ); });
            input.addEventListener( 'blur', function(){ input.classList.remove( 'has-focus' ); });
        });
    }( document, window, 0, loadPlans ));

</script>

<?php
    // load chat
    include_once(__DIR__."/../forms/chat.php")
?>

</body>
</html>