
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Set Password | Royal Travel B2B</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f7fb;
            font-family: Arial, sans-serif;
            color: #02143C;
        }

        .card {
            width: 100%;
            max-width: 440px;
            margin: 20px;
            padding: 36px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(2,20,60,.08);
        }

        h1 { margin: 0 0 12px; font-size: 26px; }

        .subtitle {
            color: #667085;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        label {
            display: block;
            font-weight: 600;
            margin: 18px 0 8px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 26px;
            border: 0;
            border-radius: 8px;
            background: #02143C;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover { background: #B7731E; }

        .error {
            margin-top: 8px;
            color: #b42318;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Set Your Password</h1>

    <p class="subtitle">
        Welcome to Royal Travel B2B.
        Create a secure password to activate your account.
    </p>

    <form method="POST"
          action="{{ route('b2b.password.update') }}">
        @csrf

        <input type="hidden"
               name="token"
               value="{{ $token }}">

        <label>Email Address</label>
        <input type="email"
               name="email"
               value="{{ old('email', $email) }}"
               required
               readonly>

        @error('email')
            <div class="error">{{ $message }}</div>
        @enderror

        <label>New Password</label>
        <input type="password"
               name="password"
               minlength="12"
               autocomplete="new-password"
               required>

        @error('password')
            <div class="error">{{ $message }}</div>
        @enderror

        <label>Confirm Password</label>
        <input type="password"
               name="password_confirmation"
               minlength="12"
               autocomplete="new-password"
               required>

        <button type="submit">
            Set Password
        </button>
    </form>
</div>

</body>
</html>
