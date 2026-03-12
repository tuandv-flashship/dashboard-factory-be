<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reset your password</title>
    </head>
    <body>
        <p>You requested a password reset for your member account.</p>
        @if (!empty($reset_link))
            <p><a href="{{ $reset_link }}">Reset your password</a></p>
        @endif
        @if (!empty($token))
            <p>Reset token: {{ $token }}</p>
        @endif
        <p>If you did not request a reset, you can ignore this email.</p>
    </body>
</html>
