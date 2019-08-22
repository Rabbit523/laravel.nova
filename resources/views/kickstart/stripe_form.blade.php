<div class="column col-12 mb-2">
    <div class="form-group">
        <label class="form-label" for="credit-card-number">カード</label>
        <div id="card-element">
            <!-- A Stripe Element will be inserted here. -->
        </div>
        <div id="card-errors" class="form-input-hint" role="alert"></div>
    </div>
</div>
<div class="column col-12 mb-2">
    <div class="form-group">
        <label class="form-label" for="name">{{ __('Name') }}</label>
        <input class="form-input" type="text" name="name" placeholder="{{ __('Name') }}" />
    </div>
</div>
