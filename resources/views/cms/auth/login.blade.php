<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <meta name="generator" content="CoolAdmin 3.5.0"/>
    <meta name="description" content="Sign in to the CoolAdmin dashboard."/>
    <title> Gaza Market System | Login</title>
    <meta name="robots" content="noindex,nofollow"/>
    <meta property="og:type" content="website"/>
    <meta property="og:title" content="Sign in | CoolAdmin Bootstrap 5 Admin Dashboard"/>
    <meta property="og:description" content="Sign in to the CoolAdmin dashboard."/>
    <meta property="og:image" content="screenshots/cooladmin-bootstrap-dashboard-2.png"/>
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="Sign in | CoolAdmin Bootstrap 5 Admin Dashboard"/>
    <meta name="twitter:description" content="Sign in to the CoolAdmin dashboard."/>
    <meta name="theme-color" content="#4272d7"/>
    <link href="{{ asset('cms/css/font-face.css') }}" rel="stylesheet" media="all"/>
    <link rel="preconnect" href="https://rsms.me/"/>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css"/>
    <link href="{{ asset('cms/vendor/fontawesome-7.3.1/css/all.min.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('cms/vendor/bootstrap-5.3.8.min.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('cms/vendor/css-hamburgers/hamburgers.min.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('cms/css/theme.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('cms/css/app.css') }}" rel="stylesheet" media="all"/>
  <link rel="stylesheet" href="{{ asset('cms/plugins/toastr/toastr.min.css') }}">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  </head>

  <body class="app auth-page">
    <main class="login-wrap" id="auth-form">
      <div class="login-content">           
         <a href="#" class="auth-brand" aria-label="Gaza Market System">
                <img class="logo-mark" src="{{ asset('cms/images/gazamarket.logo.jpeg') }}" alt="G">
                <span class="logo-text">Gaza Market System</span>
            </a>
            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-subtitle">Log in to proceed to my Gaza Market dashboard</p>

            <form class="login-form">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input id="email" class="au-input" type="email" id="email" name="email"
                        placeholder="you@example.com" autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" class="au-input" type="password" id="password" name="password"
                        placeholder="••••••••" autocomplete="current-password" required>
                </div>
                <button type="button" onclick="login()" class="btn btn-primary au-btn au-btn--green">Sign in</button>
            </form>

      </div>
    </main>
    <script src="{{asset('cms/js/vanilla-utils.js')}}"></script>
    <script src="{{asset('cms/vendor/bootstrap-5.3.8.bundle.min.js')}}"></script>
    <script src="{{asset('cms/js/bootstrap5-init.js')}}"></script>
    <script src="{{asset('cms/js/main-vanilla.js')}}"></script>
    <script src="{{asset('cms/js/modern-plugins.js')}}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.9/dist/axios.min.js"></script>
    <script src="{{ asset('cms/js/crud.js') }}"></script>
  </body>

  <script>
  function login() {
    var guard = '{{ $guard }}';
    axios.post('/cms/'+guard+'/login', {
      email: document.getElementById('email').value,
      password: document.getElementById('password').value,
      guard: guard
    })
        .then(function (response) {
        window.location.href = '/cms/admins'
    })
        .catch(function (error) {
            if (error.response.data.errors !== undefined) {
              showErrorMessages(error.response.data.errors);

            } else {
                showMessage(error.response.data);
            }
        });
  }
  </script>
</html>