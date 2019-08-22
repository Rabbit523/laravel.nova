<script type="text/javascript">
    var server = '{{ $server_url }}'
    var projectId = '{{ $project->id }}'
    // global data store
    var store = {
        plan: { id: null, name: '' },
        maxStep: 0,
        plans: [
@foreach ($plans as $plan)
            {!! $plan->toJson() !!}@if(!$loop->last),@endif
@endforeach
        ],
@auth
        user: {!! auth()->user()->toJson() !!}
@endauth
    }
    var currencyMap = {
        usd: '＄',
        jpy: '￥',
        eur: '€',
    }
    //init forms variables
    var formRegistration = document.getElementById('form-registration')
    var formPayment = document.getElementById('form-payment')
    var formSignin = document.getElementById('form-signin')
    // render plans
@if (!auth()->id() || !auth()->user()->subscribed($project->id))
    @if (count($plans) < 3)
    renderPlans(store.plans)
    @endif
@else
    renderSubscriptions()
@endif

    // Create a Stripe client.
    var stripe = Stripe('{{ $stripe_pk }}')

    // Create an instance of Elements.
    var elements = stripe.elements({
        locale: 'ja',
    })

    // Stripe styles
    var style = {
        base: {
            color: '#32325d',
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            fontSmoothing: 'antialiased',
            fontSize: '16px',
            '::placeholder': {
                color: '#aab7c4',
            },
        },
        invalid: {
            color: '#fa755a',
            iconColor: '#fa755a',
        },
    }

    // Create an instance of the card Element.
    var card = elements.create('card', { style: style, hidePostalCode: true })

    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element')

    function toggleSignin(on) {
        toggleError()
        var stepsControl = document.querySelector('#steps-control')
        var steps = document.querySelectorAll('.form-step')
        for (var i = 0; i < steps.length; i++) {
            steps[i].classList.toggle('d-hide', on)
        }
        stepsControl.classList.toggle('d-hide', on)
        formSignin.classList.toggle('d-hide', !on)
        var signin = document.querySelector('#auth-signin')
        if (!on) {
            goToStep(0)
        }
        signin ? signin.classList.toggle('d-hide', on): false
        return false
    }
    // hide/show signin/signout buttons depending on state
    function renderAuth() {
        var isAuthenticated = Boolean(store.user)
        var signin = document.querySelector('#auth-signin')
        var signout = document.querySelector('#auth-signout')
        signin ? signin.classList.toggle('d-hide', isAuthenticated): false
        signout ? signout.classList.toggle('d-hide', !isAuthenticated): false
    }
    // navigate to a step: 0,1,2
    function goToStep(step) {
        toggleError()
        if (step > store.maxStep) {
            return
        }
        var pills = document.querySelectorAll('.step-item')
        for (var i = 0; i < pills.length; i++) {
            if (i == step) {
                pills[i].classList.add('active')
            } else {
                pills[i].classList.remove('active')
            }
        }
        var steps = document.querySelectorAll('.form-step')
        for (var i = 0; i < steps.length; i++) {
            if (i == step) {
                steps[i].classList.remove('d-hide')
            } else {
                steps[i].classList.add('d-hide')
            }
        }
        if (step == 2) {
            var plan = store.plan
            var amount = plan.amount || 0
            var currency = plan.currency || 'jpy'
            var currencyCharacter = currencyMap[currency]
            var tax = amount * 0.08
            if (plan.currency == 'jpy') {
                tax = Math.ceil(tax)
            }
            var discount = 0
            var total = amount + tax - discount
            render({
                'selected-plan-name': plan.name,
                'selected-plan-price': currencyCharacter + amount,
                'tax-calculated': currencyCharacter + tax,
                'total-calculated': currencyCharacter + (amount + tax),
                'discount-calculated': '-' + currencyCharacter + discount,
                'total-to-pay': currencyCharacter + total,
            })
        }
    }
    // close modal dialog - just sent message to parent window
    var toggleModal = function() {
        window.parent.postMessage('close', '*')
    }
    // first step - handles plan selection
    function choosePlan(id) {
        toggleError()
        plan = store.plans.find(function(p) {
            return p.id == id
        })
        store.plan = plan
        store.maxStep = store.user ? 2 : 1
        goToStep(store.user ? 2 : 1)
    }
    // changes innerText of all elements with [data-render] attribute
    function render(data) {
        Object.keys(data).forEach(function(key) {
            var elems = document.querySelectorAll('[data-render="' + key + '"]')
            elems.forEach(function(el) {
                el.innerText = data[key]
            })
        })
    }
    // render plans for first step
    function renderPlans(plansSettings) {
        var html = plansSettings
            .map(function(plan, index) {
                var interval = ''
                if (plan.interval) {
                    interval = ' / '
                    if (plan.interval_count > 1) interval += plan.interval_count
                    interval += ' ' + (plan.interval == 'year' ? '年': '月')
                }
                var price = formatCurrency(plan.currency, plan.amount) + interval

                return (
                    '<div class="column col-6 col-xs-12"><div class="card mb-2">' +
                    '<div class="card-header plan-header--medium">' +
                    plan.name +
                    '</div>' +
                    '<div class="card-body py-4 text-center plan-text--medium">' +
                    '<p class="card-subtitle mb-4 muted"><span>' + price +
                    '</span></p><p class="card-text lang-ja">' +
                    (plan.description || '-') +
                    '</p></div><div class="card-footer">' +
                    '<a href="#" class="btn btn-primary btn-block" onclick="choosePlan(\'' +
                    plan.id +
                    '\')" data-render="subscribe_text">{{ $subscribe_text }}</a>' +
                    '</div></div></div>'
                )
            })
            .join('')
        document.getElementById('plans').innerHTML = html
    }
    function formatDate(d) {
        var date = new Date(d)
        var year = date.getFullYear()
        var month = date.getMonth() + 1
        if (month < 10) month = '0' + month
        var day = date.getDate()
        if (day < 10) day = '0' + day
        return year + '/' + month + '/' + day
    }
    function formatCurrency(currency, amount) {
        return (
            (currencyMap[currency] || '') +
            Number(amount).toFixed(currency == 'jpy' ? 0 : 2)
        )
    }
    function toggleLoading(flag) {
        document.getElementById('loading').classList.toggle('d-hide', !flag)
    }
    // display error
    function toggleError(message) {
        var error = document.getElementById('error')
        if (message) {
            var errorMessage = document.getElementById('error-message')
            errorMessage.innerText = message
            error.classList.remove('d-hide')
            document.querySelector('.modal-body').scrollTop = 0
        } else {
            error.classList.add('d-hide')
        }
    }

    // parse url params
    function getParameterByName(name, url) {
        if (!url) url = window.location.href
        name = name.replace(/[\[\]]/g, '\\$&')
        var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
            results = regex.exec(url)
        if (!results) return null
        if (!results[2]) return ''
        return decodeURIComponent(results[2].replace(/\+/g, ' '))
    }

@if (!$preview)
    // PRODUCTION MODE ---------------------
    // CODE FOR REAL MODE
    formRegistration ? formRegistration.onsubmit = submitRegister: false
    formPayment ? formPayment.onsubmit = submitPayment : false
    formSignin ? formSignin.onsubmit = submitSignin: false
    goToStep(0)
    // plans selection (first signup step) is shown by default for anonymous user,
    // use `singin=true` to show signin instead
    if(!store.user && getParameterByName('signin')){
        toggleSignin(true)
    }

    // Handle real-time validation errors from the card Element.
    card.addEventListener('change', displayCardError)
@auth
    var subscriptions = document.getElementById('subscriptions')
    subscriptions ? subscriptions.addEventListener('click', function(e) {
        if (!e.target) return
        e.preventDefault()
        // e.target was the clicked element
        if (e.target.getAttribute('data-subscription-action') == 'toggle-subscribe') {
            toggleSubscribe(e)
        } else if (e.target.getAttribute('data-subscription-action') == 'show-plans') {
            toggleSubscriptionPlans(e.target.getAttribute('data-subscription-id'))
        } else if (e.target.getAttribute('data-subscription-action') == 'choose-plan') {
            chooseSubscriptionPlan(e.target.getAttribute('data-plan-id'))
        } else if (
            e.target.getAttribute('data-subscription-action') == 'save-subscription'
        ) {
            saveSubscription(e)
        }
    }): false
@endauth

    // displays stripe card error
    function displayCardError(event) {
        var displayError = document.getElementById('card-errors')
        if (event.error) {
            displayError.textContent = event.error.message
            displayError.parentElement.classList.add('has-error')
        } else {
            displayError.textContent = ''
            displayError.parentElement.classList.remove('has-error')
        }
    }

    function getFormDataJSON(formData) {
        var body = {}
        formData.forEach(function(value, key) {
            body[key] = value
        })
        return body
    }

    // second step - registration form handler
    function submitRegister(e) {
        e.preventDefault()
        toggleError() // hide error
        var data = getFormDataJSON(new FormData(formRegistration))
        if (!data.email || !data.password) {
            toggleError('Email and password are required')
            return
        }
        toggleFormLoading(e.target, true)
        var url = server + '/kickstart/api/' + projectId + '/register'
        fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            method: 'POST',
            body: JSON.stringify(data),
        })
        .then(handleResponse)
        .then(function(data) {
            if (data && data.user.subscriptions && Array.isArray(data.user.subscriptions)) {
                window.location.reload()
                return
            }
            var signin = document.querySelector('#auth-signin')
            signin ? signin.classList.toggle('d-hide', true): false

            toggleFormLoading(formRegistration, false)
            store.user = data && data.user
            store.maxStep = 2
            goToStep(2)
            formRegistration.classList.add('d-hide')
            document
                .getElementById('success-registration')
                .classList.remove('d-hide')
        })
        .catch(function(error) {
            toggleError(error.message)
            toggleFormLoading(formRegistration, false)
        })
        return false
    }

    // second step - registration form handler
    function submitPayment(e) {
        e.preventDefault()
        toggleError() // hide error
        var data = getFormDataJSON(new FormData(formPayment))
        toggleFormLoading(e.target, true)
        stripe.createToken(card, { name: data.name }).then(function(result) {
            toggleFormLoading(formPayment, false)
            if (result.error) {
                displayCardError(result)
            } else {
                // Send the token to your server.
                if (!store.plan || !store.plan.id) {
                    stripeTokenHandlerCardChange(result.token)
                } else {
                    stripeTokenHandler(result.token)
                }
            }
        })
        return false
    }

    // Submit the form with the token ID.
    function stripeTokenHandlerCardChange(token) {
        toggleError() // hide error
        // Insert the token ID into the form so it gets submitted to the server
        var formData = new FormData(formPayment)
        formData.append('token', token.id)
        toggleFormLoading(formPayment, true)
        var url = server + '/kickstart/api/' + projectId + '/card'
        fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            method: 'PUT',
            body: JSON.stringify(getFormDataJSON(formData)),
        })
            .then(handleResponse)
            .then(function(data) {
                // handle success
                toggleCardForm(false)
                toggleFormLoading(formPayment, false)
                // reload data
                window.location.reload()
            })
            .catch(function(error) {
                toggleError(error.message)
                toggleFormLoading(formPayment, false)
            })
    }

    // Submit the form with the token ID.
    function stripeTokenHandler(token) {
        toggleError() // hide error
        // Insert the token ID into the form so it gets submitted to the server
        var formData = new FormData(formPayment)
        formData.append('token', token.id)
        formData.append('plan_id', store.plan.id)
        toggleFormLoading(formPayment, true)
        var url = server + '/kickstart/api/' + projectId + '/subscription'
        fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            method: 'POST',
            body: JSON.stringify(getFormDataJSON(formData)),
        })
        .then(handleResponse)
        .then(function(data) {
            // handle success
            formPayment.classList.add('d-hide')
            document.getElementById('success').classList.remove('d-hide')
            toggleFormLoading(formPayment, false)
        })
        .catch(function(error) {
            toggleError(error.message)
            toggleFormLoading(formPayment, false)
        })
        return false
    }

    // set forms submit button in loading state and disable all its controls
    function toggleFormLoading(formEl, on) {
        const buttonEl = formEl.querySelector('[type="submit"]')
        const inputEls = formEl.querySelectorAll('input')
        toggleButtonLoading(buttonEl, on)
        inputEls.forEach(function(el) {
            if (on) {
                el.setAttribute('disabled', 'disabled')
            } else {
                el.removeAttribute('disabled')
            }
        })
    }
    function toggleButtonLoading(buttonEl, on){
        buttonEl.classList.toggle('loading', on)
        if (on) {
            buttonEl.setAttribute('disabled', 'disabled')
        } else {
            buttonEl.removeAttribute('disabled')
        }
    }
    // show/hide credit card form
    function toggleCardForm(on) {
        var preview = document.getElementById('card-preview')
        var form = document.getElementById('card-form')
        preview.classList.toggle('d-hide', on)
        form.classList.toggle('d-hide', !on)
        return false
    }

    function handleResponse(response) {
        return response.status != 204 ? response.json().then(json => {
            if (!response.ok) {
                const error = json.errors || {
                    message: response.statusText,
                    status_code: response.status,
                }

                return Promise.reject(error)
            }
            return json
        }): true
    }

    // second step - registration form handler
    function submitSignin(e) {
        e.preventDefault()
        toggleError() // hide error
        var data = getFormDataJSON(new FormData(e.target))
        if (!data.email || !data.password) {
            toggleError('Email and password are required')
            return
        }
        toggleFormLoading(e.target, true)
        var url = server + '/kickstart/api/' + projectId + '/login'
        fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            method: 'POST',
            body: JSON.stringify(data),
        })
            .then(handleResponse)
            .then(function() {
                // toggleFormLoading(e.target, false)
                // store.user = data && data.user
                // renderAuth()
                window.location.reload()
            })
            .catch(function(error) {
                toggleError(error.message)
                toggleFormLoading(e.target, false)
            })
        return false
    }

    function requestLogout(){
        var signout = document.querySelector('#auth-signout')
        toggleButtonLoading(signout, true)
        var url = server + '/kickstart/api/' + projectId + '/logout'
        fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            method: 'POST',
            body: JSON.stringify({_token: '{{ csrf_token() }}'}),
        })
        .then(handleResponse)
        .then(function() {
            store.user = null
            // renderAuth()
            window.location.reload()
        })
        .catch(function(error) {
            toggleError(error.message)
            toggleButtonLoading(signout, false)
        })
    }
@auth
    function toggleSubscriptionPlans(subscriptionId) {
        const containerId = 'subscription-plans'
        var oldContainer = document.getElementById(containerId)
        if (oldContainer) {
            oldContainer.remove()
        }
        if (!subscriptionId) return

        var subscription = store.user.subscriptions.find(function(s) {
            return s.id == subscriptionId
        })
        if (!subscription) return

        var plansHTML = (store.plans || [])
            .map(function(plan, index) {
                var button =
                    '<a class="btn mt-4" data-subscription-action="choose-plan" data-plan-id="' +
                    plan.id +
                    '">選ぶ</a>'
                if (plan.payment_id == subscription.stripe_plan) {
                    button =
                        '<span class="btn btn-success mt-4" disabled tabindex="-1"><i class="icon icon-check"></i></span>'
                }
                return (
                    '<div class="column col-6 col-xs-12"><div class="card mb-2">' +
                    '<div class="card-header plan-header--medium">' +
                    plan.name +
                    '</div>' +
                    '<div class="card-body py-4 text-center plan-text--medium">' +
                    '<p class="card-subtitle mb-4 muted"><span>' +
                    currencyMap[plan.currency] +
                    plan.amount +
                    '</span></p><p class="card-text lang-ja">' +
                    (plan.description || '-') +
                    '</p>' +
                    button +
                    '</div></div></div>'
                )
            })
            .join('')
        var actionsHTML =
            '<div class="pt-1 columns"><div class="column" data-render="subscription-plans-summary"></div>' +
            '<div class="column col-12 col-sm-auto"><a class="btn" data-subscription-action="show-plans">{{ __('Cancel') }}</a>' +
            '<a class="btn btn-primary d-hide ml-2" data-subscription-action="save-subscription" data-subscription-id="' +
            subscription.id +
            '">{{ __('Send') }}</a></div></div>'
        var container = document.createElement('div')
        container.id = containerId
        container.innerHTML =
            '<div class="py-2"><div class="columns">' +
            plansHTML +
            '</div>' +
            actionsHTML +
            '</div>'
        document.getElementById('subscription-' + subscriptionId).appendChild(container)
    }
    // 'choose plan' button handler
    function chooseSubscriptionPlan(planId) {
        var plan = store.plans.find(function(p) {
            return p.id == planId
        })
        if (!plan) return
        var interval = ''
        if (plan.interval) {
            interval = ' / '
            if (plan.interval_count > 1) interval += plan.interval_count
            interval += ' ' + (plan.interval == 'year' ? '年': '月')
        }
        var price = formatCurrency(plan.currency, plan.amount) + interval
        if (plan.amount == 0) {
            price = 'FREE'
        }
        render({
            'subscription-plans-summary': price,
        })
        var saveButton = document.querySelector(
            '[data-subscription-action="save-subscription"]'
        )
        if (saveButton) {
            saveButton.classList.remove('d-hide')
            saveButton.setAttribute('data-plan-id', planId)
        }
    }

    function renderSubscriptions() {
        if (!store.user || !Array.isArray(store.user.subscriptions)) {
            document.getElementById('subscriptions').innerHTML = ''
            var subscriptions = document.querySelector('#card-subscriptions')
            subscriptions ? subscriptions.classList.toggle('d-hide', true): false
            return
        }
        toggleLoading(true)
        var subscriptions = store.user.subscriptions
        var html = subscriptions
            .map(function(subscription, index) {
                var plan =
                    (store.plans || []).find(function(p) {
                        return p.payment_id == subscription.stripe_plan
                    }) || {}
                var divider = ''
                if (index < subscriptions.length - 1) {
                    divider = '<hr class="my-2" style="opacity: 0.3;"/>'
                }
                var buttonHTML = ''
                if (!subscription.ends_at) {
                    buttonHTML =
                        '<a class="btn btn-error" data-subscription-action="toggle-subscribe" data-subscription-id="' +
                        subscription.id +
                        '">' +
                        '{{ __('Cancel Subscription') }}' +
                        '</a>'
                } else if (new Date(subscription.ends_at).getTime() > new Date().getTime()) {
                    buttonHTML =
                        '<a class="btn btn-success" data-subscription-action="toggle-subscribe" data-subscription-id="' +
                        subscription.id +
                        '">' +
                        '{{ __('Resume Subscription') }}' +
                        '</a>'
                }
                var interval = ''
                if (plan.interval) {
                    interval = ' / '
                    if (plan.interval_count > 1) interval += plan.interval_count
                    interval += ' ' + (plan.interval == 'year' ? '年': '月')
                }
                var price = formatCurrency(plan.currency, plan.amount) + interval
                if (plan.amount == 0) {
                    price = 'FREE'
                }
                var changePlanButton = ''
                if (store.plans && store.plans.length > 1) {
                    changePlanButton =
                        '<a href="#" class="btn btn-link btn-sm tooltip tooltip-right" data-tooltip="{{ __('Change payment plan') }}" data-subscription-action="show-plans" data-subscription-id="' +
                        subscription.id +
                        '">' +
                        '<i class="icon icon-edit" style="pointer-events:none;"></i>' +
                        '</a>'
                }
                return (
                    '<div id="subscription-' +
                    subscription.id +
                    '"><div class="columns"><div class="column">' +
                    '<div class="tile-title text-bold d-flex" style="align-items: center">' +
                    price +
                    changePlanButton +
                    '</div><div class="tile-subtitle">{{ __('Start date') }}: ' +
                    formatDate(subscription.created_at) +
                    '</div></div><div class="column col-auto">' +
                    buttonHTML +
                    '</div></div></div>' +
                    divider
                )
            })
            .join('')
        document.getElementById('subscriptions').innerHTML = html
        toggleLoading(false)
    }

    function saveSubscription(e) {
        var subscriptionId = e.target.getAttribute('data-subscription-id')
        var planId = e.target.getAttribute('data-plan-id')
        var subscription = store.user.subscriptions.find(function(s) {
            return s.id == subscriptionId
        })
        var plan = store.plans.find(function(p) {
            return p.id == planId
        })
        if (!subscription || !plan) return
        var data = { plan_id: plan.payment_id }
        toggleButtonLoading(e.target, true)
        var url = server + '/kickstart/api/' + projectId + '/subscription'
        data._token = '{{ csrf_token() }}'
        fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            method: 'put',
            body: JSON.stringify(data),
        })
            .then(handleResponse)
            .then(function(data) {
                toggleButtonLoading(e.target, false)
                // reload data
                window.location.reload()
                // hide plans selection
                toggleSubscriptionPlans()
            })
            .catch(function(error) {
                toggleButtonLoading(e.target, false)
                toggleError(error.message)
            })
    }

    function toggleSubscribe(e) {
        e.preventDefault()
        var subscriptionId = e.target.getAttribute('data-subscription-id')
        var subscription = store.user.subscriptions.find(function(s) {
            return s.id == subscriptionId
        })
        toggleButtonLoading(e.target, true)
        var promise = null
        var url = server + '/kickstart/api/' + projectId + '/subscription'
        subscription._token = '{{ csrf_token() }}'
        if (new Date(subscription.ends_at).getTime() > new Date().getTime()) {
            // resume subscription
            promise = fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                method: 'PATCH',
                body: JSON.stringify(subscription),
            })
        } else {
            // cancel subscription
            promise = fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                method: 'DELETE',
                body: JSON.stringify(subscription),
            })
        }
        promise
            .then(handleResponse)
            .then(function(data) {
                toggleButtonLoading(e.target, false)
                // reload data
                window.location.reload()
            })
            .catch(function(error) {
                toggleButtonLoading(e.target, false)
                toggleError(error.message)
            })
    }
@endauth
    // PRODUCTION MODE ---------------------
@else
    // CODE BELOW IS ONLY FOR PREVIEW MODE ------------------------------------------
    var toggleModal = function() {}
    store.maxStep = 2
    enablePreviewMode()
    goToStep(Number(getParameterByName('step')) || 0)

    function enablePreviewMode() {
        document.querySelectorAll('a').forEach(function(el) {
            el.href = 'javascript:void(0)'
            el.onclick = ''
        })
        document.querySelectorAll('form').forEach(function(el) {
            el.addEventListener('submit', function(evt) {
                evt.preventDefault()
            })
        })

        window.addEventListener(
            'message',
            function(event) {
                var origin = event.origin || event.originalEvent.origin // For Chrome, the origin property is in the event.originalEvent object.
                // if (origin !== /*the container's domain url*/)
                // return;
                if (typeof event.data == 'object' && event.data.call == 'setData') {
                    setData(event.data.value)
                }
            },
            false
        )
        window.parent.postMessage({ type: 'getData', sender: 'popup' }, '*')
    }
    function setData(json) {
        var data = JSON.parse(json)
        var settings = data && data.settings
        renderPlans(data.plans)
        document.querySelector('.modal').classList.toggle('modal-lg', settings.modal_lg)
        render(data.settings)
        if (settings.primary_color) {
            var c = settings.primary_color
            var stylesheet = document.getElementById('styles')
            var styles =
                'html{--primary-color:' +
                c +
                ';}' +
                '.btn{border-color:' +
                c +
                ';}' +
                '.btn.btn-primary{background:' +
                c +
                ';border-color:' +
                c +
                ';}' +
                '.step .step-item a, a:visited{color:' +
                c +
                ';}' +
                '.text-primary, a{color:' +
                c +
                ';}'
            stylesheet.innerHTML = styles
        }
    }
    // CODE ABOVE IS ONLY FOR PREVIEW MODE ------------------------------------------
@endif
</script>
