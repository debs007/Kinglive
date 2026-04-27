<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Coin Seller Portal — King Live</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: linear-gradient(135deg, #0A0515 0%, #1A0A2E 100%); min-height: 100vh;
           display: flex; align-items: center; justify-content: center;
           font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #fff; }
    .card { background: #1A0A2E; border: 1px solid rgba(255,255,255,.08); border-radius: 16px;
            padding: 36px; width: 100%; max-width: 400px; }
    .icon { font-size: 48px; text-align: center; }
    h4 { text-align: center; margin: 10px 0 4px; font-size: 20px; }
    .sub { text-align: center; color: #6a5f80; font-size: 13px; margin-bottom: 28px; }
    label { display: block; font-size: 12px; color: #a89bc0; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
    input { width: 100%; background: #2D1B4E; border: 1px solid rgba(255,255,255,.1); border-radius: 8px;
            padding: 11px 14px; color: #fff; font-size: 14px; outline: none; margin-bottom: 16px; }
    input:focus { border-color: #FFD700; }
    .error { background: rgba(231,76,60,.15); border: 1px solid rgba(231,76,60,.3); color: #e74c3c;
             padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
    button { width: 100%; padding: 12px; background: linear-gradient(135deg, #FFD700, #B8860B);
             border: none; border-radius: 8px; color: #000; font-weight: 700; font-size: 14px; cursor: pointer; }
    button:hover { opacity: .9; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🪙</div>
    <h4>Coin Seller Portal</h4>
    <div class="sub">King Live</div>

    @if($errors->any())
      <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('coin_seller.login.post') }}">
      @csrf
      <label>Email</label>
      <input type="email" name="email" value="{{ old('email') }}" required autofocus>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit">Sign In</button>
    </form>
  </div>
</body>
</html>
