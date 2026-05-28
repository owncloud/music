# agents.md -- Music

## Repository Overview

Full-featured music player and streaming server app for ownCloud. Licensed under AGPL-3.0. PHP backend with JavaScript frontend. The ownCloud version is in maintenance mode (bug/security fixes only).

## Architecture & Key Paths

- `lib/` -- PHP application logic
- `js/` -- Frontend JavaScript
- `css/` -- Stylesheets
- `templates/` -- Server-side templates
- `appinfo/` -- ownCloud app metadata
- `l10n/` -- Translation files
- `3rdparty/` -- Bundled third-party libraries
- `tests/` -- Unit tests
- `img/` -- Images and logos
- `build/` -- Build scripts
- `Makefile` -- (not present, uses npm scripts)
- `composer.json` -- PHP dependencies
- `package.json` -- Node.js dependencies
- `webpack.config.js` -- Webpack configuration

## Development Conventions

- PHP backend follows ownCloud coding standards
- JavaScript frontend built with webpack
- Static analysis with PHPStan
- Scrutinizer CI for code quality

## Build & Test Commands

```bash
npm install                   # Install Node.js dependencies
composer install              # Install PHP dependencies
# Build and test through ownCloud app framework
```

## Important Constraints

- Licensed under AGPL-3.0 (copyleft). Apache 2.0 migration planned.
- In maintenance mode -- only bug and security fixes.
- All contributions require a DCO sign-off.


## OSPO Policy Constraints

### GitHub Actions
- **Only** use actions owned by `owncloud`, created by GitHub (`actions/*`), verified on the GitHub Marketplace, or verified by the ownCloud Maintainers.
- Pin all actions to their full commit SHA (not tags): `uses: actions/checkout@<SHA> # vX.Y.Z`
- Never introduce actions from unverified third parties.

### Dependency Management
- Dependabot is configured for automated dependency updates.
- Review and merge Dependabot PRs as part of regular maintenance.
- Do not introduce new dependencies without discussion in an issue first.

### Git Workflow
- **Rebase policy**: Always rebase; never create merge commits. Use `git pull --rebase` and `git rebase` before pushing.
- **Signed commits**: All commits **must** be PGP/GPG signed (`git commit -S -s`).
- **DCO sign-off**: Every commit needs a `Signed-off-by` line (`git commit -s`).
- **Conventional Commits & Squash Merge**: Use the [Conventional Commits](https://www.conventionalcommits.org/) format where the repository enforces it. Many repos use squash merge, where the PR title becomes the commit message on the default branch — apply Conventional Commits format to PR titles as well. A reusable GitHub Actions workflow enforces this.

## Context for AI Agents

This is a classic OC10 app with Ampache and Subsonic API compatibility. The app scans audio files in user storage and provides web-based playback. The Nextcloud version has moved to a separate repository. Changes should be limited to bug/security fixes.
