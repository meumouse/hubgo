# Changelog

All notable changes to HubGo are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Two notes on the history below. Releases before 2.0.0 did not strictly follow SemVer — a bug fix shipped as a minor (1.4.0) and a feature shipped as a patch (1.2.5) — so the numbering of that period cannot be read as a compatibility promise. And the entries before 3.0.0 were carried over from the original history, keeping the level of detail they had at the time.

## [Unreleased]

## [3.0.0] - 2026-08-26

### Added

- **A modular, extensible foundation** — an integration registry and a filterable settings schema, so a setting, a card or an endpoint is added by declaring it rather than by editing the UI
- **A HubGo menu of its own in the admin panel**, with the Settings, Integrations and License subpages
    - The Settings page has the General, Appearance, Texts and About tabs
    - The About tab gathers the maintenance preferences, the system status and the restore-defaults action
    - The `hubgo-settings` slug remains valid: old links keep working
- **Integrations page** with an application grid, a category filter and a settings modal per integration
    - Joinotify (Pro), Melhor Envio and Frenet
    - Install and activate the integration's plugin straight from the admin panel
    - "Pro" badge on the integrations that depend on an active license
- **A License screen of its own**, with the activation, sync and site deactivation forms
- **Licensing and updates through the MDS PHP SDK** (`meumouse/mds-php-sdk` ^1.1), installed via Composer
    - "HubGo - License" screen to activate, deactivate and revalidate the key, with a notice when the license is missing, invalid or expired
    - Signed update check (ed25519) restricted to valid licenses, with a 12-hour cache and a daily heartbeat via WP-Cron
    - Bundle license support: a single key covers several products (e.g. "Clube M"), with the included products available from `MeuMouse\Hubgo\Core\License::get_bundle()`
    - Plan data, renewal URL, support expiry and the activation limit available from `License::get_data()` without a new request
    - An "Automatic updates" toggle on the About tab lets HubGo update itself whenever the license allows
- **"Check for updates" link on the HubGo row of the Plugins list** (`MeuMouse\Hubgo\Core\Update_Checker`)
    - The check is asynchronous (`POST hubgo/v1/updates/check`) and the result appears next to the link, without reloading the page
    - It forces a fresh check against MDS: the 12-hour update cache and the rollback version list are cleared and the license is revalidated before answering
    - When a new version is available, the "Update now" link goes straight to the WordPress update
    - Without JavaScript, the link itself runs the check on the server and returns the result as a notice
    - New filter: `Hubgo/Core/Update_Checker/Payload`
- **The delivery date promised at the checkout is now stored on the order** (`MeuMouse\Hubgo\Core\Delivery_Promise`)
    - Stores the estimate in business days, the promised date, the carrier and the shipping method, from the meta WooCommerce copies from the shipping method onto the order item
    - Works on the classic checkout and on the block checkout (Store API)
    - New filters: `Hubgo/Delivery/Promise_Days`, `Hubgo/Delivery/Carrier_Meta_Keys`. New action: `Hubgo/Delivery/Promise_Saved`
- **Daily check for late deliveries** (`MeuMouse\Hubgo\Core\Delivery_Watcher`)
    - Orders in the "Order shipped" status whose promised date has passed fire the `Hubgo/Delivery/Overdue` action, once per order
    - Processed in batches of 50 orders per run, with a one-day grace period
    - New filters: `Hubgo/Delivery/Overdue_Enabled`, `Hubgo/Delivery/Overdue_Grace_Days`, `Hubgo/Delivery/Overdue_Query`
- **Joinotify: new "Late delivery" trigger and new placeholders**
    - `{{ hubgo_delivery_date }}`, `{{ hubgo_delivery_days }}` and `{{ hubgo_shipping_method }}`, available on every HubGo trigger
    - `{{ hubgo_carrier_name }}` now uses the carrier quoted at the checkout when the tracking code has no carrier of its own yet
- **Compatibility with the Frenet plugin (Frenet Shipping Gateway)** — everything solved on HubGo's side, without touching the third-party plugin
    - The delivery time and the carrier (Correios, Jadlog, Loggi) are read from the Frenet API response and stored as standard shipping method meta (`delivery_time` and `carrier`), which puts the promised date back on the calculator ("Get it by <date>"). On stores with no token (SOAP mode), the delivery time is read from the shipping method label
- **Compatibility with the official Melhor Envio plugin** — everything solved on HubGo's side, without touching the third-party plugin
    - The integration card points to the official plugin (`melhor-envio-cotacao`), with a one-click install from the WordPress repository
    - The carrier that actually moves the parcel (Correios, Jadlog, Loggi, Azul) is stored as standard shipping method meta (`carrier`), which makes the carrier's name — and not the intermediary's — appear in the notifications and in the tracking. The delivery time is read from what the plugin writes to `delivery_time`
    - The integration is free rather than Pro, like Frenet's: what it does is stop two plugins the store already uses from contradicting each other, and that should not depend on a license
    - New "Tracking URL" option on the integration card, defaulting to Melhor Rastreio
    - New filters: `Hubgo/Integrations/Melhor_Envio/Plugin_Files`, `Hubgo/Integrations/Melhor_Envio/Package_Url`, `Hubgo/Integrations/Melhor_Envio/Skip_Compositions`
- **State resolution from the postcode** (official Correios ranges) via `MeuMouse\Hubgo\Core\Postcode_Locator`
- **The "State" field of the postcode finder uses HubGo's modern select**, with search by name or abbreviation, keyboard navigation and the same look as the other fields
    - The states come from the WooCommerce list (`WC()->countries->get_states()`), showing the state name with the abbreviation next to it, instead of the abbreviation alone
- **Measurement field (`dimension` type) in the appearance settings**, with a unit picker (rem, em, px and %)
    - Radii, spacing, height, font size and blur are no longer sliders and now accept the chosen unit
    - The value is stored with its unit ("1.5rem"); values stored before remain valid and are read as px
    - The Elementor widget offers the same units on the equivalent controls
- **Smooth transitions across the whole interface, in the admin panel and on the storefront**
    - Modals fade in and out with a slight shift, instead of appearing all at once
    - The page behind the modal is locked from scrolling while it is open, without shifting the wp-admin content
    - The loading skeleton of the three screens gives way to the content in a transition, instead of swapping all at once
    - Switching tabs in the settings, filtering by category on the integrations screen and activating the license swap the panel in a transition
    - On the storefront calculator: switching between the postcode form and the quote, the cascading entrance of the shipping methods, selecting an option and the address lookup
    - Toasts come in from the right, leave without pushing the others, and the remaining ones slide into place
    - Everything respects the operating system's "reduce motion" preference: animations become a short fade, and loading indicators keep spinning
- New public method `MeuMouse\Hubgo\Core\Delivery_Estimate::get_days_from_meta()`
- Optional `country` parameter on the `POST hubgo/v1/shipping/calculate` endpoint
- With WooCommerce's shipping debug mode on, the endpoint returns the matched zone
- New REST endpoints: `GET hubgo/v1/integrations`, `POST hubgo/v1/plugins/install`, `POST hubgo/v1/settings/reset`, `GET hubgo/v1/license`, `POST hubgo/v1/license/activate`, `POST hubgo/v1/license/deactivate`, `POST hubgo/v1/license/sync`, `POST hubgo/v1/updates/check`
- New calculator filters: `Hubgo/Shipping_Calculator/Postcode_State_Map`, `Hubgo/Shipping_Calculator/Resolved_State`, `Hubgo/Shipping_Calculator/Country`, `Hubgo/Shipping_Calculator/Destination`, `Hubgo/Shipping_Calculator/Zone`
- New admin and integration filters: `Hubgo/Integrations/Cards`, `Hubgo/Integrations/Card`, `Hubgo/Integrations/Categories`, `Hubgo/Admin/Integrations/Cards`, `Hubgo/Admin/Integrations/Bootstrap_Data`, `Hubgo/Admin/System_Status`, `Hubgo/Core/Assets/Admin_Pages`, `Hubgo/Core/License/Payload`, `Hubgo/Core/Plugin_Installer/Allowed_Hosts`
- `MeuMouse\Hubgo\Core\License::allows_updates()` and `::allows_downloads()`, the two permissions MDS now answers the update check with. Every update surface asks these instead of `is_active()` — the server can waive a gate for a single license, which is how a customer who bought before HubGo required a key keeps updating, and a client that decided on its own would refuse the update before MDS ever got to honour its own waiver
- Both permissions travel in the license summary (`allows_updates`, `allows_downloads`), so a screen never has to infer "is this site still being served?" from the license status
- `Hubgo/Core/Update_Checker/Payload` gained `allows_updates`, `allows_downloads` and `can_install`. A release MDS announces without handing over the package now reads as "Version x.y.z is available, but installing it needs an active license" and carries no update link, instead of offering a one-click update that could only end in "Update package not available"
- The License screen tells a customer whose key is not active that this site is still receiving updates, when that is what the server says
- `Requires at least: 6.0` in the plugin header, matching the product metadata registered on MDS (`requires` 6.0, `tested` 7.1, `requires_php` 7.4)

### Changed

- **BREAKING: the settings interface was rewritten in Vue 3 + Vite** (Joinotify pattern), and the server-rendered settings form is gone
- **BREAKING: every legacy AJAX action was replaced by the REST API** (`hubgo/v1` namespace). Anything hooked to the old `admin-ajax.php` actions has to move to the REST routes
- **Joinotify v2 compatibility** (new functional API: triggers, placeholders and workflow dispatch)
- **Visual standardisation of the admin fields**: same height (3rem), same radius and same border on inputs, selects, password and colour
    - The colour picker has a 3rem square swatch next to the hex code field, with a button to reset to the default colour
    - Focus is a 2px border in the primary colour, with no shadow
    - Buttons declare their own appearance instead of inheriting the browser's, since Tailwind's preflight is disabled
- The `Hubgo/Shipping_Calculator/Rates` filter now receives the matched zone as a third argument
- **MDS PHP SDK to `^1.3`** (from `^1.1`). The update check is `POST /v2/update-check` with the site `domain`, the `product_slug` and the installed `current_version`, and it is no longer barred by the license being valid — it is barred by the update gate the server answers with
- The SDK's license notices and its auto-registered submenu are switched off through the `features` map the SDK exposes since 1.2, instead of unhooking the notice callback from `admin_notices` after registration
- **License activation is off, and it no longer takes the update check with it.** `License::ENABLED` used to switch off the whole SDK registration; it now governs the key alone. With it off HubGo registers under the SDK's `updates_only` preset — the check goes out with no `license_key` and MDS answers on the product's own gates, with the response still verified by ed25519 signature. This needs MDS to agree: a keyless check is only answered for a product whose update gate is open on the server
- `License::is_configured()` answers for the MDS credentials alone, so the "Check for updates" link on the plugins list no longer disappears along with activation
- `License::get_license_url()` returns an empty string while there is no License screen to link to, instead of a URL WordPress would refuse

### Removed

- **The bespoke update checker** (`MeuMouse\Hubgo\API\Updater`), which polled a static JSON on packages.meumouse.com. Licensing, update checks, signature verification and rollback are the MDS SDK's job now
- **The license activation flow**, while `License::ENABLED` stays off: the License screen and its Vue bundle, the `hubgo/v1/license/*` routes, the license heartbeat, rollback and the SDK's license notices. Nothing was deleted — the constant brings all of it back
- **The Pro tier on the integrations catalog**: the `requires_license` card key, the `is_locked` runtime flag, the Pro badge component and the "activate your license" notices on the Integrations screen and in the Elementor card. The Elementor widget is registered whenever the integration is enabled, instead of only while a license was valid — which used to make the element vanish from pages already using it when a key lapsed

### Fixed

- **The shipping calculator did not respect shipping zone priority** for the postcode entered. The destination state was read from the customer session (or from the store's base address) instead of being derived from the postcode, letting a per-state zone with a lower order beat the zones the postcode actually covered
- The shipping calculator changed the customer's cart (adding and removing the product) to evaluate free shipping
- The shipping calculator overwrote the billing and shipping postcode saved in the customer session
- Free shipping was injected into the table by hand, ignoring the zone, the `woocommerce_package_rates` filter and the option to hide the other rates
- The calculation wrote to the same session cache used by the checkout
- An invalid postcode returned the "no shipping method available" message instead of a validation error
- The plugin silently changed WooCommerce's `woocommerce_default_customer_address` option
- **Frenet (Frenet Shipping Gateway)**
    - HubGo's calculator was quoting with a declared value of R$ 0.00. The Frenet plugin reads the cart total, which is empty on the product page, and services quoted by declared value simply did not show up. Quoting by product is now enabled on HubGo's packages only
    - The product page showed two calculators. The Frenet plugin's simulator is now removed when HubGo's calculator is active, with a new "Hide the Frenet simulator" option on the integration card
    - The "Import tracking" option now has a real effect: on orders shipped through Frenet, tracking codes with no carrier set get Frenet as the provider (for the link) and the quoted carrier as the displayed name. Nothing is written to the database — the fill-in happens at display time only
- **Melhor Envio**
    - HubGo's calculator did not work with Melhor Envio active. The plugin quotes the customer's cart unless the package identifies itself as a product-page quote; without that, either the quote came out with the wrong items (cart filled) or the request broke while reading data that did not exist in the package (empty cart). HubGo's package is now assembled in the shape the plugin expects (`product_page_calculation` and `formatted_data`), using Melhor Envio's own product factory — which keeps bundles and composite products normalized the same way. This fix applies whenever the Melhor Envio plugin is active, even with the integration switched off: it is what keeps HubGo's calculator standing, not a feature of the integration
    - The "Import tracking" option now has a real effect: as soon as Melhor Envio writes the tracking code onto the order, it is imported into HubGo's tracking with Melhor Envio as the provider (for the link) and the quoted carrier as the displayed name, firing the e-mails and the Joinotify automations like a code typed by hand. Codes predating the integration are still displayed from the other plugin's data, read-only
    - The "Mark as shipped" option now has a real effect, and stands on its own rather than depending on "Import tracking" being switched on as well: the order moves to the "Order shipped" status the first time a Melhor Envio code arrives, once per order, without touching cancelled, refunded or failed orders
    - The product page showed two calculators. The Melhor Envio plugin's simulator is now removed when HubGo's calculator is active, with a new "Hide the Melhor Envio simulator" option on the integration card
    - Bundles and composite products (WPC Product Bundles and WPC Composite Products) got the wrong quote. Outside the cart the Melhor Envio plugin only sees the parent product's dimensions, which are usually empty — so much so that its own simulator refuses to quote and asks for the calculation to be done in the cart. Melhor Envio's shipping methods are now left out of the calculator in those cases, instead of showing a price the store will not be able to honour. New filter for stores that have filled in the bundle's dimensions: `Hubgo/Integrations/Melhor_Envio/Skip_Compositions`
    - The delivery time appeared twice on the calculator. The plugin repeats the delivery window inside the shipping method name ("Correios Pac (3 a 5 dias úteis)") as well as storing it as meta, and HubGo already turns that meta into a promised date — which starts to disagree with the text as soon as the store sets a handling time. The part in parentheses is now removed, on HubGo calculator packages only

### Security

- REST endpoints now require a capability check plus the `wp_rest` nonce

## [2.2.0] - 2026-03-12

### Added

- "Order shipped" status in the bulk actions of the orders page

### Fixed

- Orders in the "Order shipped" status were not listed on the orders page

## [2.1.0] - 2026-03-06

### Added

- New order status: "Order shipped"
- Order metabox for entering the tracking code
- Joinotify integration

## [2.0.0] - 2026-02-25

### Changed

- **Architecture change to MACI** (Modular Autoload Class Initialization)
- Optimizations

### Fixed

- Melhor Envio compatibility

## [1.4.0] - 2025-08-11

### Fixed

- Loading of the shipping options

## [1.3.0] - 2024-02-16

### Added

- Automatic shipping calculation

### Changed

- Optimizations

### Fixed

- Bug fixes

## [1.2.6] - 2023-12-18

### Fixed

- Bug fixes

## [1.2.5] - 2023-10-31

### Added

- WooCommerce High Performance Order Storage (HPOS) compatibility

## 1.2.0 - 2023-10-09

### Changed

- Optimizations

### Fixed

- Bug fixes

## 1.1.7 - 2023-07-26

### Changed

- Optimizations

### Fixed

- Bug fixes

## 1.1.5 - 2023-07-19

### Fixed

- Bug fixes

## 1.1.0 - 2023-07-14

### Fixed

- Bug fixes

## 1.0.0 - 2023-07-13

### Added

- Initial release

[Unreleased]: https://github.com/meumouse/hubgo/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/meumouse/hubgo/compare/v2.2.0...v3.0.0
[2.2.0]: https://github.com/meumouse/hubgo/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/meumouse/hubgo/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/meumouse/hubgo/compare/v1.4.0...v2.0.0
[1.4.0]: https://github.com/meumouse/hubgo/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/meumouse/hubgo/compare/v1.2.6...v1.3.0
[1.2.6]: https://github.com/meumouse/hubgo/compare/1.2.5...v1.2.6
[1.2.5]: https://github.com/meumouse/hubgo/releases/tag/1.2.5
