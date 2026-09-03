# HubGo — Shipping Management for WooCommerce

HubGo is a WordPress plugin that extends WooCommerce with everything that happens
around shipping: a product-page shipping calculator, order tracking, an "Order
shipped" status with its own e-mail, a delivery promise stored on the order, and
integrations with the shipping and automation plugins a store already uses.

Since **3.0.0** the plugin is API-first: the admin UI is a Vue 3 SPA that talks
exclusively to the `hubgo/v1` REST namespace, and the storefront calculator is a
Vue bundle of its own.

- **Author:** [MeuMouse.com](https://meumouse.com/)
- **Plugin page:** https://meumouse.com/plugins/hubgo/
- **License:** GPLv2 or later — see [license.md](license.md)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)
- **Contributor guidelines:** [AGENTS.md](AGENTS.md)

---

## Table of contents

1. [Requirements](#1-requirements)
2. [Features](#2-features)
3. [Installation](#3-installation)
4. [Admin screens](#4-admin-screens)
5. [Storefront usage](#5-storefront-usage)
6. [Integrations](#6-integrations)
7. [Licensing and updates](#7-licensing-and-updates)
8. [Architecture](#8-architecture)
9. [REST API](#9-rest-api)
10. [Extensibility](#10-extensibility)
11. [Development](#11-development)
12. [Internationalization](#12-internationalization)
13. [Versioning and releases](#13-versioning-and-releases)
14. [Support](#14-support)

---

## 1. Requirements

| Requirement | Version |
| --- | --- |
| PHP | 7.4 or higher |
| WordPress | Tested up to 7.1 |
| WooCommerce | 6.0 or higher is enforced at runtime; the plugin header declares 9.0+ and is tested up to 11.0 |
| HPOS (custom order tables) | Compatible |

The plugin checks PHP and WooCommerce on `plugins_loaded` and, when something is
missing, renders an admin notice instead of booting its components. The
MDS registration is wired outside that gate, so updates keep working even without
WooCommerce.

For development you also need **Node.js 18+** (Vite build and translation
tooling) and **Composer** (PHP autoload and the MDS SDK).

---

## 2. Features

### Shipping calculator on the product page

- Vue 3 storefront app rendered by a product-page hook, a shortcode or an
  Elementor widget — all three print the same mount node, so they can never
  drift apart.
- Delivery-date forecast: the carrier's estimate in business days plus the
  store's handling days, resolved into a real date ("Get it by …").
- Free-shipping badge driven by a configurable threshold.
- Preferred-method choice that is carried into the cart and the checkout.
- Optional "I do not know my postcode" finder, backed by the Google Maps
  integration when it is enabled.
- Automatic calculation for customers whose postcode is already known
  (`enable_auto_shipping_calculator`).
- The calculation is **side-effect free**: it never writes to the cart, the
  customer session or the rate cache, which keeps the endpoint public and page
  caches intact.

### Order tracking

- Tracking codes per order, with carrier and tracking URL.
- Admin metabox on the order screen.
- "My Account" view for the customer.
- Transactional e-mail carrying the tracking information.
- Read-only bridge to WooCommerce Shipment Tracking, plus a batched migration
  that imports its data into HubGo.

### "Order shipped" order status

- Custom WooCommerce status `wc-shipped-order`, listed next to Processing, with
  bulk actions and reports support.
- Dedicated WooCommerce e-mail (`hubgo_shipped_order`), overridable as a
  template.

### Delivery promise and late-delivery watch

- What the shopper was told at the checkout is stored on the order
  (`_hubgo_delivery_date`, `_hubgo_delivery_days`, `_hubgo_delivery_carrier`,
  `_hubgo_delivery_method`), on both the classic and the block checkout, so
  every downstream consumer states the promise instead of a fresh quote.
- A daily pass fires `Hubgo/Delivery/Overdue` once per order whose promised date
  has passed, in batches, with a grace period.

### Appearance and copy

- Every storefront string is a setting, so an untouched install still reads in
  the site language.
- The calculator is styled exclusively through `--hubgo-calc-*` CSS custom
  properties, driven by the Appearance tab and, per instance, by the Elementor
  widget.

---

## 3. Installation

### From the WordPress admin panel

1. Open your site's admin panel.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Select `hubgo-<version>.zip` and click **Install Now**.
4. Click **Activate Plugin**.

### Via FTP

1. Unzip `hubgo-<version>.zip` on your computer.
2. Connect to your server over FTP.
3. Upload the `hubgo` folder into `wp-content/plugins`.
4. Go to **Plugins → Installed Plugins** and activate **HubGo**.

### From source

The repository does not ship generated artifacts (`app/dist/`, `admin/vendor/`),
so a checkout has to be built before it runs:

```bash
npm install && npm run build
```

The result is `release/hubgo-<version>.zip`, which is the deliverable, plus the
staged tree in `release/hubgo/`. See [Development](#11-development).

---

## 4. Admin screens

HubGo owns a top-level **HubGo** menu with three subpages, each one a Vue bundle
of its own:

| Page | Slug | Bootstrap route |
| --- | --- | --- |
| Settings | `hubgo-settings` | `GET hubgo/v1/settings` |
| Integrations | `hubgo-integrations` | `GET hubgo/v1/integrations` |
| License | `hubgo-license` | `GET hubgo/v1/license` |

`hubgo-settings` doubles as the parent menu slug, so links published before the
3.0.0 restructure keep working.

The Settings screen has four tabs:

- **General** — features, calculator behaviour and placement, preferred method.
- **Appearance** — colors and typography, container, free-shipping badge,
  postcode field, calculate button, delivery options, details window, layout.
- **Texts** — every storefront string.
- **About** — maintenance preferences, system status snapshot and the
  restore-defaults action.

All settings live in a single option, `hubgo_settings`, and are described by a
server-side schema (sections → cards → fields). The SPA renders whatever the
schema declares, which is why adding a setting means editing the schema, not the
UI.

The default capability for every admin and REST surface is `manage_woocommerce`,
filterable through `Hubgo/Admin/Settings_Capability`.

---

## 5. Storefront usage

### Placement

The calculator's position is set in **General → Calculator**:

| Setting value | WordPress hook |
| --- | --- |
| `before_cart` | `woocommerce_before_add_to_cart_form` |
| `after_cart` (default) | `woocommerce_after_add_to_cart_form` |
| `meta_end` | `woocommerce_product_meta_end` |
| `shortcode` | nothing is hooked — place it yourself |
| `elementor` | nothing is hooked — use the widget |

The list is filterable via `Hubgo/Shipping_Calculator/Positions`.

### Shortcode

Registered whichever position is selected, as the escape hatch for themes none
of the hooks fit:

```
[hubgo_shipping_calculator]
```

### Elementor widget

The shipping calculator widget renders the same component and adds per-instance
style controls, built from the same token map the settings screen uses.

### DOM events

The storefront app publishes browser events other scripts can listen to:

- `hubgo:shipping_calculated`
- `hubgo:shipping_error`
- `hubgo:shipping_preference_changed`

### Template overrides

Templates live in `templates/` and are loaded with WooCommerce's template
loader, so a theme overrides them by copying the file into its own
`woocommerce/` folder:

```
templates/shipping-calculator.php
templates/email/hubgo-tracking-info.php
templates/emails/hubgo-shipped-order.php
templates/myaccount/hubgo-tracking-info.php
```

---

## 6. Integrations

The Integrations screen is a card catalog with a category filter, a settings
modal per integration and one-click install/activate for the plugins that are
distributed on wordpress.org.

| Integration | What it does |
| --- | --- |
| **Joinotify** | WhatsApp automation: five triggers (order shipped, tracking code saved or removed, delivery date promised, delivery late), 33 placeholders and the conditions to branch on them |
| **Melhor Envio** | Stores the carrier that actually moves the parcel and the delivery time as standard rate meta, and makes the official plugin quote correctly from the product page |
| **Frenet** | Recovers the delivery forecast and the carrier from the Frenet response and puts the promised date back on the calculator |
| **WooCommerce Shipment Tracking** | Read-only bridge for its tracking items, plus a batched migration into HubGo |
| **Google Maps** | Address lookup that powers the postcode finder and the street name shown on the quote |
| **Elementor** | Registers the shipping calculator widget |

Two rules govern this area:

- **Nothing in Core registers an address provider.** `Address_Service` publishes
  `Hubgo/Core/Address/Provider` with a `null` default, and the Google Maps
  integration answers it once its card is on and a key is saved — an install
  with the card off never reaches an external service.
- **A plugin HubGo installs is never patched.** Incompatibilities are fixed from
  HubGo's side, through whatever public surface the other plugin exposes.

---

## 7. Licensing and updates

Licensing, signed updates (ed25519), rollback and the update heartbeat are
handled by the **MDS PHP SDK** (`meumouse/mds-php-sdk` `^1.3`), installed with
Composer and registered by `Core\License` — there is no bespoke updater.

The update check is `POST /v2/update-check` on the MDS API, sent by the SDK with
the site `domain`, the `product_slug` (`hubgo`) and the installed
`current_version`; the license key travels with it while the update gate is
closed. What the answer may say about this site is two separate permissions,
not one:

- `License::allows_updates()` — MDS still announces new versions here.
- `License::allows_downloads()` — MDS still hands over the package itself.

They usually agree, and where they do not the update interface follows them
rather than `License::is_active()`: MDS can waive either gate for one license
(how a customer who bought before HubGo required a key keeps updating), and a
release can be announced to every site with the ZIP reserved for licensed ones —
which shows up as a new version with no "Update now".

The version metadata WordPress renders (`requires`, `tested`, `requires_php`)
comes from the same response, off the product registered on MDS; the plugin
header carries the matching values.

MDS credentials are compile-time constants, overridable from `wp-config.php`:

- `HUBGO_MDS_API_URL`
- `HUBGO_MDS_API_KEY`
- `HUBGO_MDS_PUBLIC_KEY`

**License activation is currently switched off** (`License::ENABLED`), and it
governs the key, not the product. HubGo is still registered with MDS and still
checks for updates — under the SDK's `updates_only` preset, which sends the
check with no `license_key` and lets MDS answer on the product's own gates. The
response is still verified against the ed25519 public key; no preset switches
that off.

What is gone while the switch is off: the License screen and its Vue bundle,
the `hubgo/v1/license/*` routes, the license heartbeat, rollback, the SDK
notices, and every Pro gate — `License::is_active()` answers `true`, so nothing
locks itself behind a key. Nothing was removed from the codebase: flip the
constant, or define it in `wp-config.php`, and the activation flow comes back
whole.

```php
define( 'HUBGO_LICENSE_ENABLED', true );
```

This requires MDS to agree. With no key on the wire, `/v2/update-check` only
answers for a product whose update gate is open on the server.

The plugins list carries a **Check for updates** link either way, forcing a
fresh MDS check over `POST hubgo/v1/updates/check`.

---

## 8. Architecture

```
hubgo.php                   Plugin bootstrap: header, autoload, MDS SDK, Plugin::init()
admin/                      All PHP backend code + Composer (PSR-4: MeuMouse\Hubgo\ => admin/src/)
  src/Admin/                Admin screens, settings schema, persistence, system status
  src/API/                  REST layer (namespace hubgo/v1), one class per endpoint
  src/Core/                 Bootstrap, assets, licensing, address lookup, domain services
  src/Emails/               WooCommerce e-mail classes
  src/Integrations/         Integration registry + one class per third-party plugin
  src/Views/                Storefront/admin rendering and calculator styling
app/                        Vue 3 + Vite apps (admin SPA + storefront)
  src/entries/              One entry per bundle (settings, integrations, license, storefront)
  src/pages/                Admin page components
  src/components/           Shared admin UI (fields, cards, modals, toasts, layout)
  src/storefront/           Storefront calculator components, tokens and styles
  src/utils/                REST client, i18n proxy, mount helper
assets/                     Non-bundled admin CSS/JS and brand SVGs
templates/                  Overridable WooCommerce templates
languages/                  .pot/.po/.mo/.l10n.php + the translation CLI tooling
scripts/build.mjs           Release pipeline (Vite + Composer + translations + zip)
release/                    Build output (git-ignored)
```

Design points worth knowing before reading the code:

- **Lazy bootstrap.** `Plugin::init()` is the only entry point. A hook → classes
  map decides when each component is instantiated, behind the dependency gate.
- **API-first.** No `admin-ajax.php` handlers and no server-rendered settings
  form; the PHP page prints a mount node and a skeleton, everything else arrives
  over REST.
- **Schema-driven settings.** One option, one write path, sanitization by field
  type. Every screen POSTs its full settings map.
- **Generated artifacts are not committed.** `app/dist/` and `admin/vendor/` are
  produced by the build, and the packaging step refuses to zip when the Vite
  manifest or the Composer autoload is missing.

[AGENTS.md](AGENTS.md) documents the conventions in full: PHP style, docblocks,
security rules, the Vue and Tailwind rules, the field registry, motion tokens
and the integration/migration contracts.

---

## 9. REST API

Namespace: **`hubgo/v1`**. Every response has the shape
`{ status: 'success' | 'error', … }`. Admin routes require the
`manage_woocommerce` capability and the `wp_rest` nonce in `X-WP-Nonce`; the
calculator and the address routes are public by necessity and protected by
caching, a per-visitor rate limit and a store-wide daily ceiling.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/settings` | Bootstrap payload for the Settings screen (schema + values) |
| `POST` | `/settings` | Persist the full settings map |
| `POST` | `/settings/reset` | Restore defaults |
| `GET` | `/integrations` | Bootstrap payload for the Integrations screen |
| `GET` | `/providers` | Shipping providers/carriers list |
| `POST` | `/plugins/install` | Install and activate an integration's plugin |
| `POST` | `/migrations/run` | Process one migration batch |
| `POST` | `/shipping/calculate` | Quote a product (public) |
| `GET` | `/address/autocomplete` | Postcode finder suggestions (public) |
| `GET` | `/address/resolve` | Resolve a suggestion into an address (public) |
| `GET` | `/tracking` | Read the tracking items of an order |
| `POST` | `/tracking` | Create a tracking item |
| `DELETE` | `/tracking/<id>` | Delete a tracking item |
| `GET` | `/license` | Bootstrap payload for the License screen |
| `POST` | `/license/activate` | Activate a key |
| `POST` | `/license/sync` | Revalidate the license |
| `POST` | `/license/deactivate` | Deactivate this site |
| `POST` | `/updates/check` | Force a fresh MDS update check |

The `/license*` routes are only registered while license activation is on; the
update route is always there — see [Licensing and updates](#7-licensing-and-updates).

---

## 10. Extensibility

### Hooks

Naming convention: **`Hubgo/Namespace/Thing`** — slash-separated, PascalCase
segments mirroring the class path. Published hooks are never renamed or removed.

Frequently used ones:

| Hook | Purpose |
| --- | --- |
| `Hubgo/Before_Init`, `Hubgo/After_Init` | Plugin bootstrap |
| `Hubgo/API/Routes` | Register or replace REST routes |
| `Hubgo/Admin/Settings/Schema` | Add sections, cards or fields |
| `Hubgo/Admin/Settings/Bootstrap_Data` | Extend the Settings bootstrap payload |
| `Hubgo/Integrations/Registered` | Register an integration class |
| `Hubgo/Integrations/Cards` | Filter the integration catalog |
| `Hubgo/Shipping_Calculator/Package` | Reshape the package before quoting |
| `Hubgo/Shipping_Calculator/Rates` | Filter the rates returned to the storefront |
| `Hubgo/Shipping_Calculator/Positions` | Add a product-page placement |
| `Hubgo/Shipping_Calculator/Delivery_Meta_Keys` | Where the carrier's forecast is read from |
| `Hubgo/Core/Address/Provider` | Supply the address lookup provider |
| `Hubgo/Tracking/Get_Items` | Inject tracking items owned by another plugin (display only) |
| `Hubgo/Tracking/Item_Saved`, `Hubgo/Tracking/Order_Shipped` | Tracking lifecycle |
| `Hubgo/Delivery/Promise_Saved`, `Hubgo/Delivery/Overdue` | Delivery promise lifecycle |
| `Hubgo/Migrations/Registered` | Register a data migration |

[AGENTS.md](AGENTS.md) carries the complete list.

### Adding an integration

Extend `Integrations\Integrations_Base`, register the card **first** (so it is
listed even when the host plugin is missing), then bail out when the dependency
or the toggle is off, and register the class in `Hubgo/Integrations/Registered`.

### Adding a field type

1. Create the component under `app/src/components/fields/`.
2. Register it in `fieldRegistry.js`.
3. Add the field builder in PHP `Settings\Registry` and a sanitizer case in
   `Settings\Repository`.

Registered types: `toggle`, `text`, `textarea`, `select`, `color`, `number`,
`range`, `dimension`, `password`. The registry is exposed as
`window.HubgoFieldComponents` and announces `hubgo:field-registry-ready`, so
external bundles can register or override widgets.

---

## 11. Development

Run from the plugin root.

```bash
npm run build
```

Full release pipeline: Vite build → Composer install `--no-dev` → refresh the
`.pot` → compile `.mo` / `.l10n.php` → stage the runtime files into
`release/hubgo/` → zip them into `release/hubgo-<version>.zip`.

```bash
npm run dev
```

Vite dev server for the SPA.

```bash
npm run build:app
```

Frontend only, without packaging.

```bash
npm run build:fast
```

Re-stage and re-zip from the artifacts already on disk.

```bash
npm run build:translate
```

Full build plus AI re-translation of every locale (requires API keys in
`languages/.env`).

Useful `scripts/build.mjs` flags: `--skip-app`, `--skip-composer`,
`--skip-translations`, `--translate`, `--engine=google|openai`, `--no-install`,
`--no-zip`.

There is **no automated test suite and no linter configured**. Correctness is
verified by reading the code and by manual testing in a WordPress install.

---

## 12. Internationalization

- Text domain: **`hubgo`**, loaded from `/languages` on `init` at priority `0`.
- **Source strings are written in en-US** and translated outward from there.
  Code, identifiers, comments, docblocks, commit messages, the changelog and
  this documentation are English; Portuguese lives only in the `pt_BR` /
  `pt_PT` catalogs.
- Shipped locales: `en_US`, `es_ES`, `pt_BR`, `pt_PT`, `de_DE`, `fr_FR`,
  `it_IT`.
- Never call a translation function before `init` — the text domain is not
  loaded yet and WordPress 6.7+ warns about it.
- After adding strings, run `npm run pot` (or a full build) and commit the
  regenerated `languages/` artifacts.

Translation sub-commands: `npm run pot`, `npm run translate:ai`,
`npm run compile:translations`. See [languages/README.md](languages/README.md).

---

## 13. Versioning and releases

HubGo follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The
version appears in four places and they must stay in sync:

1. The `Version:` header in `hubgo.php`
2. `$plugin_version` in `hubgo.php`
3. `package.json`
4. `app/package.json`

[CHANGELOG.md](CHANGELOG.md) follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), is written in en-US,
and ships inside the zip — it is read by store owners, so it describes what
changed for them.

---

## 14. Support

- Documentation and support: https://meumouse.com/plugins/hubgo/
- Contributing to this repository: read [AGENTS.md](AGENTS.md) first — it is the
  single source of truth for the project's conventions.

HubGo is released under the GPLv2 (or later) license. See [license.md](license.md).
