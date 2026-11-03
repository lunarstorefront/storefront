---
name: storefront-upgrades
description: Take package updates in a storefront app built on lunarstorefront/storefront and @lunarstorefront/vue3, and review dependency bump PRs. Covers force-publishing package assets, regenerating TypeScript types, keeping storefront and storefront-ui in step, panel lockfile drift, and version constraints. Use when running composer or npm updates, reviewing a Renovate or Dependabot PR, or pinning a package version in a storefront.
---

# Upgrading Storefront Packages

## When to use this skill

Use when a store takes new versions of its packages: `lunarphp/*`, `lunarstorefront/storefront`, `storefront-ui` (`@lunarstorefront/vue3`), `lunarphp/checkout`, or panel addons. Fixes only reach a store through these updates, so they should be routine and low risk.

## After every composer update

Run these, in order, and commit what they change:

1. **Force-publish package assets.** Checkout serves its prebuilt bundle from `public/vendor/lunarphp/checkout`, and panel addons serve theirs from `public/vendor`. A stale published copy means the store deploys the previous version's JavaScript. Keep this in a composer script that runs on `post-install-cmd` and `post-update-cmd`:

   ```json
   "publish-assets": [
       "@php artisan vendor:publish --tag=panel-all-assets --ansi --force",
       "@php artisan vendor:publish --tag=lunar-checkout-assets --ansi --force"
   ]
   ```

   Stores that list each addon's tag (`panel-assets`, `lunar-cms-panel-assets`, `storefront-cache-panel-assets`, ...) must add a line for every new addon. `panel-all-assets` covers them all. Gitignore the published directories.

2. **Regenerate TypeScript types:** `php artisan typescript:transform`. The storefront Data DTOs are the contract with the frontend; a changed DTO that isn't regenerated compiles but sends props the pages don't expect.

3. **Refresh the npm lockfile.** The panel npm packages are `file:` dependencies into `vendor/lunarphp/...`. When a composer bump changes their version, `package-lock.json` is stale and `npm ci` fails. Run `npm install --package-lock-only` and commit the lockfile with the composer change.

4. **Type-check and build:** `vue-tsc --noEmit` (or the store's `typecheck` script), then `npm run build`, then the test suite.

## storefront and storefront-ui move together

The PHP Data DTOs in `lunarstorefront/storefront` and the types in `@lunarstorefront/vue3` describe the same data. Upgrade them in the same change, and check the package release notes for the version pairing. Group them in the dependency bot config so one PR bumps both:

```json
{
  "packageRules": [
    {
      "matchPackageNames": ["lunarstorefront/storefront", "storefront-ui"],
      "groupName": "storefront"
    }
  ]
}
```

`storefront-ui` is installed from git. Pin it to an exact release tag (`#v2.1.0`), not a commit SHA or branch, so the bot can see and bump new tags. If the store's Vite, `tsconfig` or Vitest config can point at a local sibling checkout for package development, that switch must be off by default (behind an env flag), or local builds silently ignore the pinned version.

## Version constraints

- Prefer caret ranges on tags (`^2.0`) so patch and minor releases arrive automatically.
- A pin to a package branch (`dev-main`, `dev-fix/...`) or a commit SHA stops fixes arriving and makes builds change without a version bump. Treat any such pin as temporary: note why, and move back to a tag once the fix is released.
- Never delete a package branch that a store pins. The store's next `composer install` fails.
- A breaking change to a storefront DTO or its TypeScript type is a major version. Read the release notes before taking a major.

## Reviewing a dependency bump PR

1. Read the release notes for each bumped package, especially majors and anything marked security.
2. Check the PR includes the regenerated types, the refreshed `package-lock.json`, and that `storefront` and `storefront-ui` moved together.
3. Check CI ran the type-check, build and tests, plus `composer audit` and `npm audit`.
4. Smoke the pages most likely affected: product, listing, cart, checkout, account.
5. Merge security releases promptly. They are the reason this path exists.
