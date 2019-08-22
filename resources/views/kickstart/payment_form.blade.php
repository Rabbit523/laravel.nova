<div id="form-step-3" class="form-step d-hide">
    <table class="table mb-2">
        <tbody>
            <tr>
                <td class="text-gray">選択したプラン</td>
                <td class="text-right" data-render="selected-plan-name"></td>
            </tr>
            <tr>
                <td class="text-gray">料金</td>
                <td class="text-right" data-render="selected-plan-price"></td>
            </tr>
            <tr>
                <td class="text-gray">消費税 <span id="tax-rate">8%</span></td>
                <td class="text-right" data-render="tax-calculated"></td>
            </tr>
            <tr>
                <td colspan="2" class="bg-gray p-2 text-right">
                    <span
                        class="text-primary h5"
                        data-render="total-calculated"
                    ></span>
                </td>
            </tr>
        </tbody>
    </table>
@if (auth()->id() && auth()->user()->card_last_four)
    <div id="card-preview" class="card mb-2">
        <div class="card-header">
            <div class="card-title h5 text-capitalize">{{ __('Payment method') }}</div>
            <div class="card-subtitle text-gray" data-render="card_brand">{{ auth()->user()->card_brand }}</div>
        </div>
        <div class="card-body">
            <img
                src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAACASURBVEhL7ZRLCoAwDES707Po/e+gnknQmULiJvhjii4y8CAt7Uu7Scn8IiNYwAo2EXTRSXctokMKZuAv77gQhS466fZu6rjXilaEm0rCTSVHIc53DWwd8SR+583lO8kGl3Fvi1nUAzrrLOLEs25qJlBnNpvYTxTQRecAMmcpZQdeR9EwV8HayQAAAABJRU5ErkJggg=="
            />
            ************<span data-render="card_last_four">{{ auth()->user()->card_last_four }}</span>
        </div>
        <div class="card-footer text-right">
            <button
                class="btn btn-primary btn-action btn-lg tooltip"
                data-tooltip="{{ __('Edit') }}"
                onclick="toggleCardForm(true)"
            >
                <i class="icon icon-edit"></i>
            </button>
        </div>
    </div>
@endif
    <form id="form-payment" method="post" action="#" class="mb-1">
        @csrf
        <div id="card-form" class="columns {{ auth()->id() && auth()->user()->card_last_four ? 'd-hide':'' }}">
            @include('kickstart.stripe_form')
        </div>
        <div class="columns">
            <div class="column col-sm-12 my-2">
                <div class="form-group">
                    <input
                        class="form-input"
                        type="text"
                        name="coupon"
                        placeholder="{{ __('Coupon') }}"
                    />
                </div>
            </div>
            <div class="column col-sm-12 my-2 text-right">
                <button class="btn btn-primary" type="submit">
                    {{ __('Subscribe') }}
                </button>
            </div>
        </div>
    </form>
    <div id="success" class="toast toast-success my-2 d-hide">
        <p>{{ __('You have been successfully subscribed! Thank you.') }}</p>
    </div>
</div>
