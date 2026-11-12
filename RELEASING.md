# Releasing plugin updates

WordPress updates are sourced only from published GitHub Releases. A commit or branch push by itself is intentionally not offered to WordPress sites as an update.

## Build an installable ZIP locally

From the repository root, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-plugin.ps1
```

The resulting `dist/southland-residential-calculator.zip` contains one top-level `southland-residential-calculator` directory and can be installed through **Plugins > Add New > Upload Plugin**.

## Publish an update

1. Update the version in all three locations:
   - the `Version` plugin header in `southland-residential-calculator.php`;
   - the `SRC_VERSION` constant in the same file;
   - `Stable tag` in `readme.txt`.
2. Add the new version to the changelog in `readme.txt`.
3. Commit and push the changes.
4. Create and push a tag matching the version, for example `v1.0.3`.

The `Build plugin release` GitHub Actions workflow validates PHP syntax and version synchronization, creates the correctly rooted ZIP, and publishes it as a GitHub Release asset named `southland-residential-calculator.zip`. Installed sites will then find it during WordPress's normal update checks and can install it with the standard **Update now** button.

## Private repository authentication

Public repositories require no token. For a private repository, define a read-only GitHub token outside this plugin's files, typically in `wp-config.php`:

```php
define( 'PROPERTY_TAX_ESTIMATOR_GITHUB_TOKEN', 'github-token-with-read-access' );
```

Do not commit the token to this repository. Fine-grained tokens should have the minimum repository read permission needed to access releases. Each WordPress installation that updates from a private repository needs access to the token.
