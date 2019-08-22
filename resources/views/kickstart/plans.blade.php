<div id="plans" class="columns">
    <div class="column col-sm-12 col-8 col-mx-auto">
        @foreach ($plans as $plan)
        <div class="accordion">
            <input id="plan_{{$loop->index}}" type="radio" name="accordion-checkbox" hidden>
            <label class="accordion-header c-hand" for="plan_{{$loop->index}}">
                <span class="text-primary">
                    <i class="icon icon-arrow-right mr-2"></i>
                    {{ $plan->name }}
                </span>
            </label>
            <div class="accordion-body">
                <div class="card mt-1">
                    <div class="card-body">
                        <div class="tile tile-centered py-1">
                            <div class="tile-content">
                                <small class="tile-subtitle text-gray">{{ __('Price') }}</small>
                                <div class="tile-title">¥{{ number_format($plan->amount) }} / {{ __($plan->interval) }}</div>
                            </div>
                        </div>
@if ($plan->description)
                        <div class="tile tile-centered py-1">
                            <div class="tile-content">
                                <small class="tile-subtitle text-gray">{{ __('Description') }}</small>
                                <div class="tile-title">{{ $plan->description }}</div>
                            </div>
                        </div>
@endif
                    </div>
                    <div class="card-footer">
                        <button onclick="choosePlan('{{ $plan->id }}')" class="btn btn-primary btn-block">{{ $subscribe_text }}</button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
