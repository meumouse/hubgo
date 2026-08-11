# AGENTS.md — HubGo

Project guidelines for AI agents and human contributors working on **HubGo — Gerenciamento de Frete para WooCommerce** (MeuMouse.com).

This file is the single source of truth for conventions in this repository. Read it before touching code.

---

## 1. What this project is

HubGo is a WordPress plugin that extends WooCommerce with shipping features:

- **Shipping calculator** on the product page: a Vue 3 storefront app (since 3.0.0) with a delivery-date forecast, a free-shipping badge, and a preferred-method choice that is carried into the checkout.
- **Order tracking**: tracking codes per order, admin metabox, "My Account" view, and transactional e-mails.
- **"Order shipped" status**: a custom WooCommerce order status with bulk actions and e-mail notification.
- **Integrations**: Joinotify (WhatsApp automation), Melhor Envio, Frenet, WooCommerce Shipment Tracking (read-only bridge + data migration), Google Maps (address lookup for the calculator) and Elementor (licensed calculator widget), exposed as a card catalog on a dedicated admin screen.
- **Licensing & updates**: handled by the MDS PHP SDK (Modular Distribution Service), not by a bespoke updater.

Since **3.0.0** the plugin is **API-first**: the admin UI is a Vue 3 SPA that talks exclusively to the `hubgo/v1` REST namespace. There is no `admin-ajax.php` usage and no server-rendered settings form.

The plugin owns a **top-level admin menu** with three subpages, one Vite entry each:

| Page | Slug | Entry | Bootstrap route |
| --- | --- | --- | --- |
| Configurações | `hubgo-settings` | `src/entries/settings.js` | `GET hubgo/v1/settings` |
| Integrações | `hubgo-integrations` | `src/entries/integrations.js` | `GET hubgo/v1/integrations` |
| Licença | `hubgo-license` | `src/entries/license.js` | `GET hubgo/v1/license` |

`hubgo-settings` doubles as the parent menu slug so links published before the restructure keep working. The MDS SDK is registered with `settings_parent => null`, which stops it from adding a submenu of its own — the license screen is HubGo's Vue page, driven by the `hubgo/v1/license/*` routes over the SDK's license manager.

The settings screen has four tabs: **Geral**, **Aparência**, **Textos** and **Sobre**.

**Requirements:** PHP >= 7.4, WordPress tested up to 6.9.4, WooCommerce >= 6.0 (tested up to 10.6.0). HPOS (custom order tables) compatible.

---

## 2. Repository layout

```
hubgo.php                   Plugin bootstrap: header, autoload, MDS SDK, Plugin::init()
admin/                      All PHP backend code + Composer
  composer.json             PSR-4: MeuMouse\Hubgo\ => admin/src/
  src/
    Admin/                  Admin screens, settings schema and persistence
      Settings/Registry.php     Schema (sections -> cards -> fields) + bootstrap payload
      Settings/Repository.php   Read/write + type-based sanitization of `hubgo_settings`
      Settings.php              Thin static read/write facade (legacy-compatible)
      Default_Options.php       Default values for every setting key
      Menu.php                  Top-level HubGo menu + the three SPA subpages
      System_Status.php         Environment snapshot for the "Sobre" tab
      Order_Tracking_Meta_Box.php
    API/                    REST layer (namespace hubgo/v1)
      Abstract_Route.php        Base class for every endpoint
      Rest_Controller.php       Instantiates all routes (filterable)
      Routes/                   One class per endpoint
    Core/                   Plugin bootstrap, assets, licensing, domain services
      Address/                  Address lookup: Address_Service (facade, cache,
                                ceilings), Address_Provider (base),
                                Google_Maps_Provider
      Plugin.php                Singleton, constants, dependency gate, lazy class loading
      License.php               MDS SDK registration (licensing + signed updates)
      Update_Checker.php        "Check for updates" link on the plugins list (forced MDS check)
      Assets.php                Enqueues storefront + admin assets (page -> entry map)
      Scripts.php               Resolves the Vite manifest into URLs/versions
      Plugin_Installer.php      One-click install/activate for integration plugins
      Abstract_Migration.php    Batching/progress base for data migrations
      Migration_Registry.php    Registered migrations (filterable)
      Woo_Shipment_Tracking_Migration.php
      Delivery_Estimate.php     Carrier forecast -> business-day delivery date
      Free_Shipping_Context.php Threshold advertised by the storefront badge
      Shipping_Preference.php   Preferred method cookie -> cart/checkout selection
      Tracking_Manager.php, Order_Status.php, Providers_Registry.php,
      Shipping_Calculator_Service.php
    Emails/                 WooCommerce e-mail classes
    Integrations/           Integrations_Base (card catalog) + Integration_Registry
                            + one class per third-party plugin
      Widgets/                  Elementor widget classes
    Views/                  Storefront/admin rendering
      Shipping_Calculator.php   Mount node + per-instance config (hook/shortcode/Elementor)
      Calculator_Styles.php     Setting -> CSS custom property map + wp_head block
      Custom_Colors.php         Deprecated 3.0.0, kept for its published filters
  vendor/                   Composer output (git-ignored, generated by the build)
app/                        Vue 3 + Vite apps (admin SPA + storefront)
  src/entries/              One entry per bundle (settings, integrations, license, storefront)
  src/pages/                Admin page components (+ page-local components/)
  src/components/           Shared admin UI: fields, toggles, buttons, cards, modals,
                            toasts, skeletons, layout, brand
  src/storefront/           Storefront calculator: components/, tokens.js, api.js,
                            useShippingQuote.js, styles/calculator.css
  src/utils/                api.js (REST client), i18n.js, bootstrap.js (mountPage)
  src/styles/main.css       Tailwind entry + design tokens, scoped to .hubgo-app
  dist/                     Vite build output — generated, git-ignored
assets/                     Non-bundled static assets
  admin/                    Order tracking metabox + plugins-list update check CSS/JS (vanilla)
  brand/                    SVG logos
templates/                  Overridable WooCommerce templates (shipping-calculator.php,
                            email/, emails/, myaccount/)
languages/                  .pot/.po/.mo/.l10n.php + the AI translation CLI tooling
scripts/build.mjs           Release pipeline (Vite + Composer + translations + zip)
release/                    Build output — staged tree + hubgo-<version>.zip (git-ignored)
dist/                       Legacy release artifacts kept from before 3.0.0
```

**Rule:** all PHP lives under `admin/src/`. Do not create PHP source files at the plugin root or under `app/`.

---

## 3. Commands

Run from the plugin root unless stated otherwise.

```bash
npm run build
```

Full release pipeline: Vite build (`app/`) → Composer install `--no-dev` (`admin/`) → refresh `.pot` → compile `.mo` / `.l10n.php` → stage the runtime files into `release/hubgo/` → zip them into `release/hubgo-<version>.zip`.

The zip is the deliverable. It carries only what WordPress needs at runtime: `hubgo.php`, `admin/src`, `admin/vendor`, `app/dist`, `assets`, `templates`, the compiled `languages` artifacts and the three top-level documents. `AGENTS.md`, `CLAUDE.md`, `package.json` and the Node tooling stay out.

```bash
npm run build:fast
```

Re-stage and re-zip from the artifacts already on disk (`--skip-app --skip-composer --skip-translations --no-install`). Use it when only the packaging changed.

```bash
npm run dev
```

Vite dev server for the SPA (`app/`).

```bash
npm run build:app
```

Frontend only, without packaging.

```bash
npm run build:translate
```

Full build **plus** AI re-translation of every locale (requires API keys in `languages/.env`).

Useful `scripts/build.mjs` flags: `--skip-app`, `--skip-composer`, `--skip-translations`, `--translate`, `--engine=google|openai`, `--no-install`, `--no-zip`.

`assertBuildArtifacts()` refuses to package when `app/dist/.vite/manifest.json` or `admin/vendor/autoload.php` is missing, or when the manifest lacks one of the declared Vite entries. That check is what makes the generated-not-committed model safe: without it, a `--skip-app` on a clean checkout produces a zip that installs fine and then has no UI at all. **Add a new Vite entry to the list in that function when you add one to `vite.config.js`.**

Translation sub-commands (from root): `npm run pot`, `npm run translate:ai`, `npm run compile:translations`. See `languages/README.md`.

There is **no automated test suite and no linter configured**. Correctness is verified by reading the code and by manual testing in a WordPress install. Do not add a test framework or lint config unless explicitly asked.

---

## 4. PHP conventions

Follow the existing style exactly — it is consistent across the codebase and is closer to WordPress Core style than to PSR-12.

### File and class structure

```php
<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;

use WP_REST_Request;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Short one-line description.
 *
 * Longer explanation when the class does something non-obvious.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Example {
```

- One class per file. **File name matches the class name**, `Snake_Case` with capitals: `Tracking_Manager.php`, `Abstract_Route.php`, `Order_Tracking_Meta_Box.php`.
- Namespace mirrors the directory under `admin/src/`: `MeuMouse\Hubgo\API\Routes`.
- Every file starts with `defined('ABSPATH') || exit;` after the `use` block.
- Group `use` statements: project classes first, then vendor/global classes, separated by a blank line.

### Naming

- Classes: `Snake_Case` with initial capitals (`Providers_Registry`, `Email_Shipped_Order`).
- Methods and functions: `snake_case`.
- Properties and variables: `snake_case`.
- Constants: `UPPER_SNAKE_CASE` (`OPTION_NAME`, `PAGE_SLUG`, `FRONT_SCRIPT_HANDLE`).
- Global constants are prefixed `HUBGO_` and defined in `Plugin::define_constants()`.

### Formatting

- 4 spaces, never tabs.
- Spaces inside parentheses: `if ( $a === $b ) {`, `foo( $bar )`. Exception, kept for consistency with existing code: `defined('ABSPATH')`, `do_action('Hubgo/Before_Init')` and `esc_html__` calls written without inner spaces in a few legacy spots — match the surrounding file rather than reformatting it.
- Negation with a space: `! empty( $value )`.
- **Two blank lines between methods.** One blank line between logical blocks inside a method.
- Long array syntax `array()`, not `[]`.
- Yoda conditions when comparing against a literal: `if ( 'woocommerce' === $type )`, `if ( null === self::$instance )`.
- Multi-line `array()` literals use aligned `=>` when it improves readability (see `Plugin::define_constants()`).

### Docblocks

Every class, method and property gets a docblock. This is not optional — the whole codebase follows it.

```php
    /**
     * Persist incoming settings after sanitization.
     *
     * @since 3.0.0
     * @version 3.0.1
     * @param array $incoming Raw settings map from the client.
     * @return array Sanitized settings that were saved.
     */
```

- `@since` = version where the member was introduced. **Never change an existing `@since`.**
- `@version` = version of the last meaningful change; add/bump it when you modify existing behaviour.
- `@param` / `@return` on everything. `@inheritDoc` is acceptable for route `handle()` overrides.
- Inline comments explain **why**, not what. Existing comments about ordering, gating and WP quirks are load-bearing — keep them and write new ones in the same voice.

### Security (mandatory)

- Escape on output: `esc_html__()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
- Sanitize on input: `sanitize_text_field()`, `sanitize_textarea_field()`, `sanitize_hex_color()`, `absint()`.
- **A string that travels to the SPA as data uses plain `__()`, never `esc_html__()`.** The schema, the integration catalog, the system status and every REST message are JSON that Vue renders with text interpolation, which escapes on its own; pre-escaping in PHP is what put a literal `&quot;` on screen. Keep `esc_html__()` for strings PHP concatenates into markup — an `echo`, a `printf()` of an admin notice, or the HTML of a modal block, which the SPA renders with `v-html`.
- Capability checks on every admin/REST surface. Default capability is `manage_woocommerce` (filterable via `Hubgo/Admin/Settings_Capability`).
- REST requests authenticate with the `wp_rest` nonce (`X-WP-Nonce`), issued through `wp_localize_script()`.
- Never trust `$_POST` / `$_GET` directly; go through `WP_REST_Request` or sanitize explicitly.

---

## 5. Architecture rules

### Bootstrap and lazy loading

`Plugin::init()` is the only entry point. Components are **not** instantiated eagerly: `get_hook_class_map()` maps a WordPress hook to the classes that should boot on it, and `safe_instance_class()` instantiates them behind the dependency gate (PHP version, WooCommerce presence and version), skipping classes with required constructor arguments and calling `init()` when present.

To add a component:

1. Create the class under the right `admin/src/` sub-namespace.
2. Add it to the appropriate hook bucket in `get_hook_class_map()`.
3. Give it a no-argument constructor that registers its own `add_action` / `add_filter` calls.

Hook-bucket choice matters: integrations boot on `plugins_loaded` because host plugins (Joinotify) assemble their catalogs from filters early; admin/REST/asset classes boot on `init`; storefront views boot on `wp_loaded`.

Classes that must work **without** WooCommerce (licensing) are wired outside the dependency gate — see `License::boot()` in `Plugin::init()`.

### REST API

Namespace: **`hubgo/v1`**. One class per endpoint under `admin/src/API/Routes/`, extending `Abstract_Route`:

```php
class Settings_Save extends Abstract_Route {

    protected $route = '/settings';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        // ...
        return $this->success_response( array( 'settings' => $saved ) );
    }
}
```

- Register the class in `Rest_Controller` (which is filterable via `Hubgo/API/Routes`).
- Always return through `success_response()` / `error_response()` so the payload shape stays `{ status: 'success'|'error', ... }`.
- Override `permission()` only when the endpoint needs something other than `manage_woocommerce` (e.g. public storefront calculation).
- Business logic belongs in `Core/*_Service.php` or manager classes; routes stay thin.

### Settings

Settings are **schema-driven** and stored in a single option, `hubgo_settings`.

- `Admin\Settings\Registry::get_schema()` defines sections → cards → fields. The Vue SPA renders whatever the schema declares; **adding a setting means editing the schema, not the UI**.
- A card declares either `fields` (rendered by the field registry) or a `component` name resolved against the page-local `CARD_COMPONENTS` map in `SettingsPage.vue` — that is how the "Sobre" tab mixes plain settings with the system status panel and the danger zone.
- `Admin\Default_Options::get_defaults()` must contain a default for every new key, **including integration keys**.
- `Admin\Settings\Repository` merges defaults at read time and sanitizes by field `type` at write time. New field types need a matching `case` in `sanitize_value()`.
- Toggles are stored as the strings `'yes'` / `'no'`, and an absent toggle in the payload means `'no'`. Every screen therefore POSTs its **full** settings map, never a partial patch.
- `Registry::get_field_definitions()` merges the integration card fields into the same map, so `POST hubgo/v1/settings` is the single write path for both screens.
- Read values elsewhere via `Settings::get_setting( 'key', $default )`.

### Templates

Overridable templates live in `templates/` and are loaded with `HUBGO_PATH . 'templates/'` as the WooCommerce template base, so themes can override them under `woocommerce/`. Keep template files logic-light: escape everything, no direct queries.

### Extensibility hooks

Naming convention: **`Hubgo/Namespace/Thing`** — slash-separated, PascalCase segments mirroring the class path.

Existing hooks include `Hubgo/Core/Address/Provider`, `Hubgo/Core/Address/Finder_Enabled`, `Hubgo/Core/Address/Lookup_Enabled`, `Hubgo/Core/Address/Postcode_Address`, `Hubgo/Core/Address/Rate_Limit`, `Hubgo/Core/Address/Daily_Budget`, `Hubgo/Core/Address/Client_Ip`, `Hubgo/Core/Address/Region_Code`, `Hubgo/Before_Init`, `Hubgo/After_Init`, `Hubgo/API/Routes`, `Hubgo/Admin/Menu/Registered`, `Hubgo/Admin/Settings/Schema`, `Hubgo/Admin/Settings/Field_Definitions`, `Hubgo/Admin/Settings/Bootstrap_Data`, `Hubgo/Admin/Settings/Reset`, `Hubgo/Admin/Settings_Capability`, `Hubgo/Admin/System_Status`, `Hubgo/Admin/Integrations/Bootstrap_Data`, `Hubgo/Admin/Integrations/Cards`, `Hubgo/Core/Assets/Admin_Pages`, `Hubgo/Core/Assets/FrontParams`, `Hubgo/Core/License/Payload`, `Hubgo/Core/Update_Checker/Payload`, `Hubgo/Core/Plugin_Installer/Allowed_Hosts`, `Hubgo/Migrations/Registered`, `Hubgo/Migrations/Batch_Processed`, `Hubgo/Tracking/Items_Imported`, `Hubgo/Integrations/Registered`, `Hubgo/Integrations/Loaded`, `Hubgo/Integrations/Cards`, `Hubgo/Integrations/Card`, `Hubgo/Integrations/Categories`, `Hubgo/Shipping_Calculator/Package`, `Hubgo/Shipping_Calculator/Rates`, `Hubgo/Shipping_Calculator/Rates_Order`, `Hubgo/Shipping_Calculator/Positions`, `Hubgo/Shipping_Calculator/Postcode_Helper`, `Hubgo/Shipping_Calculator/Postcode_State_Map`, `Hubgo/Shipping_Calculator/Resolved_State`, `Hubgo/Shipping_Calculator/Country`, `Hubgo/Shipping_Calculator/Destination`, `Hubgo/Shipping_Calculator/Zone`, `Hubgo/Shipping_Calculator/Config`, `Hubgo/Shipping_Calculator/Free_Shipping`, `Hubgo/Shipping_Calculator/Delivery_Estimate`, `Hubgo/Shipping_Calculator/Delivery_Days`, `Hubgo/Shipping_Calculator/Delivery_Meta_Keys`, `Hubgo/Shipping_Calculator/Holidays`, `Hubgo/Shipping_Calculator/Non_Business_Days`, `Hubgo/Shipping_Preference/Chosen_Method`, `Hubgo/Shipping_Preference/Postcode_Applied`, `Hubgo/Views/Calculator_Styles/Token_Map`, `Hubgo/Tracking/Get_Items`, `Hubgo/Tracking/Item_Saved`, `Hubgo/Tracking/Order_Shipped`, `Hubgo/Delivery/Promise_Saved`, `Hubgo/Delivery/Promise_Days`, `Hubgo/Delivery/Carrier_Meta_Keys`, `Hubgo/Delivery/Overdue`, `Hubgo/Delivery/Overdue_Enabled`, `Hubgo/Delivery/Overdue_Grace_Days`, `Hubgo/Delivery/Overdue_Query`.

**The delivery promise.** `Core\Delivery_Promise` stores what the shopper was told at the checkout (`_hubgo_delivery_date`, `_hubgo_delivery_days`, `_hubgo_delivery_carrier`, `_hubgo_delivery_method`) and every downstream consumer reads *that*, never a fresh quote. This is what makes a notification, a late-delivery check or an order screen able to state the promise months later, and it is why the capture is idempotent: an order that already carries a date keeps it, because a customer may already have been told. The forecast reaches the order for free — WooCommerce copies rate meta onto the shipping line item — so an integration only has to publish the carrier's days under one of the keys in `Delivery_Estimate::get_meta_keys()`.

**Tracking reads vs. writes.** `Hubgo/Tracking/Get_Items` is a *display* filter: an integration may inject items owned by another plugin (the Shipment Tracking bridge does, flagged `read_only`). Every write path in `Tracking_Manager` therefore starts from the unfiltered `read_items()` — feeding a filtered list back into storage would silently copy foreign data into HubGo's own meta on the next save.

**Shipping zone matching.** WooCommerce matches a package to a *single* zone using country + state + postcode and keeps the first by `zone_order`; the postcode can only *exclude* zones that declare postcode rules. So the package must carry the state that belongs to the informed postcode — `Core\Postcode_Locator` derives it from the CEP. Never populate `$package['destination']['state']` from the customer session: it is stale on the product page and absent entirely on REST requests, which silently hands the quote to the wrong zone.

**The calculator is side-effect free.** `Core\Shipping_Calculator_Service` never writes to the cart, the customer session or the checkout rate cache, which is what lets `POST hubgo/v1/shipping/calculate` stay public and cacheable. Anything that needs to *persist* a shopper decision goes through `Core\Shipping_Preference` instead. Do not reach for `WC()->session->set( 'chosen_shipping_methods', ... )` from a product page: it forces a WooCommerce session (a DB row plus a cookie) for every visitor who merely calculates shipping, and that cookie makes most page caches bail out.

### Address lookup

`Core\Address` gives the calculator two things: the free-text "I do not know my postcode" finder (`hubgo/v1/address/autocomplete` + `/address/resolve`) and the street name a quote is for, which travels back inside `context.address` and is what makes the card read *"to Rua X (postcode 00000-000)"*.

**Nothing in Core registers a provider.** `Address_Service::get_provider()` publishes `Hubgo/Core/Address/Provider` with a `null` default, and `Integrations\Google_Maps` is what answers it once its card is on and a key is saved. That is the whole reason an install with the card off never reaches an external service, and it is also the seam a third party plugs a different service into — never add a provider to Core's own defaults.

The three ceilings in `Address_Service` are load-bearing, not hygiene. Both the finder routes and `hubgo/v1/shipping/calculate` are public by necessity, and the quote resolves an address, so a script walking postcodes spends the store owner's Google quota. What stands between that and the bill: the per-postcode cache (a month — the street a postcode sits on does not change, so the steady-state cost is one call per postcode for the whole store), a per-visitor rate limit, a store-wide daily ceiling on *uncached* postcodes, and a five-minute negative cache so a rejected key does not mean one failed round-trip per product page view. **None of them may ever fail a quote:** `lookup_postcode()` answers with the empty address whatever goes wrong, and the provider gets `INLINE_TIMEOUT` rather than `TIMEOUT` because a shopper is waiting on a shipping quote while it runs.

The resolved city and state are deliberately *not* fed into `$package['destination']`. Changing what the package carries changes which zone matches and what the carrier is asked to price — that is a change to the quote wearing a cosmetic feature's clothes.

### Preferred shipping method

The shopper's choice travels in one cookie, `hubgo_ship_pref` (`{ r: rate id, p: postcode }`), written by the storefront app and read back by `Core\Shipping_Preference` at the cart and the checkout.

- Selection happens through `woocommerce_shipping_chosen_method`, which WooCommerce only applies when the session holds no valid choice or the available rates changed. The preference is therefore a **default, never an override** — a method picked at the checkout always wins.
- Three guards make the filter bail: a still-available local pickup is selected (WooCommerce keeps pickup sticky for the block checkout toggle), the default is free shipping granted by a coupon (applying a paid preference would charge someone who had earned free delivery), or nothing in the package matches.
- The cookie is attacker-controllable input. Its rate id is only ever used to **select** a key already present in `$package['rates']`, never to build a rate, and the postcode is validated with `WC_Validation::is_postcode()` before touching `WC_Customer`.
- The postcode seed only fills an **empty** shipping postcode, and sets the state derived from it as well — seeding the postcode alone can hand the cart to a different zone than the one that was quoted.

### Storefront calculator styling

The calculator is styled exclusively through `--hubgo-calc-*` CSS custom properties, which is what lets the settings panel and Elementor drive the same component without either knowing about the other. The cascade resolves precedence, lowest to highest:

1. `app/src/storefront/styles/calculator.css` — the default value of every property.
2. `Views\Calculator_Styles` — a `.hubgo-shipping-calculator { … }` block in `wp_head`, from the Aparência tab. Only non-empty settings are emitted; an empty setting means "keep the built-in value", which is why the style keys in `Default_Options` are empty strings rather than copies of the stylesheet's numbers.
3. The Elementor widget — `{{WRAPPER}} .hubgo-shipping-calculator { … }`, higher specificity, per instance.

`Calculator_Styles::get_token_map()` is the single source of truth for which setting feeds which property; the Elementor widget builds its `selectors` from it via `Calculator_Styles::get_token()`. Adding a style control means one entry in that map plus one control — never a property name written in two places. `app/src/storefront/tokens.js` mirrors the property list for one reason only: modals are teleported to `<body>`, so their values have to be copied across explicitly.

Never style the storefront with Tailwind utilities. The admin build scopes every utility under `.hubgo-app` (`important: '.hubgo-app'`), so a utility class out there compiles to a selector that never matches.

Add a filter to any list or payload a third party might reasonably want to extend. Never rename or remove a published hook.

### Integrations

Add a class under `admin/src/Integrations/` extending `Integrations_Base`, and register it in the `Hubgo/Integrations/Registered` filter default array. The class itself decides whether its dependency is available — do not gate it from the registry.

**Never patch a plugin HubGo installs.** The Integrations screen installs Frenet and Melhor Envio straight from wordpress.org, so any edit to their files is erased by the next update — and silently, because the store keeps working right up to the moment the integration stops. An incompatibility is therefore fixed from this side, using whatever public surface the other plugin exposes: a hook, a public property, or the HTTP response it receives. `Integrations\Frenet` is the worked example of all three — it flips the gateway's public `quoteByProduct` flag through `woocommerce_before_get_rates_for_package`, recovers the delivery forecast the gateway discards by tapping `http_response` (`Integrations\Frenet_Quote_Listener`), and unhooks the gateway's competing product-page simulator by name. `Integrations\Melhor_Envio` covers the two cases Frenet does not: it reshapes the package through `Hubgo/Shipping_Calculator/Package` into the form the gateway expects from a product-page quote (`product_page_calculation` plus a `formatted_data` payload built with the gateway's own factory), and unhooks a simulator that was hooked from an instance nobody kept a reference to, by matching the registered callbacks on their class. Reach for an upstream pull request only for what genuinely has no external path, and keep shipping the external fix until it lands.

The constructor must follow this order:

```php
public function __construct() {
    // Always first: the card has to be listed even when the host plugin is
    // missing or the toggle is off, or the user can never enable/install it.
    $this->register_integration_card( 20 );

    if ( ! self::is_available() || ! self::is_enabled() ) {
        return;
    }

    // Runtime wiring here.
}
```

`add_integration_item( $integrations )` returns the catalog with the card appended under its slug. Recognized keys: `title`, `description`, `icon` (inline brand SVG), `category`, `setting_key`, `is_plugin`, `plugin_active` (list of basenames — **any** match satisfies the dependency), `requires_license` (renders the Pro badge), `coming_soon`, `doc_url`, `install` (`plugin_slug` + `download_url`), `settings` (field definitions, same shape as the schema) and `modal` (`title`, `description`, `size`, `blocks`).

Runtime flags (`enabled`, `plugin_active`, `is_locked`, `can_install`, `has_settings`, `disabled_message`) are computed by `Registry::get_integration_cards()`. The Vue side is a pure renderer — never re-derive them in the frontend.

### Data migrations

A migration imports another plugin's data into HubGo. Add a class under
`admin/src/Core/` extending `Core\Abstract_Migration` and register it in the
`Hubgo/Migrations/Registered` filter default array (`Core\Migration_Registry`).

The subclass answers four questions — how many orders hold the source data, how
to page through them, how to convert one, and whether the source is reachable.
Batching, the progress option (`hubgo_migrations_state`) and the status payload
belong to the base class. `POST hubgo/v1/migrations/run` processes one batch and
the client calls it until the status reports `completed`, so a large store never
depends on a single long request.

Two rules are load-bearing:

- **Idempotency lives in the subclass.** The progress option is only a resume
  pointer; a re-run over an already migrated order must import nothing. HubGo's
  Shipment Tracking migration flags the order (`_hubgo_wcst_migrated_at`) and
  `Tracking_Manager::import_items()` additionally de-duplicates by
  `source_tracking_id`.
- **Never fire the per-record hooks.** `import_items()` deliberately skips
  `Hubgo/Tracking/Item_Saved`: that hook drives the Joinotify automation, and
  replaying it over a store's history would send one WhatsApp message per
  historical shipment. Use `Hubgo/Tracking/Items_Imported` instead.

Source data is copied, never deleted, so a store can roll back by re-activating
the other plugin. A migration surfaces on the Integrations screen through a
`migration` modal block (`Integrations_Base::modal_migration_block()`), which
carries the whole status payload — the Vue block renders the progress bar and
drives the endpoint without knowing which plugin is being migrated.

One-click installs go through `Core\Plugin_Installer`, which only accepts packages from `Hubgo/Core/Plugin_Installer/Allowed_Hosts` and reads the URL from the server-side catalog, never from the request body. An integration whose plugin has not shipped yet leaves `download_url` empty and sets `coming_soon`, so the card advertises the integration without offering an install. Cards that resolve their package from a constant or a filter (`Integrations\Melhor_Envio`, through `HUBGO_MELHOR_ENVIO_PACKAGE_URL` and `Hubgo/Integrations/Melhor_Envio/Package_Url`) can be pointed at a beta build without a code change.

---

## 6. Frontend (Vue SPA) conventions

Stack: **Vue 3 (`<script setup>`) + Pinia + Vite 6 + Tailwind CSS 3 + Boxicons**. No TypeScript, no router (one entry per admin page).

### Structure

- One Vite entry per admin page in `app/src/entries/`, mounted via `mountPage( 'element-id', Component )`. Adding a page means: a `src/entries/*.js` file, a Rollup input in `app/vite.config.js`, a row in `Core\Assets::get_admin_pages()` and a render method in `Admin\Menu`.
- The PHP page renders only the mount node plus a skeleton; all data arrives from REST.
- Shared components live under `app/src/components/<domain>/`; page-specific ones under `app/src/pages/<page>/components/`.
- Cross-page logic lives in `app/src/composables/` (`useToasts`) and `app/src/utils/` (`object.js`, `icons.js`).

### Icons

UI glyphs come from `@boxicons/vue`, imported by name so Rollup can tree-shake them. Icon *names* the backend emits (section tabs, integration categories) are mapped in `app/src/utils/icons.js` — add a mapping there instead of importing Boxicons ad hoc in a component. Brand logos remain inline SVG shipped by PHP and are detected with `isMarkup()`.

### Style

- 4 spaces, single quotes, semicolons.
- Spaces inside call/paren expressions, matching the PHP style: `foo( bar )`, `if ( ! value )`, `array.map( ( item ) => item.id )`.
- SFC order: `<script setup>` first, then `<template>`. Scoped `<style>` only when Tailwind genuinely cannot express it.
- Each component opens with a JSDoc block: file name, one-paragraph purpose, `@since`.
- Every non-trivial function gets a JSDoc block with `@param` / `@return`.
- `defineProps` with explicit types and defaults; `defineEmits` declared explicitly. Use `v-model` conventions (`modelValue` / `update:modelValue`).

### REST access

Always go through `app/src/utils/api.js` (`api.get`, `api.post`, `api.del`). It reads `window.hubgoBootstrapConfig` (injected by `wp_localize_script`) for `restUrl` and `nonce`. Never call `fetch()` directly from a component and never hardcode a REST URL.

### i18n in JS

Use `__()` from `app/src/utils/i18n.js`, which proxies `window.wp.i18n` at call time. `@wordpress/i18n` is deliberately **not** bundled so the WordPress-provided locale data stays authoritative. Script translations are wired by `wp_set_script_translations()` in `Core\Assets`.

### Styling rules (important)

- Tailwind `preflight` is **disabled** and every utility is scoped with `important: '.hubgo-app'`. Nothing may leak into wp-admin.
- Teleported roots (modals, toasts, dropdowns rendered into `<body>`) must carry the `.hubgo-app` class.
- Design tokens are CSS custom properties declared on `.hubgo-app` in `main.css`, never on `:root`.
- Use the brand scale from `tailwind.config.js` (`primary-*`, `shell-*`, `ink`, `success`, `danger`, `warning`, `info`, `muted`) instead of raw hex values.
- Tailwind `content` also scans `admin/src/Views/**/*.php` — classes used in PHP-rendered markup are safe.

**Control chrome lives in `main.css`, not in the components.** Height (`--hubgo-control-height`), padding, radius and border colour are declared once and applied both to bare inputs (through the `input`/`textarea`/`select` block that also beats wp-admin's `forms.css`) and to the `.hubgo-control` class, which any element acting as a control — the select trigger, the colour swatch, a shell wrapping an input plus a suffix — opts into. A control that needs an inner input renders it with `.hubgo-control__inner`, which strips the input's own chrome so the shell owns it. Do not re-declare border/radius/focus utilities on a field: that is how the fields drifted apart in the first place.

Focus is a **2px solid primary border and nothing else**. The idle border is already 2px, so the focused state only recolours it and never resizes the box. Never reintroduce a `ring-*`/`box-shadow` glow; where a border cannot express focus (buttons, the toggle), use `outline-2 outline-offset-2 outline-primary-700`. A control whose focus lives elsewhere (an open dropdown teleported to `<body>`) keeps its border by carrying `is-focused`.

### Motion

Every transition is built from one token set, declared on `.hubgo-app` in `main.css` and mirrored on `.hubgo-shipping-calculator` / `.hubgo-calc-modal` in `calculator.css` (separate bundle, and the modal is teleported out of the widget): `--hubgo-motion-fast|base|slow`, `--hubgo-motion-shift`, `--hubgo-motion-scale`, `--hubgo-ease-out|in`. Four rules follow from that:

1. **A named `<Transition>` is backed by plain CSS, never by Tailwind utilities bound to the `enter-*`/`leave-*` props.** Those sets share tokens, and Vue's class bookkeeping then leaves both the "from" and the "to" utility on the node: the leave never resolves and the element stays in the DOM, visible. This has already happened once — see the comment on `hubgo-select-pop`. It also means a transitioned element must not carry an `opacity-*`, `translate-*` or `transition-*` utility of its own; under `important: '.hubgo-app'` those compile with `!important` and win outright.
2. **Only `opacity` and `transform` are animated.** Never `height`, `width` or an offset — those are a reflow per frame. Where two states have different heights, crossfade with `mode="out-in"` and let the height change once.
3. **Displacement goes through `--hubgo-motion-shift` / `--hubgo-motion-scale`, never a literal.** That is the whole `prefers-reduced-motion` switch: collapsing the two tokens to `0` and `1` turns every transition into a plain fade without a rule being restated. Spinners keep spinning under reduced motion — they report that work is still running; decorative shimmer stops.
4. **The leave is faster than the enter.** The enter is the user waiting for something; the leave is the user already moving on.

A `TransitionGroup` that needs its `-move` to work gives the leaving item `position: absolute` with **every offset left at `auto`**, so it is painted at its static position while the rest close the gap, and its container `position: relative` so `width: 100%` resolves. Do not reach for this inside a CSS grid: an absolutely positioned grid child escapes its track. Crossfade the whole grid on a key instead — that is what `IntegrationsGrid` does.

### Field system

Settings controls are resolved at runtime by `app/src/components/fields/fieldRegistry.js`. To add a field type:

1. Create the component under `app/src/components/fields/` (or `toggles/`, etc.).
2. `registerFieldComponent( 'my-type', MyField )` in `fieldRegistry.js`.
3. Add the matching field builder in PHP `Settings\Registry` and a sanitizer case in `Settings\Repository`.

Registered types: `toggle`, `text`, `textarea`, `select`, `color` (alias `color-picker`), `number`, `range`, `dimension`, `password`.

`number`, `range` and `dimension` store their value as a **string**, and an empty string is meaningful: the calculator style keys read it as "use the built-in default". Do not coerce a cleared field to `0` — that is a different instruction. `range` renders at the field's declared `default` while unset and offers an explicit reset back to empty.

`dimension` is the control for a CSS length: a numeric input plus a unit picker (`rem`, `em`, `px`, `%`, narrowed per field with `units`). It stores the **assembled CSS value** — `"1.5rem"`, `"12px"`, `"50%"` — so `Views\Calculator_Styles` can drop it straight into a custom property. A bare number is still valid input and is read with the field's declared `unit`: that is how every length was stored before the picker existed, and `Calculator_Styles::sanitize_value()` still appends the unit from the token map to it. A stored value is only ever re-saved with its unit spelled out. The Elementor widget offers the same unit list, so both editors can express the same value.

The registry is exposed as `window.HubgoFieldComponents` and announces `hubgo:field-registry-ready` so external bundles can register or override widgets — keep that contract intact.

### Build output

`app/dist/` is **git-ignored and generated**. `npm run build` rebuilds it and copies it into `release/hubgo-<version>.zip`, which is what ships; PHP resolves it through the Vite manifest (`app/dist/.vite/manifest.json`) via `Core\Scripts`. `admin/vendor/` works the same way.

Nothing generated is committed, so **never commit `app/dist/` or `admin/vendor/`** — and never assume a clean checkout has them. Run the build.

The risk this trades against is real and was hit once: while `app/dist` was tracked, adding it to `.gitignore` left the already-tracked bundles working (hiding the breakage) while a brand-new entry silently stayed out of the release. `assertBuildArtifacts()` in `scripts/build.mjs` is the guard against that — it fails the build when the manifest is missing an entry, so the failure is loud and at build time instead of silent and in production. Keep its entry list in step with `app/vite.config.js`.

For local work, `npm run build:app` still rebuilds just the frontend without packaging.

### Storefront app

Since 3.0.0 the storefront calculator is a Vite bundle like the admin screens (`src/entries/storefront.js` → `app/dist/storefront/`), resolved through the manifest by `Core\Assets::enqueue_frontend_assets()` and configured through `hubgo_front_params`. The vanilla `assets/front/` bundle it replaced was deleted.

Rendering is uniform across all three entry points — the product-page hook, the `[hubgo_shipping_calculator]` shortcode and the Elementor widget all print the same node through `templates/shipping-calculator.php`:

```html
<div class="hubgo-shipping-calculator" data-hubgo-calculator='{ … }'></div>
```

`Views\Shipping_Calculator::get_config()` builds that config in one place, so the three can never drift; `app/src/storefront/mount.js` scans for the nodes and mounts one Vue app per node, re-scanning on Elementor's `frontend/element_ready` so the editor preview stays live.

The published DOM events `hubgo:shipping_calculated` and `hubgo:shipping_error` are part of the contract and survived the rewrite; `hubgo:shipping_preference_changed` was added.

`assets/admin/` (the order tracking metabox and the plugins-list update check) remains **plain, unbundled** CSS/JS enqueued directly. Do not migrate it into the Vite build — those surfaces are core admin screens that never load the SPA, so their strings are passed in through `wp_localize_script()` instead of `wp.i18n`.

---

## 7. Internationalization

- Text domain: **`hubgo`**, loaded from `/languages` on `init` at priority **0**.
- **Source strings are written in en-US** (since 3.0.0) and translated outward from there, pt-BR included. Everything is English: code, comments, docblocks, commit messages, this documentation, the plugin header and every user-facing string.
- Wrap every user-facing string: `__()`, `esc_html__()`, `_n()`, `esc_html_e()` in PHP; `__()` from `utils/i18n.js` in the SPA. This includes the storefront copy defaults in `Admin\Default_Options`, so an untouched install reads in the site language.
- **Never call a translation function before `init`.** The text domain is not loaded yet and WordPress 6.7+ answers with *"Translation loading for the hubgo domain was triggered too early"*. In practice: nothing translatable may run at plugin-load time or on `plugins_loaded`. `Plugin::check_dependencies()` therefore yields error *codes* and `render_dependency_notices()` turns them into copy; the integrations registry and the tracking components boot on `init` (priority 5) rather than earlier.
- Never concatenate translatable fragments — use `sprintf()` with placeholders.
- Preserve sprintf placeholders, HTML, URLs and template tokens (`{{ hubgo_tracking_code }}`, `{{ wc_order_total }}`) inside translated strings.
- After adding strings, run `npm run pot` (or a full `npm run build`) and commit the regenerated `languages/` artifacts. `.mo` and `.l10n.php` are committed; `.env` and `node_modules` are not.
- Supported locales: `en_US`, `es_ES`, `pt_BR`, `pt_PT`, `de_DE`, `fr_FR`, `it_IT`.

---

## 8. Versioning and releases

The version appears in **four** places and they must stay in sync:

1. `hubgo.php` plugin header `Version:`
2. `$plugin_version` in `hubgo.php`
3. `package.json`
4. `app/package.json`

Semantic versioning. Licensing, update checks, signature verification and rollback are handled by the **MDS PHP SDK** (`admin/vendor/meumouse/mds-php-sdk`) via `Core\License` — do not reintroduce a custom updater or poll a static JSON endpoint. MDS credentials are compile-time constants overridable from `wp-config.php` (`HUBGO_MDS_API_URL`, `HUBGO_MDS_API_KEY`, `HUBGO_MDS_PUBLIC_KEY`).

Release artifacts go to `dist/` (`hubgo.zip`, `versions/<version>/`).

---

## 9. Git conventions

- Work on feature branches (e.g. `refactor/hubgo-3.0.0`); `main` is the release branch.
- Commit subjects: imperative mood, English, capitalized, no trailing period — matching history: *"Add WooCommerce install/activate button to the dependency notice"*, *"Move backend into /admin (src + Composer), mirroring Joinotify layout"*.
- Commit generated artifacts (`app/dist/`, `languages/*.mo`, `languages/*.l10n.php`) together with the sources that produced them.
- `admin/vendor/` and `node_modules/` are git-ignored and regenerated by the build.
- Do not commit or push unless explicitly asked.

---

## 10. Working agreements for agents

**Do**

- Read the surrounding file before editing and mirror its exact style, spacing and docblock density.
- Prefer extending the schema/registry/filter systems over adding bespoke code paths.
- Keep the SPA thin and the REST contract stable; the SPA must render whatever the schema returns.
- Bump `@version` tags and the plugin version when behaviour changes.
- Rebuild and commit `app/dist/` and `languages/` artifacts when their sources change.
- Flag genuine bugs you notice in passing instead of silently fixing unrelated code.

**Don't**

- Don't add `admin-ajax.php` handlers — everything goes through `hubgo/v1`.
- Don't add PHP outside `admin/src/`, or bundle-built JS outside `app/`.
- Don't enable Tailwind preflight, use `:root` tokens, or emit unscoped CSS.
- Don't bundle `@wordpress/i18n`.
- Don't rename or remove published hooks, REST routes, option keys or setting keys.
- Don't edit `admin/vendor/` or `app/node_modules/` by hand.
- Don't introduce new build tooling, test frameworks, linters or dependencies without being asked.
- Don't write source strings, comments or documentation in Portuguese — en-US is the source language everywhere; locale copy belongs in `languages/`.
- Don't call `__()` (or any sibling) before `init` — see section 7.
