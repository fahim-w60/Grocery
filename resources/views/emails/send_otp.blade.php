<!DOCTYPE html>
<html>
<head>
    <title>Your Grocery Marketplace OTP Code</title>
</head>
<body style="background-color: #262329; padding: 13px; font-family: Arial, sans-serif;">
    <p style="font-family: Arial, sans-serif; font-size: 13px; color: #ffffff text-align: center;">Hi {{ $user->name }},</p>
    <p style="font-family: Arial, sans-serif; font-size: 25px; color: #F4F1F1; text-align: center;">
        Your Grocery Marketplace OTP is 
    </p>
    <p style="font-family: Arial, sans-serif; font-size: 25px; color: #F4F1F1; text-align: center;">
        <strong>{{ $otp }}</strong>
    </p>

    <p style="font-family: Arial, sans-serif; font-size: 13px; color: #F4F1F1;">
        This code will expire in 5 minutes.
    </p>

    <p style="font-family: Arial, sans-serif; font-size: 13px; color: #F4F1F1;">
        Please use this code to verify your account. If you did not request this code, please ignore this email.
    </p>
    
    <p style="font-family: Arial, sans-serif; font-size: 13px; color: #F4F1F1;">
        Thank you!
    </p>
</body>
</html>