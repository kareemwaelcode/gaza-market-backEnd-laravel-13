<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
<meta name="generator" content="CoolAdmin 3.5.0"/>
<meta name="description" content="Marketing analytics dashboard with traffic trend, source breakdown, and top pages."/>
<title>Gaza Market | SYSTEM | @yield('title')</title>
<meta property="og:type" content="website"/>
<meta property="og:title" content="Marketing analytics | CoolAdmin Bootstrap 5 Admin Dashboard"/>
<meta property="og:description" content="Marketing analytics dashboard with traffic trend, source breakdown, and top pages."/>
<meta property="og:image" content="screenshots/cooladmin-bootstrap-dashboard-2.png"/>
<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:title" content="Marketing analytics | CoolAdmin Bootstrap 5 Admin Dashboard"/>
<meta name="twitter:description" content="Marketing analytics dashboard with traffic trend, source breakdown, and top pages."/>
<meta name="theme-color" content="#4272d7"/>
<link href="{{ asset('cms/css/font-face.css') }}" rel="stylesheet" media="all"/>
<link rel="preconnect" href="{{ asset('cms/https://rsms.me/') }}"/>
<link rel="stylesheet" href="{{ asset('cms/https://rsms.me/inter/inter.css') }}"/>
<link href="{{ asset('cms/vendor/fontawesome-7.3.1/css/all.min.css') }}" rel="stylesheet" media="all"/>
<link href="{{ asset('cms/vendor/bootstrap-5.3.8.min.css') }}" rel="stylesheet" media="all"/>
<link href="{{ asset('cms/vendor/css-hamburgers/hamburgers.min.css') }}" rel="stylesheet" media="all"/>
<link href="{{ asset('cms/css/theme.css') }}" rel="stylesheet" media="all"/>
<link href="{{ asset('cms/css/app.css') }}" rel="stylesheet" media="all"/>
//new links
  <link rel="stylesheet" href="{{ asset('cms/plugins/toastr/toastr.min.css') }}">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <script src="https://unpkg.com/lucide@latest"></script>

@yield('styles')
</head>
<body class="app"><a class="visually-hidden-focusable skip-link" href="#main-content">Skip to main content</a>
<div class="page-wrapper">
<header class="header-mobile d-block d-lg-none">
<div class="header-mobile__bar">
<div class="container-fluid">
<div class="header-mobile-inner"><a class="logo" href="index.html"><img src="{{ asset('cms/images/icon/logo.png') }}" alt="CoolAdsmin"></a>
<button class="hamburger hamburger--slider" type="button" aria-label="Toggle navigation"><span class="hamburger-box"><span class="hamburger-inner"></span></span></button>
</div>
</div>
</div>
<nav class="navbar-mobile">
<div class="container-fluid">
<ul class="navbar-mobile__list list-unstyled">
<li class="has-sub"><a class="js-arrow" href="#"><i class="fa-solid fa-tachometer-alt"></i>Dashboard</a>
<ul class="navbar-mobile-sub__list list-unstyled js-sub-list">
  <li><a href="index3.html">Dashboard 1</a></li>
</ul>
</li>
<li class="has-sub"><a class="js-arrow" href="#"><i class="fa-solid fa-copy"></i>Pages</a>
<ul class="navbar-mobile-sub__list list-unstyled js-sub-list">
  <li><a href="login.html">Login</a></li>
  <li><a href="register.html">Register</a></li>
  <li><a href="forget-pass.html">Forgot password</a></li>
  <li><a href="404.html">404 not found</a></li>
  <li><a href="500.html">500 server error</a></li>
  <li><a href="maintenance.html">Maintenance</a></li>
</ul>
</li>
<li class="has-sub"><a class="js-arrow" href="#"><i class="fa-solid fa-desktop"></i>UI Elements</a>
<ul class="navbar-mobile-sub__list list-unstyled js-sub-list">
  <li><a href="button.html">Buttons</a></li>
  <li><a href="badge.html">Badges</a></li>
  <li><a href="tab.html">Tabs</a></li>
  <li><a href="card.html">Cards</a></li>
  <li><a href="alert.html">Alerts</a></li>
  <li><a href="progress-bar.html">Progress bars</a></li>
  <li><a href="modal.html">Modals</a></li>
  <li><a href="switch.html">Switches</a></li>
  <li><a href="grid.html">Grid system</a></li>
  <li><a href="fontawesome.html">Font Awesome</a></li>
  <li><a href="typo.html">Typography</a></li>
</ul>
</li>
</ul>
</div>
</nav>
</header>
<aside class="menu-sidebar" id="main-sidebar">
<div class="logo"><a class="logo-link" href="{{ route('dashboard') }}" aria-label="CoolAdminss homess"><span class="logo-mark" aria-hidden="true">G</span><span class="logo-text">Gaza Market</span></a>
<button class="sidebar-close js-sidebar-toggle" type="button" aria-label="Close navigation"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
</div>
<div class="menu-sidebar__content js-scrollbar1">
<nav class="navbar-sidebar">
<ul class="list-unstyled navbar__list">
<li class="active has-sub"><a class="js-arrow open" href="#"><i class="fa-solid fa-tachometer-alt"></i>Dashboard</a>
<ul class="list-unstyled navbar__sub-list js-sub-list" style="display: block;">
  <li class="active"><a href="{{ route('dashboard') }}"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house preview-icon"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Home Dashboard</a></li>
</ul>
</li>


<li class="has-sub"><a class="js-arrow" href="#"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-shield preview-icon"><path d="M10 15H6a4 4 0 0 0-4 4v2"/><path d="M22 17.5c0 2.499-1.75 3.749-3.83 4.474a.5.5 0 0 1-.335-.005c-2.085-.72-3.835-1.97-3.835-4.47V14a.5.5 0 0 1 .5-.499c1 0 2.25-.6 3.12-1.36a.6.6 0 0 1 .76-.001c.875.765 2.12 1.36 3.12 1.36a.5.5 0 0 1 .5.5z"/><circle cx="9" cy="7" r="4"/></svg>Admins</a>
<ul class="list-unstyled navbar__sub-list js-sub-list">
  <li><a href="{{ route('admin.index') }}"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-tv-minimal preview-icon"><path d="M7 21h10"/><rect width="20" height="14" x="2" y="3" rx="2"/></svg>Index</a></li>
  <li><a href="{{ route('admin.create') }}"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus preview-icon"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>Create</a></li>
</ul>
</li>

</ul>
</nav>
</div>
</aside>
<div class="page-container">
<header class="header-desktop">
<div class="section__content section__content--p30">
<div class="container-fluid">
<div class="header-wrap">
<button class="sidebar-toggle js-sidebar-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="main-sidebar"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu preview-icon"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg></button>
<form class="form-header" role="search" onsubmit="return false"><i class="fa-solid fa-magnifying-glass form-header__icon" aria-hidden="true"></i>
  <input class="au-input au-input--xl" type="search" name="search" placeholder="Search anything…" aria-label="Search"><kbd class="form-header__hint" aria-hidden="true">⌘K</kbd>
</form>
<div class="header-button">
  <div class="noti-wrap">
    <div class="noti__item js-item-menu" role="button" tabindex="0" aria-haspopup="true" aria-label="Messages"><i class="fa-solid fa-comment-dots"></i><span class="quantity">1</span>
      <div class="mess-dropdown js-dropdown">
        <div class="mess__title">
          <p>You have 2 news message</p>
        </div>

        <div class="mess__footer"><a href="#">View all messages</a></div>
      </div>
    </div>
    <div class="noti__item js-item-menu" role="button" tabindex="0" aria-haspopup="true" aria-label="Emails"><i class="fa-solid fa-envelope"></i><span class="quantity">1</span>
      <div class="email-dropdown js-dropdown">
        <div class="email__title">
          <p>You have 3 New Emails</p>
        </div>

        <div class="email__footer"><a href="#">See all emails</a></div>
      </div>
    </div>
    <div class="noti__item js-item-menu" role="button" tabindex="0" aria-haspopup="true" aria-label="Notifications"><i class="fa-solid fa-bell"></i><span class="quantity">3</span>
      <div class="notifi-dropdown js-dropdown">
        <div class="notifi__title">
          <p>You have 3 Notifications</p>
        </div>
 
        <div class="notifi__footer"><a href="#">All notifications</a></div>
      </div>
    </div>
  </div>
  <div class="account-wrap">
    <div class="account-item clearfix js-item-menu" role="button" tabindex="0" aria-haspopup="true" aria-label="Account menu">
      <div class="image"><img src="{{ asset('cms/images/icon/avatar-01.jpg') }}" alt="John Doe"></div>
      <div class="content"><a class="js-acc-btn" href="#">john doe</a></div>
      <div class="account-dropdown js-dropdown">
        <div class="info clearfix">
          <div class="image"><a href="#"><img src="{{ asset('cms/images/icon/avatar-01.jpg') }}" alt="John Doe"></a></div>
          <div class="content">
            <h5 class="name"><a href="#">john doe</a></h5><span class="email">johndoe@example.com</span>
          </div>
        </div>
        <div class="account-dropdown__body">
          <div class="account-dropdown__item"><a href="#"><i class="fa-solid fa-user"></i>Account</a></div>
          <div class="account-dropdown__item"><a href="#"><i class="fa-solid fa-gear"></i>Setting</a></div>
          <div class="account-dropdown__item"><a href="#"><i class="fa-solid fa-sack-dollar"></i>Billing</a></div>
        </div>
        <div class="account-dropdown__footer"><a href="#"><i class="fa-solid fa-power-off"></i>Logout</a></div>
      </div>
    </div>
  </div>
</div>
</div>
</div>
</div>
</header>

<main class="main-content" id="main-content">
<div class="section__content section__content--p30">
<div class="container-fluid">

@if (request()->routeIs('dashboard'))
        <div class="page-header">
            <div>
                <h1>Overview of the Gaza Market Platform</h1>
                <p class="subtitle">Users, visitors, stores, products, and market ads at a glance.</p>
            </div>
            <div class="page-header__actions">
                <button type="button" class="date-chip" aria-label="Date range: last 28 days">
                    <i class="fa-regular fa-calendar"></i>
                    Last 28 days
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <button type="button" class="m-btn m-btn--ghost" id="dash-refresh-btn" aria-label="Refresh dashboard data">
                    <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                    Refresh
                </button>
                <button type="button" class="m-btn m-btn--ghost">
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    Export
                </button>
            </div>
        </div>

<div class="row row-tight">
    <div class="col-sm-6 col-lg">
        <article class="stat-card">
            <div class="stat-card__head">
                <p class="stat-card__label">users</p>
                <span class="stat-card__icon stat-card__icon--c1"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users preview-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg></span>
            </div>
            <p class="stat-card__value">0</p>
        </article>
    </div>

    <div class="col-sm-6 col-lg">
        <article class="stat-card">
            <div class="stat-card__head">
                <p class="stat-card__label">Stores</p>
                <span class="stat-card__icon stat-card__icon--c3"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-store preview-icon"><path d="M15 21v-5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5"/><path d="M17.774 10.31a1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.451 0 1.12 1.12 0 0 0-1.548 0 2.5 2.5 0 0 1-3.452 0 1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.77-3.248l2.889-4.184A2 2 0 0 1 7 2h10a2 2 0 0 1 1.653.873l2.895 4.192a2.5 2.5 0 0 1-3.774 3.244"/><path d="M4 10.95V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8.05"/></svg></span>
            </div>
            <p class="stat-card__value">0</p>
        </article>
    </div>
    <div class="col-sm-6 col-lg">
        <article class="stat-card">
            <div class="stat-card__head">
                <p class="stat-card__label">Products</p>
                <span class="stat-card__icon stat-card__icon--c4"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box preview-icon"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>
            </div>
            <p class="stat-card__value">0</p>
        </article>
    </div>

    <div class="col-sm-6 col-lg">
        <article class="stat-card">
            <div class="stat-card__head">
                <p class="stat-card__label">Market ads</p>
                <span class="stat-card__icon stat-card__icon--c1"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-megaphone preview-icon"><path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/></svg></span>
            </div>
            <p class="stat-card__value">0</p>
        </article>
    </div>
    <div class="col-sm-6 col-lg">
        <article class="stat-card">
            <div class="stat-card__head">
                <p class="stat-card__label">Visitors today</p>
                <span class="stat-card__icon stat-card__icon--c2"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye preview-icon"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg></span>
            </div>
            <p class="stat-card__value">0</p>
            <ul class="stat-card__list">
                <li><span>Total</span><strong>0</strong></li>
            </ul>
        </article>
    </div>
</div>

<!-- Traffic trend + Sources donut -->
<div class="row row-tight" style="margin-top: 16px;">
    <div class="col-lg-12">
        <section class="m-card" aria-labelledby="traffic-title">
            <header class="m-card__header">
                <div>
                    <h2 class="m-card__title" id="traffic-title">Traffic over time</h2>
                    <p class="m-card__subtitle">Daily visitors vs. sessions, last 28 days.</p>
                </div>
            </header>
            <div style="height: 280px; position: relative;">
                <canvas id="traffic-trend"></canvas>
            </div>
        </section>
    </div>
</div>
@endif

        @yield('content')

        <!-- Footer -->
        <div class="row" style="margin-top: 28px;">
            <div class="col-md-12">
                <div class="copyright">
                    <p>Copyright {{ now()->year }} - {{ now()->year+1 }} <a href="{{ env('APP_NAME') }}" rel="nofollow" target="_blank">Gaza Market</a>.</p>
                </div>
            </div>
        </div>

</div>
</div>
</main>
</div>
</div>
<script src="{{asset('cms/js/vanilla-utils.js')}}"></script>
<script src="{{asset('cms/vendor/bootstrap-5.3.8.bundle.min.js')}}"></script>
<script src="{{asset('cms/vendor/chartjs/chart.umd.js-4.5.1.min.js')}}"></script>
<script src="{{asset('cms/js/bootstrap5-init.js')}}"></script>
<script src="{{asset('cms/js/main-vanilla.js')}}"></script>
<script src="{{asset('cms/js/modern-plugins.js')}}"></script>
//new links
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="{{ asset('cms/js/crud.js') }}"></script>
@yield('scripts')
</body>
</html>