<?php


use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../vendor/autoload.php';

$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->SMTPAuth = true;
$mail->Host = 'smtp.ethereal.email';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;

$mail->Username = 'davin.lang12@ethereal.email';
$mail->Password = 'RSZa77NRnbx3yMFCzX';

$mail->isHTML(true);
$mail->CharSet = 'UTF-8';
$mail->Timeout = 30;


$mail->SMTPKeepAlive = false;

$mail->SMTPOptions = [
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
  ]
];

return $mail;