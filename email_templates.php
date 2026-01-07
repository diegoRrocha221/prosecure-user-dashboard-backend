<?php 
function new_user_email_template($title, $name, $subtitle, $content){
  $email_template = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <title>ProSecureLSP</title>
  <style>
    .a_body {
      background-color: #25364D;
      color: #fff;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 2rem;
      border: 1px solid #fff;
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .logo {
      text-align: center;
      margin-bottom: 1rem;
    }
    .logo img {
      max-width: 200px;
    }
    .title {
      text-align: center;
      margin-bottom: 1rem;
      font-size: 2rem;
      font-weight: bold;
      color: #fff;
    }
    .intro {
      text-align: center;
      margin-bottom: 2rem;
      font-size: 1.2rem;
      color: #fff;
    }
    .section {
      margin-bottom: 2rem;
    }
    .section h2 {
      margin-top: 0;
      margin-bottom: 1rem;
      font-size: 1.5rem;
      font-weight: bold;
      color: #fff;
    }
    .section p {
      margin-bottom: 1rem;
      font-size: 1.1rem;
      color: #fff;
    }
    .section ul {
      margin-bottom: 0;
      padding-left: 0;
    }
    .section li {
      margin-bottom: 0.5rem;
      font-size: 1.1rem;
      color: #fff;
    }
    .section strong {
      font-weight: bold;
    }
  </style>
</head>
<body>
<div class="a_body">
  <div class="container">
    <div class="logo">
      <img src="https://www.prosecurelsp.com/images/logo.png" alt="LSP logo">
    </div>
    <div class="title">'.$title.'</div>
    <div class="intro">Hi '.$name.'!</div>
    <div class="section">
      <h2>'.$subtitle.'</h2>
       '.$content.'
    </div>
  </div>
</div>
</body>
</html>
  ';
  return $email_template;
}

function password_changed_notification_template($email, $username, $ip, $location, $device, $browser, $timestamp) {
    $title = "Password Changed Successfully";
    $subtitle = "Security Alert";
    
    $locationInfo = "Unknown Location";
    if (!empty($location['city']) || !empty($location['country'])) {
        $parts = array_filter([
            $location['city'] ?? '',
            $location['region'] ?? '',
            $location['country'] ?? ''
        ]);
        $locationInfo = implode(', ', $parts);
    }
    
    $content = '
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px;">
            <p style="color: #856404; margin: 0; font-weight: bold;">
                <span style="font-size: 1.2rem;">&#9888;</span> Password was recently changed
            </p>
        </div>
        
        <p style="color: #fff; font-size: 1rem; line-height: 1.6;">
            This email confirms that the password for account <strong>' . htmlspecialchars($username) . '</strong> was successfully changed on <strong>' . htmlspecialchars($timestamp) . '</strong>.
        </p>
        
        <div style="background: rgba(255, 255, 255, 0.1); padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;">
            <h3 style="color: #fff; margin-top: 0; margin-bottom: 1rem; font-size: 1.2rem;">
                &#128205; Activity Details:
            </h3>
            <table style="width: 100%; color: #fff; font-size: 0.95rem;">
                <tr>
                    <td style="padding: 0.5rem 0; width: 30%;"><strong>Username:</strong></td>
                    <td style="padding: 0.5rem 0;">' . htmlspecialchars($username) . '</td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0;"><strong>Date &amp; Time:</strong></td>
                    <td style="padding: 0.5rem 0;">' . htmlspecialchars($timestamp) . '</td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0;"><strong>IP Address:</strong></td>
                    <td style="padding: 0.5rem 0;">' . htmlspecialchars($ip) . '</td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0;"><strong>Location:</strong></td>
                    <td style="padding: 0.5rem 0;">' . htmlspecialchars($locationInfo) . '</td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0;"><strong>Device:</strong></td>
                    <td style="padding: 0.5rem 0;">' . htmlspecialchars($device) . '</td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0;"><strong>Browser:</strong></td>
                    <td style="padding: 0.5rem 0;">' . htmlspecialchars($browser) . '</td>
                </tr>
            </table>
        </div>
        
        <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 1rem; margin: 1.5rem 0; border-radius: 4px;">
            <p style="color: #155724; margin: 0;">
                <strong>&#10004; Was this you?</strong><br>
                <span style="font-size: 0.95rem;">If you made this change, you can safely ignore this email. No further action is needed.</span>
            </p>
        </div>
        
        <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 1rem; margin: 1.5rem 0; border-radius: 4px;">
            <p style="color: #721c24; margin: 0;">
                <strong>&#9888; Didn\'t make this change?</strong><br>
                <span style="font-size: 0.95rem;">
                    If you did not authorize this password change, your account may be compromised. 
                    Please contact our support team immediately at <a href="mailto:support@prosecure.com" style="color: #721c24; font-weight: bold;">support@prosecure.com</a>
                </span>
            </p>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.2);">
            <p style="color: rgba(255,255,255,0.7); font-size: 0.85rem; line-height: 1.5; margin: 0;">
                <strong>Security Tips:</strong><br>
                &bull; Never share your password with anyone<br>
                &bull; Use a unique password for your ProSecureLSP account<br>
                &bull; Enable two-factor authentication for additional security<br>
                &bull; Regularly review your account activity
            </p>
        </div>
        
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2); text-align: center;">
            <p style="color: rgba(255,255,255,0.6); font-size: 0.8rem; margin: 0;">
                This is an automated security notification. Please do not reply to this email.
            </p>
        </div>
    ';
    
    return new_user_email_template($title, 'Valued User', $subtitle, $content);
}
?>