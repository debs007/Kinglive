<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — KingLive</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0F0A1A;
            color: #E0E0E0;
            line-height: 1.7;
        }
        .header {
            background: linear-gradient(135deg, #1A0A2E, #2D1B4E);
            padding: 24px 20px;
            text-align: center;
            border-bottom: 1px solid #3D1B6E;
        }
        .logo { color: #FFD700; font-size: 22px; font-weight: 800; letter-spacing: 2px; }
        .page-title { color: #fff; font-size: 18px; margin-top: 6px; }
        .container { max-width: 720px; margin: 0 auto; padding: 32px 20px 60px; }
        h2 { color: #FFD700; font-size: 16px; margin: 28px 0 10px; }
        p { color: #C0C0C0; font-size: 14px; margin-bottom: 12px; }
        ul { color: #C0C0C0; font-size: 14px; padding-left: 20px; margin-bottom: 12px; }
        li { margin-bottom: 6px; }
        .updated { color: #9B59B6; font-size: 12px; margin-bottom: 24px; }
        a { color: #9B59B6; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">👑 KINGLIVE</div>
        <div class="page-title">{{ $title }}</div>
    </div>
    <div class="container">
        <p class="updated">Last updated: {{ $updated }}</p>
        {!! $content !!}
    </div>
</body>
</html>
