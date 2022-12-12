<?php

require_once(dirname(__FILE__)."/../../models/class.mail.php");

define('EMAIL_ENABLED', $portal_email_enabled);


/**
 * Class PtEmailer
 * construct emails and send them to watchers of tickets
 */
class PtEmailer {

    /**
     * @param PtTicket $ticket
     * @param PtUserInfo $userInfo
     * @return bool
     */
    public static function ticketCreated( $ticket, $userInfo ) {
        // send emails for ticket creation
        return self::sendEmails(
            // email template
            "/portal/new-ticket.html",
            // title for normal users
            "Landconnect: ".$ticket->props->getFloorName()." Floorplan Update",
            // title for admins
            PtCompanyInfo::get($ticket->cid)->name.": ".$ticket->props->getFloorName()." - New Plan",
            // hooks in the email content
            self::ticketNodeHooks( $ticket, $ticket->nodes, $userInfo ),
            // the recipients of this email
            self::getRecipients( $ticket, $userInfo )
        );
    }

    /**
     * nodesAdded
     * send email updates when nodes are being added (message &/OR file)
     * @param PtTicket $ticket
     * @param PtNode $messageNode
     * @param PtNode $fileNode
     * @param PtUserInfo $userInfo
     * @return bool
     */
    public static function nodesAdded( $ticket, $messageNode, $fileNodes, $userInfo ) {
        // send emails for ticket creation
        return self::sendEmails(
            // email template
            "/portal/new-node.html",
            // title for normal users
            "Landconnect: ".$ticket->props->getFloorName()." Floorplan Update",
            // title for admins
            PtCompanyInfo::get($ticket->cid)->name.": ".$ticket->props->getFloorName()." - New Message",
            // hooks in the email content
            self::ticketNodeHooks( $ticket, array_merge([$messageNode], $fileNodes), $userInfo ),
            // the recipients of this email
            self::getRecipients( $ticket, $userInfo )
        );
    }

    /**
     * @param PtTicket $ticket
     * @param PtUserInfo $userInfo
     * @return array
     */
    private static function getRecipients( $ticket, $userInfo ) {
        $emails = array();

        if ( WATCHER_SYSTEM_ENABLED ) {
            foreach ($ticket->watchers as $watcher) {
                if ($watcher->uid != $userInfo->id)
                    $emails[$watcher->uid] = $watcher->userInfo->email;
            }
        }   else {
            $users = PtUserInfo::getPortalUsersInfos( $ticket->cid );
            /**
             * @var PtUserInfo $portalUserInfo
             */
            foreach ($users as $portalUserInfo) {
                if ($portalUserInfo->id != $userInfo->id)
                    $emails[$portalUserInfo->id] = $portalUserInfo->email;
            }
        }

        return $emails;
    }

    /**
     * @param PtTicket $ticket
     * @param Array $nodes
     * @param PtUserInfo $userInfo
     * @return Array
     */
    private static function ticketNodeHooks( $ticket, $nodes, $userInfo )
    {
        // see if we have a message and a file
        $message    = "N/A";
        $fileInfo   = "";

        // find the message & file info
        foreach ($nodes as $node) {
            if ( $node->type == PtNode::MESSAGE ) {
                $message = $node->data;
            }   else
            if ( $node->type == PtNode::FILE ) {
                $fileInfo .= "<b>Attached File:</b> ".$node->data."<br/>";
            }
        }

        // return hooks
        return array(
            "searchStrs" => array(
                "#USERNAME#",
                "#COMPANY#",
                "#STATE#",
                "#FLOORNAME#",
                "#RANGE#",
                "#MESSAGE#",
                "#FILEINFO#",
                "#TICKET_LINK#"
            ),
            "subjectStrs" => array(
                $userInfo->display_name,
                PtCompanyInfo::get($userInfo->company_id)->name,
                PtStateInfo::get($userInfo->state_id)->name,
                $ticket->props->getFloorName(),
                $ticket->props->getTargetRange(),
                $message,
                $fileInfo,
                $ticket->getAbsolutePath()
            )
        );
    }

    /**
     * @param String $template
     * @param String $title
     * @param Array $hooks
     * @param Array $recipients
     * @return bool
     */
    private static function sendEmails( $template, $userTitle, $adminTitle, $hooks, $recipients )
    {
        $mailSender = new userCakeMail();

        // If there is a mail failure, fatal error
        if(!$mailSender->newTemplateMsg($template, $hooks)) {
            addAlert("danger", lang("MAIL_ERROR"));
            return false;
        } else {
            foreach ($recipients as $uid => $recipient) {
                if ( PtTicket::isGlobalAdmin($uid) )
                    $title = $adminTitle;
                else
                    $title = $userTitle;

                // send the email
                if ( EMAIL_ENABLED &&
                    !$mailSender->sendHtmlEmail( $recipient, $title ) ) {
                    addAlert("danger", lang("MAIL_ERROR"));
                    return false;
                }
            }

            if (!EMAIL_ENABLED) {
                $mailSender->sendHtmlEmail( "mihai.dersidan@landconnect.com.au", $adminTitle );
            }
        }

        return true;
    }

    function __construct() { }

    function __destruct () { }
}

?>