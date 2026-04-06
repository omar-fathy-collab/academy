Security notes for token handling

This project contains tooling to improve token storage security. Summary and recommended steps:

1) remember_token
- Stored on `users` table as `remember_token`.
- We apply reversible encryption using Laravel's Crypt facade so Laravel's "remember me" flow continues to work.
- Implementation: `app/Traits/Encryptable.php` and `app/Models/User.php` updated to encrypt `remember_token` values on set and decrypt on get.

Risks:
- Encrypted remember tokens are reversible using APP_KEY. If APP_KEY is lost or rotated without proper re-encryption, tokens become unreadable and users will be logged out. Manage APP_KEY using your secrets manager and rotate carefully.

2) password_reset tokens
- Best practice: store password reset tokens hashed. They are one-time tokens and don't need to be reversible.
- We added tooling to hash existing tokens in-place (`php artisan passwordresets:hash-existing`) and to invalidate/truncate the table if you prefer (`php artisan passwordresets:invalidate`).

3) API tokens / service tokens
- For any long-lived API tokens in the DB, prefer hashed storage and use Laravel Sanctum/Passport for managing tokens and scopes.
- For third-party service credentials in `config/services.php` or `.env`, ensure they are stored in environment variables and not committed to source control.

4) Operational commands
- Dry-run first: each Artisan command supports `--dry`. Always test locally and on a staging environment before applying to production.
- Backup database before running any mass-change commands. You already created a DB backup before operations.

Commands
- php artisan tokens:encrypt [--dry]
- php artisan tokens:rotate-remember [--dry]
- php artisan passwordresets:hash-existing [--dry]
- php artisan passwordresets:invalidate [--dry]

Recommended immediate plan for production
1. Ensure you have a recent DB backup.
2. Ensure APP_KEY is stable and stored in your secrets manager.
3. Run `php artisan passwordresets:hash-existing --dry` then without `--dry`.
4. Run `php artisan tokens:encrypt --dry` then without `--dry`.
5. Optionally rotate remember tokens: `php artisan tokens:rotate-remember` (this will log users out and force re-login).

Additional follow-ups
- Add scheduler job to rotate/expire tokens periodically if required.
- Add integration tests for authentication flows to detect regressions.
- Improve CI to run `php artisan migrate` against a fresh database to catch migration idempotency issues earlier.

APP_KEY rotation & re-encryption plan
------------------------------------

If you must rotate `APP_KEY` (for example, it's been leaked), follow this operational plan to avoid losing access to encrypted remember tokens:

1) PREPARE
	- Ensure you have a full DB backup and a copy of the current `APP_KEY` in a secure vault.
	- Put the site into maintenance mode: `php artisan down`.

2) RE-ENCRYPT APPROACH A (recommended when you can decrypt and re-encrypt in place):
	- On a maintenance machine, with the old `APP_KEY` available, run a script that:
	  a) Reads all rows with encrypted data (e.g., `remember_token`).
	  b) Decrypts with the old key and stores the plaintext in memory temporarily.
	  c) Switch environment to the new `APP_KEY` (or set new key in process) and re-encrypt each plaintext value.
	  d) Save re-encrypted value back to DB.
	- Validate a random sample of users can authenticate via the remember-me flow.

3) RE-ENCRYPT APPROACH B (if re-encryption in place is not possible):
	- Accept that encrypted values will be unreadable after rotation. In this case:
	  a) Rotate `APP_KEY`.
	  b) Force a logout for all users by rotating `remember_token` values (we already have `tokens:rotate-remember`).
	  c) Notify users to log in again.

4) ROLLBACK
	- If something goes wrong and you need to roll back, stop and restore DB from backup and the old `APP_KEY` from the vault.

Notes:
	- Never commit `APP_KEY` to git.
	- Test the entire rotation process in a staging environment first.

Composer audit & static analysis
--------------------------------

- I ran `composer audit` locally; no advisories were found for the current installed dependencies.
- Installing `phpstan` in this codebase bumped into dependency constraints (some packages lock transitive dependencies). I recommend adding `phpstan` in CI after reviewing compatible versions, or running it in a separate job/container where versions can be upgraded safely.

Recommendations summary
-----------------------
- Back up `APP_KEY` and DB now.
- Add phpstan (or similar SAST) to CI after resolving dependency constraints.
- Run composer audit regularly and subscribe to security advisories for critical packages.


