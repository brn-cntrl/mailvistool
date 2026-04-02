<?php
require 'vendor/autoload.php';
$config = require 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
$mail->SMTPDebug = 3;
$mail->Debugoutput = 'echo';

try {
    $mail->isSMTP();
    $mail->Host       = $config['smtp']['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp']['username'];
    $mail->Password   = $config['smtp']['password'];
    $mail->SMTPSecure = strtolower($config['smtp']['encryption']) === 'ssl' ? 'ssl' : 'tls';
    $mail->Port       = $config['smtp']['port'];

    $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);
    $mail->addAddress($config['smtp']['from_email']); // Send to self as test

    $mail->Subject = 'SMTP Test from MailOps';
    $mail->Body    = 'This is a test message to verify SMTP connectivity.';

    echo "Attempting to send...\n";
    $mail->send();
    echo "Message has been sent\n";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}
