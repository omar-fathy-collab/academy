I removed `full laravel.sql` from the working tree because it contained sensitive data (email addresses, password hashes, and possibly API keys).

What I did:
- Deleted `full laravel.sql` from the repository tip.
- Added `*.sql` to `.gitignore` to avoid future accidental commits of SQL dumps.

Next steps:
1. Trigger the repository secrets CI scan (it's already pushed on branch `ci/trigger-secret-scan`).
2. If the CI scan finds secrets, rotate them immediately (database passwords, API keys, OAuth tokens).
3. After rotation, rewrite history to remove the leaked file permanently (use git-filter-repo or BFG) and force-push with team coordination.

Note: Deleting the file from the tip does not remove it from git history. Do not rewrite history until you have rotated any real credentials found in the dump.
