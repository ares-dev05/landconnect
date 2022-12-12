<?php

include_once 'phpmailer/PHPMailerAutoload.php';

define('CHARSET','UTF-8');

use Aws\Ses\SesClient;
use Aws\Ses\Exception\SesException;

class userCakeMail {

	//UserCake uses a text based system with hooks to replace various strs in txt email templates
	public $contents = NULL;
	
	//Function used for replacing hooks in our templates
	public function newTemplateMsg($template,$additionalHooks)
	{
		global $debug_mode;

		$this->contents = file_get_contents(MAIL_TEMPLATES.$template);
		
		//Check to see we can access the file / it has some contents
		if(!$this->contents || empty($this->contents)) {
			return false;
		} else {
			//Replace default hooks
			$this->contents = replaceDefaultHook($this->contents);
			
			//Replace defined / custom hooks
			$this->contents = str_replace($additionalHooks["searchStrs"],$additionalHooks["subjectStrs"],$this->contents);
			
			return true;
		}
	}

	/**
	 * MD 10APR18: Updated to send emails through Amazon Simple Email Service
	 * @param $recipient
	 * @param $subject
	 * @param null $msg
	 * @return bool
	 */
	public function sendMail($recipient, $subject, $msg = NULL)
	{
		$mailer = new PHPMailer();
		$mailer->getSentMIMEMessage();
		return $this->sendSESEmail(
			$recipient,
			$subject,
			wordwrap($msg ? $msg : $this->contents, 70)
		);
	}

	/**
	 * MD 10APR18: Updated to send emails through Amazon Simple Email Service
	 * @TODO: support attachments
	 * @param $email
	 * @param $subject
	 * @param null $msg
	 * @param null $attachments
	 * @return bool
	 */
	public function sendHtmlEmail($recipient,$subject,$msg=NULL,$attachments=NULL, $attachmentsPathAbsolute=false)
	{
		return $this->sendSESEmail(
			$recipient,
			$subject,
			$msg ? $msg : $this->contents,
			true,
			$attachments,
			$attachmentsPathAbsolute
		);
	}

	/**
	 * Sends an email through Amazon SES
	 * @param $recipient
	 * @param $subject
	 * @param $text
	 * @param null $html
	 * @param null $attachments
	 * @return bool
	 */
	private function sendSESEmail($recipient, $subject, $message, $isHtml=false, $attachments=null, $attachmentsPathAbsolute=false)
	{
		global $websiteName, $emailAddress;

		// construct the email with PHPMailer
		$mail = new PHPMailer;
		$mail->From        = $emailAddress;
		$mail->FromName    = $websiteName;
		$mail->addAddress($recipient);
		$mail->addReplyTo($emailAddress, 'Landconnect Support');
		$mail->isHTML($isHtml);
		$mail->Subject = $subject;
		$mail->Body    = $message;

		if ( $attachments != NULL ) {
			foreach ( $attachments as $file => $name ) {
				if ( $attachmentsPathAbsolute )
					$mail->addAttachment($file, $name);
				else
					$mail->addAttachment(MAIL_TEMPLATES.$file, $name);
			}
		}

		// Attempt to assemble the above components into a MIME message.
		if (!$mail->preSend()) {
			error_log($mail->ErrorInfo);
			return false;
		}	else {
			// Create a new variable that contains the MIME message.
			$messageRawData = $mail->getSentMIMEMessage();
		}

		// Try to send the message.
		try {
			$client = getSESClient();
			$result = $client->sendRawEmail([
				'RawMessage' => [
					'Data' => $messageRawData
				]
			]);
			// If the message was sent, show the message ID.
			// $messageId = $result->get('MessageId');
			return true;
		} catch (SesException $error) {
			error_log("The email was not sent. Error message: ".$error->getAwsErrorMessage()."\n");
			return false;
		}
	}
}
?>