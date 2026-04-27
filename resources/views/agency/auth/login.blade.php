<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agency Portal — King Live</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #0A0515 0%, #1A0A2E 100%); min-height: 100vh; }
    .card { background: #1A0A2E; border: 1px solid #2D1B4E; border-radius: 16px; }
    .form-control { background: #2D1B4E; border: 1px solid #4A2F6E; color: #fff; }
    .form-control:focus { background: #3D2B5E; border-color: #6C3483; color: #fff; box-shadow: 0 0 0 0.2rem rgba(108,52,131,.25); }
    .btn-primary { background: linear-gradient(135deg, #6C3483, #9B59B6); border: none; }
    .btn-primary:hover { background: linear-gradient(135deg, #9B59B6, #6C3483); }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center">
  <div class="card p-4" style="width:100%;max-width:400px">
    <div class="text-center mb-4">
      <div style="font-size:48px">🏢</div>
      <h4 class="text-white mt-2 mb-0">Agency Portal</h4>
      <small class="text-muted">King Live</small>
    </div>

    @if($errors->any())
      <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('agency.login.post') }}">
      @csrf
      <div class="mb-3">
        <label class="form-label text-white-50 small">Email</label>
        <input type="email" name="email" class="form-control"
               value="{{ old('email') }}" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label text-white-50 small">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2">
        Sign In to Portal
      </button>
    </form>
  </div>
</body>
</html>
