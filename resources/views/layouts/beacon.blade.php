<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <title>@yield('title')</title>
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="/assets/spectre.min.css" />
    <link rel="stylesheet" href="/assets/spectre-icons.min.css" />
    <script src="https://js.stripe.com/v3/"></script>
    <style type="text/css" media="screen">
        html {
            --primary-color: @yield('primary_color');
            --secondary-color: @yield('secondary_color');
        }

        .StripeElement {
            background-color: white;
            height: 36px;
            padding: 6px 8px;
            border-radius: 4px;
            border: 1px solid transparent;
            box-shadow: 0 1px 3px 0 #e6ebf1;
            -webkit-transition: box-shadow 150ms ease;
            transition: box-shadow 150ms ease;
        }

        .StripeElement--focus {
            box-shadow: 0 1px 3px 0 #cfd7df;
        }

        .StripeElement--invalid {
            border-color: #fa755a;
        }

        .StripeElement--webkit-autofill {
            background-color: #fefde5 !important;
        }

        a:focus {
            box-shadow: none;
        }

        .rotate {
            -webkit-animation: spin 2s linear infinite;
            -moz-animation: spin 2s linear infinite;
            animation: spin 2s linear infinite;
        }

        @-moz-keyframes spin {
            100% {
                -moz-transform: rotate(360deg);
            }
        }

        @-webkit-keyframes spin {
            100% {
                -webkit-transform: rotate(360deg);
            }
        }

        @keyframes spin {
            100% {
                -webkit-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body style="background: transparent">
    <style id="styles" media="screen"></style>
    <div id="kickstart-modal" class="modal active {{ $modal_lg? 'modal-lg': '' }}">
        <a href="#close" class="modal-overlay" aria-label="Close" onclick="return false"></a>
        <div class="modal-container mb-2">
            <div class="modal-header pb-0">
                <header class="navbar">
                    <section class="navbar-section">
                        <strong class="navbar-brand mr-2">{{ __($page_title) }}</strong>
                    </section>
                    <section class="navbar-section">
                        @guest('customers')
                        <a id="auth-signin" href="#" class="btn btn-link" onclick="toggleSignin(true)">
                            {{ __('Login') }}
                        </a>
                        @else
                        <a id="auth-signout" href="#" class="btn btn-link float-right" onclick="requestLogout()">
                            {{ __('Logout') }}
                        </a>
                        @endauth
                        <button class="btn btn-clear" onclick="toggleModal()" aria-label="Close"></button>
                    </section>
                </header>
            </div>
            <div class="modal-body">
                @if (!auth()->id() || !auth()->user()->subscribed($project->id))
                <ul id="steps-control" class="step">
                    <li id="plan-selection" class="step-item active">
                        <a href="#" style="padding-left: 0" onclick="goToStep(0)">{{ __('Select plan') }}</a>
                    </li>
                    <li id="member-registration" class="step-item">
                        <a href="#" onclick="goToStep(1)">{{ __(auth()->id() ? 'Profile' : 'Registration') }}</a>
                    </li>
                    <li id="payment" class="step-item">
                        <a href="#" onclick="goToStep(2)">
                            {{ __('Confirmation') }}
                        </a>
                    </li>
                </ul>
                @endif
                <div id="error" class="toast toast-error my-2 d-hide">
                    <button class="btn btn-clear float-right" onclick="toggleError()"></button>
                    <p id="error-message"></p>
                </div>
                <div id="loading" class="d-flex d-hide" style="min-height: 160px; align-items: center; justify-content: center;">
                    <span><i class="icon icon-refresh mr-2 rotate"></i>{{ __('Loading...') }}</span>
                </div>
                @yield('content')
                @guest('customers')
                <form id="form-signin" class="d-hide" method="post" action="#">
                    @csrf
                    <div class="columns">
                        <div class="column col-6 col-xs-12 col-mx-auto">
                            <div class="form-group">
                                <label class="form-label" for="email">{{ __('E-mail') }}</label>
                                <input class="form-input" type="email" name="email" placeholder="{{ __('E-mail') }}" required />
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="password">{{ __('Password') }}</label>
                                <input class="form-input" type="password" name="password" placeholder="{{ __('Password') }}" required />
                            </div>
                            <div>
                                <label class="form-label" for="remember">
                                    <input id="remember" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                                    <span class="text-base ml-2">
                                        {{ __('Remember Me') }}
                                    </span>
                                </label>
                            </div>
                            <div class="form-group columns my-2">
                                <div class="column col-6" style="padding:0">
                                    <button class="btn btn-link mt-2" type="button" onclick="toggleSignin(false)">
                                        {{ __('Register') }}
                                    </button>
                                </div>
                                <div class="column col-6 text-right">
                                    <button class="btn btn-primary mt-2" type="submit">
                                        {{ __('Send') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>
                @endauth
            </div>
        </div>
    </div>
    <script>
        if (!window.fetch) {
            document.write(
                '<script src="https://cdnjs.cloudflare.com/ajax/libs/fetch/2.0.4/fetch.min.js"><\/script>'
            )
        }
    </script>
    @yield('script')
</body>

</html>