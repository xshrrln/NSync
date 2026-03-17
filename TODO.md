# Google OAuth Fix TODO

## Plan Steps
- [x] 1. Update app/Http/Controllers/GoogleAuthController.php to use standard Socialite->user() without stateless/custom Guzzle
- [x] 2. Verify .env GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback (OAuth successful)
- [ ] 3. Ensure Google Console OAuth redirect URI exactly matches above
- [ ] 4. Run `php artisan config:clear &amp;&amp; php artisan cache:clear`
- [ ] 5. Test login: /auth/google -> callback -> dashboard
- [ ] 6. If SSL error, check php.ini curl.cainfo or remove cacert.pem workaround

Original TODO.md content preserved below if existed:
```
(Add original content here after first update)
