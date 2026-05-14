<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в систему</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, .35), transparent 35%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, .20), transparent 35%),
                #020617;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e5e7eb;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: rgba(15, 23, 42, .94);
            border: 1px solid #334155;
            border-radius: 22px;
            padding: 34px;
            box-shadow: 0 35px 90px rgba(0, 0, 0, .55);
        }

        .system-logo {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .form-control {
            background: #020617;
            border-color: #334155;
            color: #fff;
        }

        .form-control:focus {
            background: #020617;
            color: #fff;
            border-color: #38bdf8;
            box-shadow: 0 0 0 .2rem rgba(56, 189, 248, .2);
        }

        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="system-logo">
        Ж
    </div>

    <h3 class="fw-bold mb-1">СЭПЖ</h3>

    <div class="text-secondary mb-4">
        Вход в систему электронных производственных журналов
    </div>

    <div class="alert alert-danger d-none" id="loginError"></div>

    <form id="loginForm">
        <div class="mb-3">
            <label class="form-label">Email / логин</label>
            <input type="text"
                   name="email"
                   class="form-control"
                   autocomplete="username"
                   placeholder="user@example.com">
        </div>

        <div class="mb-4">
            <label class="form-label">Пароль</label>
            <input type="password"
                   name="password"
                   class="form-control"
                   autocomplete="current-password"
                   placeholder="Введите пароль">
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Войти
        </button>
    </form>

    <div class="text-secondary small mt-4">
        Доступ выдаётся администратором системы.
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        $('#loginError').addClass('d-none').text('');

        $.ajax({
            url: "{{ route('user.login.post') }}",
            method: "POST",
            data: $(this).serialize(),
            success: function (response) {
                if (response.success) {
                    window.location.href = response.redirect;
                }
            },
            error: function (xhr) {
                let message = 'Ошибка входа';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                $('#loginError').removeClass('d-none').text(message);
            }
        });
    });
</script>

</body>
</html>
