<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Confirm your email</title>
    </head>
    <body>
        <p>Hello {{ $member_name ?? 'there' }},</p>
        <p>Please confirm your email address by clicking the link below:</p>
        <p><a href="{{ $verify_link }}">{{ $verify_link }}</a></p>
        <p>If you did not create this account, you can ignore this email.</p>
    </body>
</html>
