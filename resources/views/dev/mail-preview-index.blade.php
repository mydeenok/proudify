<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Mail preview (local only)</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f3f4f6; margin: 0; padding: 32px; }
        h1 { font-size: 20px; color: #1a1a1a; }
        ul { list-style: none; padding: 0; max-width: 480px; }
        li { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; margin-bottom: 8px; }
        a { display: block; padding: 14px 18px; color: #b40012; text-decoration: none; font-size: 14px; font-weight: bold; }
        a:hover { background: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Mail preview — local only</h1>
    <ul>
        @foreach ($emails as $key => $email)
            <li><a href="{{ route('dev.mail-preview.show', $key) }}" target="_blank">{{ $email['label'] }}</a></li>
        @endforeach
    </ul>
</body>
</html>
