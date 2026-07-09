# Boost Agent Skills for Lunar Storefront — Design

**Date:** 2026-07-09
**Status:** Approved

## Goal

Ship publishable AI agent skills with the `lunarstorefront/storefront` composer package so that consumer applications running Laravel Boost v2 automatically receive on-demand, domain-specific guidance for building storefronts with this package.

## Background

Laravel Boost v2 discovers third-party package skills from `resources/boost/skills/{skill-name}/SKILL.md` inside installed **composer** packages. When a consumer runs `php artisan boost:install` (or `boost:update --discover`), Boost offers to install these skills and syncs them across all of the consumer's configured agents (Claude Code, Cursor, Codex, etc.). Boost also aggregates an always-loaded guideline from `resources/boost/guidelines/core.blade.php`.

Boost does **not** scan npm packages. The companion frontend packages (`@lunarstorefront/core`, `@lunarstorefront/vue3` in the storefront-ui repo) therefore cannot self-publish skills; their skill ships in this composer package, which is the anchor dependency.

## Decisions

| Decision | Choice |
| --- | --- |
| Format | Boost v2 package skills (Agent Skills format: SKILL.md + YAML frontmatter) |
| Granularity | One skill per domain |
| v1 domains | catalog, pages, auth-account, ui-vue (checkout deferred — checkout-session rework in flight) |
| Upfront guideline | Yes — thin `core.blade.php` overview |
| UI skills location | This composer package (only auto-discovery path Boost supports) |
| Naming | `storefront-*` prefix (avoids collision with existing `lunar-*` Lunar core skills) |

## File Layout

```
resources/boost/
├── guidelines/
│   └── core.blade.php
└── skills/
    ├── storefront-catalog/
    │   └── SKILL.md
    ├── storefront-pages/
    │   └── SKILL.md
    ├── storefront-auth-account/
    │   └── SKILL.md
    └── storefront-ui-vue/
        └── SKILL.md
```

No service provider changes and no `vendor:publish` tag are required — Boost discovers the files by convention. A skill may add a `references/` subdirectory if its content exceeds roughly 150 lines; the SKILL.md then acts as an entry point that tells the agent when to read deeper.

## Skill Content

Every SKILL.md has frontmatter with `name` (matching its folder) and `description` written as an activation trigger (what tasks should cause an agent to load it), followed by a "When to use this skill" section, package conventions, and real code snippets derived from the package source.

### storefront-catalog

Trigger: product, collection, brand, search, or pricing work in a consumer app.

- Managers: `ProductManager`, `CollectionManager`, `BrandManager`, `SearchManager`, `PricingManager`, `VariantManager` — resolution via facades/helpers.
- Actions: `GetProductBySlug`, `GetCollectionBySlug`, `GetCollectionTree` (accepts eager loads), `GetCollectionBreadcrumbs`, `GetProductOptions`, `GetProductOptionPermutations`, `GetProductVariantByProvidedOptions`, `GetProductAssociations`, `SearchProducts`, `GetQuantifiedPrice`, `MapProductPriceBreaks`.
- Patterns: building product listing pages, product detail pages, option selection → variant resolution, search with query suggestions.

### storefront-pages

Trigger: adding storefront routes or pages, shaping Inertia props.

- `RouteRegistrar` granular route registration; `routes/catalog.php` opt-in.
- `StorefrontPage` and `PropData`/`PropManager` prop composition.
- Data DTOs (spatie/laravel-data), transformers, and the TypeScript transformer that generates frontend types.
- Inertia page wiring conventions.

### storefront-auth-account

Trigger: customer login, registration, or account area work.

- Auth and Account controllers and their routes.
- `Customer` data object, order history patterns.

### storefront-ui-vue

Trigger: frontend component work consuming storefront server props.

- `@lunarstorefront/core` and `@lunarstorefront/vue3` usage.
- Mapping server DTO types to components and composables.
- Content is written against the current published npm package APIs; version skew between the composer package and npm packages is accepted as a known limitation for v1.

### Content constraints

- No Stripe references anywhere — the storefront is moving to a checkout-session approach; checkout content is excluded from v1 entirely.
- Snippets must reflect actual package source (verified against `src/` at authoring time), not aspirational APIs.

## Guideline (core.blade.php)

Roughly 30 lines, always loaded upfront by Boost in consumer apps:

- One-paragraph description of the package (Inertia-based storefront layer for Lunar).
- Key architectural facts: data flows through spatie/laravel-data DTOs, domain managers, granular `RouteRegistrar`.
- Instruction to activate the `storefront-*` skills for domain-specific work.

## Verification

A Pest test (e.g. `tests/BoostResourcesTest.php`) asserting for each `resources/boost/skills/*/SKILL.md`:

- The file exists and its YAML frontmatter parses.
- `name` is present and matches its folder name.
- `description` is present and non-empty.

This guards against a malformed skill silently failing to install in consumer apps.

## Dependency Change

Bump `laravel/boost` in `require-dev` from `^1.8` to `^2.0`. Not required for consumers (discovery runs from the consumer's Boost installation), but keeps local testing against the actual skills-aware version.

## Out of Scope (v1)

- `storefront-checkout` skill — deferred until the checkout-session rework lands.
- Publishing skills from the storefront-ui npm repo (`boost:add-skill` route) — may revisit if the UI packages gain standalone use.
- Blade-conditional skill content keyed off detected package versions.
