<p>{{ __('Hi :name,', ['name' => $name]) }}</p>
<p>{{ __('Good news — your AWAS membership application (:reference) has been approved and your AWAS consumer account is now', ['reference' => $reference]) }} <strong>{{ __('active') }}</strong>.</p>
<p><strong>{{ __('Meter Number:') }}</strong> {{ $meterNumber }}</p>
<p><strong>{{ __('You do not need to create a new account.') }}</strong> {{ __('Just log in at :loginurl with the', ['loginurl' => $loginUrl]) }} <strong>{{ __('username') }}</strong> {{ __('and password you chose when you applied. You can then view your bills and pay online.') }}</p>
