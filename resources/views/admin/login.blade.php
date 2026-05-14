<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в админку</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top, #1e3a8a, #020617 55%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e5e7eb;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(15, 23, 42, .92);
            border: 1px solid #334155;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .45);
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
    </style>
</head>
<body>

<div class="login-card">
    <h3 class="fw-bold mb-1">СЭПЖ</h3>
    <div class="text-secondary mb-4">Вход superadmin</div>

    <div class="alert alert-danger d-none" id="loginError"></div>

    <form id="loginForm">
        <div class="mb-3">
            <label class="form-label">Логин</label>
            <input type="text" name="login" class="form-control" autocomplete="username">
        </div>

        <div class="mb-4">
            <label class="form-label">Пароль</label>
            <input type="password" name="password" class="form-control" autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Войти
        </button>
    </form>
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
            url: "{{ route('admin.login.post') }}",
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
