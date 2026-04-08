<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>King Live — Admin Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0a1a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.card{background:#1a1035;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:44px 40px;width:100%;max-width:400px}
.logo{text-align:center;margin-bottom:32px}
.logo .crown{font-size:40px;display:block;margin-bottom:8px}
.logo h1{font-size:24px;font-weight:700;color:#FFD700;letter-spacing:3px}
.logo p{font-size:12px;color:#9B59B6;letter-spacing:2px;margin-top:4px}
label{display:block;font-size:12px;color:#a89bc0;margin-bottom:6px}
input{width:100%;background:#0f0a1a;border:1px solid rgba(255,255,255,.1);color:#fff;padding:12px 14px;border-radius:8px;font-size:14px;margin-bottom:18px;transition:border-color .2s}
input:focus{outline:none;border-color:#FFD700}
button{width:100%;background:#FFD700;color:#000;border:none;padding:14px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:background .2s}
button:hover{background:#B8860B}
.error{color:#e74c3c;font-size:13px;margin-bottom:16px;padding:10px 14px;background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);border-radius:6px}
.footer{text-align:center;margin-top:20px;font-size:12px;color:#6a5f80}
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <span class="crown">👑</span>
        <h1>KING LIVE</h1>
        <p>ADMIN PANEL</p>
    </div>

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.login.post') }}">
        @csrf
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required autofocus
               value="{{ old('email') }}" placeholder="admin@kinglive.app">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required
               placeholder="••••••••">

        <button type="submit">Login to Admin Panel</button>
    </form>
    <div class="footer">King Live v1.0 · Admin Access Only</div>
</div>
</body>
</html>
