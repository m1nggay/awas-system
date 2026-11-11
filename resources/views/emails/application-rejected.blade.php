<p>{{ __('Hi :name,', ['name' => $name]) }}</p>
<p>{{ __('We reviewed your AWAS membership application (:reference).', ['reference' => $reference]) }}</p>
<p><strong>{{ __('Application status:') }}</strong> {{ __('Rejected') }}</p>
<p><strong>{{ __('Reason:') }}</strong> {{ $reason }}</p>
<p><strong>{{ __('What to do next:') }}</strong> {{ __('please contact the barangay water office to discuss this reason and how to proceed. You can also log in with your username and password to see this update.') }}</p>
