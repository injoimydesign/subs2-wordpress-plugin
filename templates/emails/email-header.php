<?php
/**
 * Email Header Template
 *
 * This template is used as the header for all email notifications.
 * Can be overridden by copying to yourtheme/subs/emails/email-header.php
 *
 * @package Subs
 * @subpackage Templates/Emails
 * @version 1.0.0
 *
 * @var string $email_heading The email heading text
 * @var string $site_name The site name
 * @var string $site_url The site URL
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Set defaults if not provided
$email_heading = isset($email_heading) ? $email_heading : get_bloginfo('name');
$site_name = isset($site_name) ? $site_name : get_bloginfo('name');
$site_url = isset($site_url) ? $site_url : home_url();

// Apply filters for customization
$email_heading = apply_filters('subs_email_header_heading', $email_heading);
$header_bg_color = apply_filters('subs_email_header_bg_color', '#f8f9fa');
$header_text_color = apply_filters('subs_email_header_text_color', '#333333');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo esc_html($email_heading); ?></title>
    <style type="text/css">
        /* Reset styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }

        /* Container styles */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .email-wrapper {
            padding: 20px;
        }

        /* Header styles */
        .email-header {
            background-color: <?php echo esc_attr($header_bg_color); ?>;
            padding: 30px 20px;
            text-align: center;
            border-bottom: 3px solid #007cba;
        }

        .email-header h1 {
            margin: 0;
            padding: 0;
            color: <?php echo esc_attr($header_text_color); ?>;
            font-size: 24px;
            font-weight: bold;
        }

        .email-header .logo {
            margin-bottom: 15px;
        }

        .email-header .logo img {
            max-width: 200px;
            height: auto;
        }

        /* Content styles */
        .email-content {
            padding: 30px 20px;
        }

        .email-content p {
            margin: 0 0 15px 0;
            line-height: 1.6;
        }

        .email-content h2,
        .email-content h3 {
            color: #333333;
            margin: 20px 0 10px 0;
        }

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }

            .email-wrapper {
                padding: 10px !important;
            }

            .email-header,
            .email-content {
                padding: 20px 15px !important;
            }

            .email-header h1 {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff;">
                    <tr>
                        <td class="email-header" style="background-color: <?php echo esc_attr($header_bg_color); ?>; padding: 30px 20px; text-align: center; border-bottom: 3px solid #007cba;">
                            <?php
                            // Display logo if available
                            $logo_url = apply_filters('subs_email_header_logo', '');
                            if (!empty($logo_url)) :
                            ?>
                                <div class="logo" style="margin-bottom: 15px;">
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width: 200px; height: auto;">
                                </div>
                            <?php endif; ?>

                            <h1 style="margin: 0; padding: 0; color: <?php echo esc_attr($header_text_color); ?>; font-size: 24px; font-weight: bold;">
                                <?php echo esc_html($email_heading); ?>
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding: 30px 20px;">
                            <!-- Email content will be inserted here -->
