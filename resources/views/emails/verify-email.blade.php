<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .header {
            background-color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .header img {
            max-width: 150px;
            height: auto;
        }

        .content {
            padding: 20px;
            text-align: center;
        }

        .content h1 {
            font-size: 24px;
            color: #333333;
            margin-bottom: 20px;
        }

        .content p {
            font-size: 16px;
            color: #555555;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007BFF;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            margin-top: 20px;
        }

        .footer {
            background-color: #f4f4f4;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #777777;
        }

        .footer a {
            color: #007BFF;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <!-- Header Section -->
        <div class="header">
            <img src="https://raw.githubusercontent.com/Nakib00/zan-tech-invoice/refs/heads/main/ZAN%20Tech%20Logo.png"
                alt="ZAN Tech Logo">
        </div>

        <!-- Content Section -->
        <div class="content">
            <h1>Verify Your Email Address</h1>
            <p>Hello {{ $user->name }},</p>
            <p>Thank you for signing up with ZAN Tech. To complete your registration, please verify your email address
                by clicking the button below:</p>
            <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>
            <p>If you did not create an account, no further action is required.</p>
        </div>

        <!-- Footer Section -->
        <div class="footer">
            {{-- <p>If you’re having trouble clicking the "Verify Email Address" button, copy and paste the URL below into
                your web browser:</p> --}}
            {{-- <p><a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a></p> --}}
            <p>&copy; {{ date('Y') }} ZAN Tech. All rights reserved.</p>
            <p>For support, contact us at
                <a href="mailto:zantechbd@gmail.com">zantechbd@gmail.com</a>
                or call <a href="tel:+8801894634149">+880 1894 634 149</a>
            </p>
        </div>
    </div>
</body>

</html>