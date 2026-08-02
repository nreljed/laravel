<h1>Hello {{ $userName }}!</h1>

<p>Thank you for registering. Please verify your email address by clicking the button below.</p>

<div class="text-center mt-4 mb-4">
  <a href="{{ $verificationUrl }}" class="button">Verify Email</a>
</div>

<p>If you did not create an account, no further action is required.</p>