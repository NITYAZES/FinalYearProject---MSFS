# FinalYearProject - MSFS


Email Configuration (Ethereal – mailer.php)

This project uses Ethereal Email for testing email notifications.
To enable email functionality, the SMTP credentials must be configured in mailer.php.

Required Setup

Visit https://ethereal.email

Create a new Ethereal email account

Copy the generated username and password

Open the following file in the project:

/config/mailer.php

Configure mailer.php

Update the SMTP credentials in mailer.php with your own Ethereal account details:

$mail->Host = 'smtp.ethereal.email';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->SMTPAuth = true;

$mail->Username = 'your_ethereal_username';
$mail->Password = 'your_ethereal_password';
