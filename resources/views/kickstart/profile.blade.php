@extends('layouts.beacon')

@section('title', $title)
@section('primary_color', $primary_color)
@section('secondary_color', $secondary_color)

@section('content')
@if (!auth()->user()->subscribed($project->id))
<div id="form-step-1" class="form-step">
    @include('kickstart.plans')
</div>
<div id="form-step-2" class="form-step d-hide">
    <div id="success-registration" class="toast toast-success my-2 d-hide">
        <p>{{ __('You have been successfully registered.') }}</p>
    </div>
</div>
@include('kickstart.payment_form')
@else
<div class="form-step">
    <div id="card-preview" class="card mb-2">
        <div class="card-header">
            <div class="card-title h5 text-capitalize">{{ __('Payment method') }}</div>
            <div class="card-subtitle text-gray" data-render="card_brand">{{ auth()->user()->card_brand }}</div>
        </div>
        <div class="card-body">
            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAACASURBVEhL7ZRLCoAwDES707Po/e+gnknQmULiJvhjii4y8CAt7Uu7Scn8IiNYwAo2EXTRSXctokMKZuAv77gQhS466fZu6rjXilaEm0rCTSVHIc53DWwd8SR+583lO8kGl3Fvi1nUAzrrLOLEs25qJlBnNpvYTxTQRecAMmcpZQdeR9EwV8HayQAAAABJRU5ErkJggg==" />
            ************<span data-render="card_last_four">{{ auth()->user()->card_last_four }}</span>
        </div>
        <div class="card-footer text-right">
            <button class="btn btn-primary btn-action btn-lg tooltip" data-tooltip="{{ __('Edit') }}" onclick="toggleCardForm(true)">
                <i class="icon icon-edit"></i>
            </button>
        </div>
    </div>

    <form id="form-payment" method="post" action="#" class="mb-2">
        @csrf
        <div id="card-form" class="card d-hide">
            <div class="card-header">
                <div class="card-title h5 text-capitalize">{{ __('Payment method') }}</div>
                <div class="card-subtitle text-gray">{{ __('Update') }}</div>
            </div>
            <div class="card-body">
                <div class="columns">
                    @include('kickstart.stripe_form')
                </div>
            </div>
            <div class="card-footer text-right">
                <button class="btn" onclick="toggleCardForm(false)">{{ __('Cancel') }}</button>
                <button class="btn btn-primary" type="submit">{{ __('Send') }}</button>
            </div>
        </div>
    </form>

    <div id="card-subscriptions" class="card mb-2">
        <div class="card-header">
            <div class="card-title h5 text-capitalize">{{ __('Subscriptions') }}</div>
        </div>

        <div id="subscriptions" class="card-body"></div>
    </div>

    <div class="card mb-2">
        <div class="card-header">
            <div class="card-title h5 text-capitalize">{{ __('Payment history') }}</div>
        </div>

        <div id="invoices" class="card-body">
            @forelse (auth()->user()->invoices as $invoice)
            <div class="columns">
                <div class="column">
                    <div class="tile-title text-bold">
                        ¥{{ number_format($invoice['amount_paid']) }}
                    </div>
                    <div class="tile-subtitle">
                        {{ as_date($invoice['date'])->format('Y/m/d') }}
                    </div>
                </div>
                <div class="column col-auto">
                    <!-- {{ route('kickstart_invoice', [$project->id, $invoice['id']]) }} -->
                    <a class="btn btn-link tooltip tooltip-left" data-tooltip="{{ __('View') }}" href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" rel="noreferrer noopener">
                        <i class="icon icon-share mr-2"></i>{{ __('View') }}</a>
                </div>
            </div>
            @if (!$loop->last)
            <hr class="my-2" style="opacity: 0.3;" />
            @endif
            @empty
            <div class="toast toast-info my-2">
                <p>{{ __('No invoices') }}</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endif
@endsection

@section('script')
@include('kickstart.script')
@endsection