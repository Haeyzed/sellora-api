<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Sign-in couldn't continue</title>
</head>
<body>
    <h1>Sign-in couldn't continue</h1>
    <p>This sign-in link has expired or was already used. Go back to the shop and start signing in again.</p>
    @if ($providerError !== null)
        <p>The sign-in provider said: {{ $providerError }}</p>
    @endif
</body>
</html>
