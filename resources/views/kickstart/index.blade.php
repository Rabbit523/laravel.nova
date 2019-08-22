@extends('layouts.beacon')

@section('title', $title)
@section('primary_color', $primary_color)
@section('secondary_color', $secondary_color)

@section('content')
<div id="form-step-1" class="form-step">
    @include('kickstart.plans')
</div>
<div id="form-step-2" class="form-step d-hide">
    <div id="success-registration" class="toast toast-success my-2 d-hide">
        <p>{{ __('You have been successfully registered.') }}</p>
    </div>
    <form id="form-registration" method="post" action="#">
        @csrf
        <input type="hidden" name="remember" value="1" />
        <div class="columns">
            <div class="column col-6 col-xs-12 mb-2">
                <div class="form-group">
                    <label class="form-label" for="name">{{ __('Name') }}</label>
                    <input
                        class="form-input"
                        type="text"
                        name="name"
                        placeholder="{{ __('Name') }}"
                    />
                </div>
            </div>
            <div class="column col-6 col-xs-12 mb-2">
                <div class="form-group">
                    <label class="form-label" for="email">{{ __('E-mail') }}</label>
                    <input
                        class="form-input"
                        type="email"
                        name="email"
                        placeholder="{{ __('E-mail') }}"
                        required
                    />
                </div>
            </div>
        </div>
        <div class="columns">
            <div class="column col-6 col-xs-12 mb-2">
                <div class="form-group">
                    <label class="form-label" for="password">{{ __('Password') }}</label>
                    <input
                        class="form-input"
                        type="password"
                        name="password"
                        placeholder="{{ __('Password') }}"
                        required
                    />
                </div>
            </div>
        </div>
        <div class="columns mt-2">
            <div class="column col-4 col-auto col-mx-auto">
                <button class="btn btn-primary" type="submit">
                    {{ __('Send') }}
                </button>
            </div>
        </div>
    </form>
</div>
@include('kickstart.payment_form')
@endsection

@section('script')
@include('kickstart.script')
@endsection
