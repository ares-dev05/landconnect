<?php

    define('DATE_FULL', "D, d/M/y g:i A");

    define('DATE_NORMAL', "d/M/y g:i A");



    /**

     * @param DateTime $date

     */

    function formatDate( $date, $format=NULL ) {

        if ( $format==NULL ) $format = DATE_FULL;

        return $date->format( $format );

    }



    /**

     * @param DateTime $date

     */

    function formatDaysLeft( $date, $asLabel=true ) {

        $nowDate  = new DateTime();

        $nowDate -> setTimezone( $date->getTimezone() );

        $dateDif  = $nowDate->diff( $date );

        $daysLeft = (int)$dateDif->format("%r%a");



        if ( $daysLeft < -1 ) {

            return formatAsLabel( abs($daysLeft)." days ago",  "danger",  $asLabel );

        }   else

        if ( $daysLeft == -1 ) {

            return formatAsLabel( "one day ago", "danger", $asLabel );

        }   else

        if ( $daysLeft == 0 ) {

            return formatAsLabel( "today", "warning", $asLabel );

        }   else

        if ( $daysLeft == 1 ) {

            return formatAsLabel( "tomorrow", "warning", $asLabel );

        }   else

            return formatAsLabel( "in $daysLeft days", "success", $asLabel );

    }



    /**

     * @param $content

     * @param $labelType

     * @param bool $asLabel included for compatibility with formatDaysLeft

     * @return mixed

     */

    function formatAsLabel( $content, $labelType, $asLabel=true ) {

        if ( $asLabel ) {

            return "

                <span class='label label-{$labelType}'>

                    {$content}

                </span>

            ";

        }



        return $content;

    }



    function isToday( $date ) {

        $nowDate  = new DateTime();

        $nowDate -> setTimezone( $date->getTimezone() );

        $dateDif  = $nowDate->diff( $date );

        return ( (int)$dateDif->format("%r%a") == 0 );

    }

    function isInThePast( $date ) {

        $nowDate  = new DateTime();

        $nowDate -> setTimezone( $date->getTimezone() );

        $dateDif  = $nowDate->diff( $date );

        return ( (int)$dateDif->format("%r%a") < 0 );

    }



    /**

     * @param int $fileSize

     * @return string

     */

    function formatFileSize( $fileSize ) {

        if ( $fileSize <= 0 || is_nan($fileSize) )

            return "N/A KB";



        if ( $fileSize < 1024 ) {

            return $fileSize."B";

        }   else {

            $fileSize = floor($fileSize/1024);

            if ( $fileSize<1024 ) {

                return $fileSize."KB";

            }   else {

                $fileSize = floor($fileSize/1024);

                if ( $fileSize<1024 ) {

                    return $fileSize."MB";

                }   else {

                    return $fileSize."GB";

                }

            }

        }

    }



    function getStatusLabel( $status ) {

        switch ( $status ) {

            case PtTicket::OPEN:

                $statusLabel = "label-warning";

                $statusText = "working";

                break;

            case PtTicket::PROBLEM:

                $statusLabel = "label-danger";

                $statusText = "attention";

                break;

            case PtTicket::CLOSED:

                // $statusLabel = "label-default";

                $statusLabel = "label-success";

                $statusText = "active";

                break;

        }

        

        return "

            <span class='label $statusLabel'>

                $statusText

            </span>

        ";

    }



    function getUserLabel( $uid, $userName ) {

        return "

            <!-- <span class='glyphicon glyphicon-user'></span> -->

            <a target='_blank' href='/account/user_details.php?id={$uid}'>$userName</a>

        ";

    }



    /**

     * @param PtNode $node

     * @return string

     */

    function getFileIcon( $node ) {

        // calculate the filesize in KB

        $absolutePath = $node->getServerStoragePath();

        $response = "";



        if ( file_exists($absolutePath) ) {

            $fileSize  = @filesize($absolutePath);

            $extension = strtolower( @pathinfo($absolutePath, PATHINFO_EXTENSION) );

        }   else {

            $fileSize  = 0;

            $extension = "";

        }



        $fileSize   = formatFileSize( $fileSize );

        $fileName   = $node->data;

        // $filePath   = $node->getRelativeFilePath();

        $filePath   = "view_resource.php?id=".$node->id;

        $fileDate   = formatDate( $node->createdAt, DATE_NORMAL );



        // <img src="image.svg" onerror="this.onerror=null; this.src='image.png'"



        // default file thumbnails

        $fileThumbnail = "<span class='glyphicon glyphicon-file' style='font-size:3em;' aria-hidden='true'></span>";



        if ( $extension == "svg" ) {

            $fileThumbnail = "<img src='$filePath' onerror='this.onerror=null; this.src=\'images/icons/file_svg.png\'' />";

            // $fileThumbnail = "<img src='images/icons/file_svg.png'/>";

        }



        $response .= "

        <div class='thumbnail'>

            <div style='text-align: center; margin-top:10px;'>

                <a href='$filePath' target='_blank'>

                    $fileThumbnail

                </a>

            </div>



            <div class='caption'>

                <div class='row'>

                    <div class='col-sm-12'>

                        <a href='$filePath' target='_blank'>$fileName</a>

                    </div>

                </div>



                <div class='row' style='font-size: 11px;'>

                    <div class='col-sm-8'>

                        $fileDate

                    </div>

                    <div class='col-sm-4' style='text-align: right;'>

                        $fileSize

                    </div>

                </div>

            </div>

        </div>

        ";



        return $response;

    }



    /**

     * @param PtUserInfo $userInfo

     * @param string $defaultOption

     */

    function getRangesSelectize(

        $userInfo,

        $selectId="select-range",

        $defaultRange="",

        $placeholder="Select existing range or enter a new one...")

    {

        $ranges = fetchCompanyRanges( $userInfo->company_id, $userInfo->state_id );

        $selectOptions = "<option value=''>{$placeholder}</option>";



        foreach ($ranges as $rangeId => $rangeName) {

            $selected =

                ( strcasecmp($defaultRange, $rangeName)==0 ) ?

                "selected=\"selected\"" : "";



            $selectOptions .= "

                <option $selected value='{$rangeName}'>{$rangeName}</option>";

        }



        return "

        <!-- <div class='control-group'> -->

            <select name='{$selectId}' id='{$selectId}' class='demo-default' placeholder='{$placeholder}'>

                {$selectOptions}

            </select>

        <!-- </div> -->

        <script>

            $('#{$selectId}').selectize({

                create: true,

                sortField: {

                    field: 'text',

                    direction: 'asc'

                }

            });

        </script>";

    }



    function prepareUserMessage( $rawMessage ) {

        $message = htmlspecialchars( $rawMessage, ENT_QUOTES, 'UTF-8' );

        $message = nl2br( $message );



        return $message;

    }



    function missingProperty( $label='not set' ) {

        return "<span class='label label-danger'>{$label}</span>";

    }





    /**

     * getSettingsBar

     *  output the settings bar for the plan portal; admins only ?

     */

    function getSettingsBar( $isAdmin, $crtState, $companyId )

    {

        if ( $isAdmin ) {

            $stateIds = fetchStates();

        }   else {

            $stateIds = fetchCompanyStates( $companyId );

        }



        // build the state selection as a list of buttons

        $statesSelect = getStateSelect( array_keys($stateIds), $crtState );



        // build a settings panel

        return "

    <div class='row'>

        <div class='col-sm-12'>

             <div class='panel panel-default'>

                <div class='panel-heading'>

                    <span class='pull-left'>

                        Settings

                    </span>

                    <div class='clearfix'></div>

                </div>

                <div class='panel-body'>

                    <div class='row'>

                        <div class='col-sm-2' style='height:29px; padding-top:6px;'>
                           State
                        </div>

                        <div class='col-sm-10'>

                            $statesSelect

                        </div>

                    </div>

                </div>

             </div>

        </div>

    </div>

";

    }



    function getStateSelect( $stateIds, $crtState )

    {

        $list = PtStateInfo::fromIds( $stateIds );



        if ( $crtState < 0 ) {

            $class = "class='active'";

            $check = "fa-check-square";

        }   else {

            $class = "";

            $check = "fa-square-o";

        }



        // start creating the state select

        $stateSelect = "

            <ul class='nav nav-pills'>";



        $stateSelect .= "

                <li role='presentation' {$class}>

                    <a href='?'>

                        <i class='fa {$check}'></i>

                        All

                    </a>

                </li>

            ";



        foreach ( $list as $stateInfo ) {

            if ( $crtState == $stateInfo->id ) {

                $class = "class='active'";

                $check = "fa-check-square";

            }   else {

                $class = "";

                $check = "fa-square-o";

            }



            $abbrev = $stateInfo->abbrev;

            $name   = $stateInfo->name;



            $stateSelect .= "

                <li role='presentation' {$class}>

                    <a href='?state={$abbrev}'>

                        <i class='fa {$check}'></i>

                        {$name}

                    </a>

                </li>

            ";

        }



        $stateSelect .= "

            </ul>";



        return $stateSelect;

    }





    /**

     *

     */

    function getPlanPortalInvoices( ) {



    }

?>