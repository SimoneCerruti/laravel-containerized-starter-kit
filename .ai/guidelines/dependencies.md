# Dependencies

- Change the application's dependencies only with the user's approval.
- Pull external packages whenever an issue that has already been solved by a package occurs. Do so only when the implementation cost (time, difficulty, complexity, maintenance) would be higher than writing that feature from scratch. Some smaller tasks, or some deeply custom ones, may not benefit form an extra dependency.

Before requiring an external package, check all the following:

- **Maintained**: REQUIRED. Commits in the last ~2 months, issues/PRs not stale. Inspect the repo via `gh repo view <owner>/<repo> --json pushedAt,updatedAt` and `gh api repos/<owner>/<repo>/commits` for recent activity, `gh api repos/<owner>/<repo>/contents <test-dir>` to confirm a real test suite exists.
- **Tested**: REQUIRED. A test suite present in the repo source code in the source control platform (eg GitHub). Look for the `tests` folder.
- **Adopted**: REQUIRED. 250+ GitHub stars or 10k+ Packagist downloads. If not met, check if the package is Well-Reputed. Check stars via `gh repo view <owner>/<repo> --json stargazerCount`; Packagist downloads from the package page.
- **Well-Reputed**: PREFERRED, NOT REQUIRED. Realized by a known creator. Check the author's GitHub (or similar) and perform an online search.
- **Secure**: REQUIRED. Has no security vulnerabilities known.
- When pulling a package or a library (eg: composer, npm, Dockerfile, github workflow, ...), always pin a secure, maintained and updated version. Never trust `latest`. Periodically, remind the user to bump those dependency: renovate is installed for that purpose.
