<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', 'KobiConnect') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: #0a0b0f;
            color: #faf8f5;
            font-family: 'Poppins', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            width: 100%;
            max-width: 28rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }
        .logo-link {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #faf8f5;
        }
        .logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
        }
        .logo-svg {
            width: 2.25rem;
            height: 2.25rem;
            fill: currentColor;
        }
        .card {
            width: 100%;
            background-color: #0f0f12;
            border: 1px solid #1e1f21;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            background-color: #141519;
            border: 1px solid #1e1f21;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 0.75rem;
            font-weight: 600;
            color: #18e299;
            margin-bottom: 1rem;
        }
        .badge-dot {
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 9999px;
            background-color: #18e299;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.025em;
            color: #faf8f5;
            margin-bottom: 0.5rem;
        }
        .description {
            font-size: 0.875rem;
            color: #cfcdca;
            line-height: 1.5;
            margin-bottom: 2rem;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        @media (min-width: 640px) {
            .actions {
                flex-direction: row;
            }
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            height: 2.25rem;
            padding: 0 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
            flex: 1;
            transition: background-color 150ms ease, opacity 150ms ease;
        }
        .btn-primary {
            background-color: #faf8f5;
            color: #0a0b0f;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .btn-outline {
            background-color: transparent;
            color: #faf8f5;
            border: 1px solid #1e1f21;
        }
        .btn-outline:hover {
            background-color: #141519;
        }
        .brand-footer {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 0.75rem;
            color: rgba(207, 205, 202, 0.6);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ url('/') }}" class="logo-link">
            <div class="logo-box">
                <svg viewBox="0 0 40 42" class="logo-svg" xmlns="http://www.w3.org/2000/svg">
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M17.2 5.63325L8.6 0.855469L0 5.63325V32.1434L16.2 41.1434L32.4 32.1434V23.699L40 19.4767V9.85547L31.4 5.07769L22.8 9.85547V18.2999L17.2 21.411V5.63325ZM38 18.2999L32.4 21.411V15.2545L38 12.1434V18.2999ZM36.9409 10.4439L31.4 13.5221L25.8591 10.4439L31.4 7.36561L36.9409 10.4439ZM24.8 18.2999V12.1434L30.4 15.2545V21.411L24.8 18.2999ZM23.8 20.0323L29.3409 23.1105L16.2 30.411L10.6591 27.3328L23.8 20.0323ZM7.6 27.9212L15.2 32.1434V38.2999L2 30.9666V7.92116L7.6 11.0323V27.9212ZM8.6 9.29991L3.05913 6.22165L8.6 3.14339L14.1409 6.22165L8.6 9.29991ZM30.4 24.8101L17.2 32.1434V38.2999L30.4 30.9666V24.8101ZM9.6 11.0323L15.2 7.92117V22.5221L9.6 25.6333V11.0323Z"
                    />
                </svg>
            </div>
        </a>

        <div class="card">
            <div class="badge">
                <span class="badge-dot"></span>
                <span>HTTP @yield('code')</span>
            </div>

            <h1 class="title">@yield('title')</h1>
            <p class="description">@yield('message')</p>

            <div class="actions">
                @section('actions')
                    <a href="{{ url('/') }}" class="btn btn-primary">Ana Sayfaya Dön</a>
                    <button onclick="window.history.back()" type="button" class="btn btn-outline">Geri Dön</button>
                @show
            </div>
        </div>

        <p class="brand-footer">KobiConnect</p>
    </div>
</body>
</html>
