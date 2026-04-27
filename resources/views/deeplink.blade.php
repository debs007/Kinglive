<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>King Live — Join Stream</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: linear-gradient(135deg, #0A0515 0%, #1A0A2E 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: #fff;
    }
    .card {
      text-align: center;
      padding: 40px 32px;
      max-width: 360px;
      width: 100%;
    }
    .icon { font-size: 64px; margin-bottom: 16px; }
    h1 { font-size: 24px; font-weight: 800; color: #FFD700; margin-bottom: 8px; }
    p { font-size: 14px; color: rgba(255,255,255,.6); margin-bottom: 28px; line-height: 1.6; }
    .btn {
      display: block;
      width: 100%;
      padding: 14px;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 700;
      text-decoration: none;
      margin-bottom: 12px;
      transition: opacity .2s;
    }
    .btn:hover { opacity: .85; }
    .btn-primary { background: linear-gradient(135deg, #6C3483, #9B59B6); color: #fff; }
    .btn-store   { background: rgba(255,255,255,.08); color: rgba(255,255,255,.7);
                   border: 1px solid rgba(255,255,255,.15); }
    .spinner {
      width: 32px; height: 32px;
      border: 3px solid rgba(255,215,0,.2);
      border-top-color: #FFD700;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 20px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">👑</div>
    <h1>King Live</h1>
    <div class="spinner" id="spinner"></div>
    <p id="msg">Opening live stream in the app…</p>

    <a href="{{ $appScheme }}" class="btn btn-primary" id="openApp">
      Open in App
    </a>
    <a href="{{ $playStore }}" class="btn btn-store" id="android" style="display:none">
      📱 Download on Google Play
    </a>
    <a href="{{ $appStore }}" class="btn btn-store" id="ios" style="display:none">
      🍎 Download on App Store
    </a>
  </div>

  <script>
    const scheme   = "{{ $appScheme }}";
    const android  = /android/i.test(navigator.userAgent);
    const ios      = /iphone|ipad|ipod/i.test(navigator.userAgent);

    // Show correct store button
    if (android) document.getElementById('android').style.display = 'block';
    if (ios)     document.getElementById('ios').style.display     = 'block';

    // Try to open app immediately
    window.location.href = scheme;

    // After 2.5s if still here, show fallback
    setTimeout(() => {
      document.getElementById('spinner').style.display = 'none';
      document.getElementById('msg').textContent =
        "App not installed? Download King Live to join the stream.";
    }, 2500);
  </script>
</body>
</html>
