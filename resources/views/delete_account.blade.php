<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account — KingLive</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0F0A1A;
            color: #E0E0E0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background: linear-gradient(135deg, #1A0A2E, #2D1B4E);
            padding: 24px 20px;
            text-align: center;
            border-bottom: 1px solid #3D1B6E;
        }
        .logo { color: #FFD700; font-size: 22px; font-weight: 800; letter-spacing: 2px; }
        .page-title { color: #fff; font-size: 16px; margin-top: 6px; opacity: .8; }
        .container { max-width: 480px; margin: 0 auto; padding: 32px 20px; width: 100%; }

        .warning-box {
            background: rgba(231, 76, 60, 0.12);
            border: 1px solid rgba(231, 76, 60, 0.4);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .warning-box h3 { color: #E74C3C; font-size: 14px; margin-bottom: 8px; }
        .warning-box ul { color: #C0C0C0; font-size: 13px; padding-left: 16px; }
        .warning-box li { margin-bottom: 4px; }

        .form-group { margin-bottom: 18px; }
        label { display: block; color: #9B9B9B; font-size: 12px; margin-bottom: 6px; letter-spacing: .3px; }
        input, textarea, select {
            width: 100%;
            background: #1A0A2E;
            border: 1px solid #3D1B6E;
            color: #fff;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color .2s;
        }
        input:focus, textarea:focus { border-color: #9B59B6; }
        textarea { resize: vertical; min-height: 80px; }

        .btn-delete {
            width: 100%;
            background: #E74C3C;
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn-delete:active { opacity: .85; }

        .error { color: #E74C3C; font-size: 12px; margin-top: 4px; }

        .links { text-align: center; margin-top: 24px; }
        .links a { color: #9B59B6; font-size: 13px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">👑 KINGLIVE</div>
        <div class="page-title">Delete Account</div>
    </div>

    <div class="container">

        <div class="warning-box">
            <h3>⚠️ This action cannot be undone</h3>
            <ul>
                <li>Your profile, streams, and messages will be deleted</li>
                <li>Your coin and diamond balance will be forfeited</li>
                <li>Your username will be released</li>
                <li>Pending withdrawals may be cancelled</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('delete.account.submit') }}">
            @csrf

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       placeholder="your@email.com" required>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                       placeholder="Enter your password" required>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Reason (optional)</label>
                <textarea name="reason"
                    placeholder="Tell us why you're leaving...">{{ old('reason') }}</textarea>
            </div>

            <button type="submit" class="btn-delete">
                Delete My Account
            </button>
        </form>

        <div class="links">
            <a href="{{ route('privacy') }}">Privacy Policy</a>
            &nbsp;·&nbsp;
            <a href="{{ route('terms') }}">Terms & Conditions</a>
        </div>
    </div>
</body>
</html>
