<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Starter Kit Mail Test</title></head>
<body style="font-family: system-ui, sans-serif; padding: 24px; color: #111;">
    <h2>Starter Kit Mail Test</h2>
    <p>This message was sent at <strong>{{ $sentAt }}</strong> to verify your mail configuration.</p>
    @if ($note)
        <p><em>{{ $note }}</em></p>
    @endif
</body>
</html>
