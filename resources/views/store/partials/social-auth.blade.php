@php $googleLogin = \App\Models\GoogleLoginSetting::current(); @endphp
@if($googleLogin->available())
<div class="auth-google-section">
  <a class="auth-google-button" href="{{ route('account.google.start', $authParams ?? []) }}">
    <svg viewBox="0 0 48 48" aria-hidden="true" width="22" height="22" focusable="false">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z"/>
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.25 5.48-4.76 7.18l7.73 6.01c4.51-4.18 7.07-10.36 7.07-17.66Z"/>
      <path fill="#FBBC05" d="M10.53 28.59A14.41 14.41 0 0 1 9.75 24c0-1.59.27-3.13.77-4.59l-7.98-6.19A24.35 24.35 0 0 0 0 24c0 3.87.93 7.52 2.56 10.78l7.97-6.19Z"/>
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.79l-7.73-6.01c-2.15 1.45-4.92 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.97 6.19C6.51 42.62 14.62 48 24 48Z"/>
    </svg>
    <span>Google ile devam et</span>
    <svg class="auth-google-next" viewBox="0 0 24 24" aria-hidden="true" width="17" height="17" fill="none"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a>
  <div class="auth-divider"><span>veya e-posta adresinle devam et</span></div>
</div>
@endif