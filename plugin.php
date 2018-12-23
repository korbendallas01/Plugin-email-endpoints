<?php

class DDI_Email_Endpoints extends KokenPlugin implements KokenEmail {

	function __construct()
	{
		$this->require_setup = true;
	}

	function set_data($data)
	{
		parent::set_data($data);

		$this->register_email_handler($this->get_display_name());
	}

	private function get_display_name()
	{
		if ($this->data->provider === 'smtp')
		{
			return 'SMTP';
		}

		return ucwords($this->data->provider);
	}

	function send($fromEmail, $fromName, $toEmail, $subject, $message)
	{
		$this->{$this->data->provider}($fromEmail, $fromName, $toEmail, $subject, $message);
	}

	private function smtp($fromEmail, $fromName, $toEmail, $subject, $message)
	{
		require_once 'providers/smtp/swift_required.php';

		$port = (int) $this->data->smtp_port;
		$security = null;

		if ($port !== 25)
		{
			$xportlist = stream_get_transports();

			if ($port === 465 && in_array('ssl', $xportlist))
			{
				$security = 'ssl';
			}
			else if ($port === 587 && in_array('tls', $xportlist))
			{
				$security = 'tls';
			}
		}

		$transport = Swift_SmtpTransport::newInstance($this->data->smtp_hostname, $port, $security);

		if (!empty($this->data->smtp_username))
		{
			$transport->setUsername($this->data->smtp_username);
		}

		if (!empty($this->data->smtp_password))
		{
			$transport->setPassword($this->data->smtp_password);
		}

		$mailer = Swift_Mailer::newInstance($transport);

		$message = Swift_Message::newInstance($subject)
		->setFrom(array($toEmail))
		->setTo(array($toEmail))
		->setBody($message);

		if (!empty($fromEmail))
		{
			$message->setReplyTo(array($fromEmail => $fromName));
		}

		$result = $mailer->send($message);
	}

	private function mandrill($fromEmail, $fromName, $toEmail, $subject, $message)
	{
		require_once 'providers/mandrill/Mandrill.php';

		$mandrill = new Mandrill($this->data->mandrill_api_key);
		$replyTo =  $fromName . ' <' . $fromEmail . '>';
		$fromEmail = $this->data->mandrill_from_email ?: $fromEmail;

		$message = array(
			'html' => nl2br($message),
			'text' => $message,
			'subject' => $subject,
			'to' => array(
				array(
					'email' => $toEmail
				)
			),
			'from_email' => $fromEmail,
			'from_name' => $fromName,
			'headers' => array('Reply-To' => $replyTo)
		);

		return $mandrill->messages->send($message);
	}
}
