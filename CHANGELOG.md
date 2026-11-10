# Changelog

All notable changes to HubGo are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Two notes on the history below. Releases before 2.0.0 did not strictly follow SemVer — a bug fix shipped as a minor (1.4.0) and a feature shipped as a patch (1.2.5) — so the numbering of that period cannot be read as a compatibility promise. And the entries before 3.0.0 were carried over from the original history, which recorded little more than "bug fixes" at the time.

## [Unreleased]

### Added

- New filter `Hubgo/Integrations/Frenet/Carrier_Codes`, which maps a carrier name to the segment Frenet routes its tracking page on.

### Fixed

- The Frenet tracking link now opens Frenet's public tracking page instead of the panel's login screen.
- A tracking URL typed by hand is left untouched, so a store that customized that field has to update it itself.

## [3.1.0] - 2026-09-03

### Added

- Joinotify gained two triggers: the delivery date promised at the checkout, and a tracking code removed from an order.
- Joinotify gained eleven placeholders, 33 in all, covering tracking codes and links, days late, and the order's date, payment method, address, currency, discounts, taxes, coupons and review links.
- Tracking placeholders are now offered only on the triggers that carry tracking data.
- A condition node under a HubGo trigger now offers the WooCommerce conditions plus six HubGo ones: carrier, tracking code, number of codes, business days promised, days late and the checkout shipping method.
- New filter `Hubgo/Integrations/Joinotify/Placeholders`.

### Fixed

- No HubGo workflow in Joinotify ever ran, because Joinotify stored the trigger slug in a different shape than HubGo dispatched it.
- Workflows saved under the old slugs repair themselves on the first admin request, without being redrawn.
- The shipped-order notification went out two or three times per order and now fires once.
- The calculator quoted a line the shopper had already changed, and now re-quotes when the quantity or the variation moves.
- The calculator's package carries `cart_subtotal`, so methods that price insurance or a declared value read the same number as the cart.
- Quantity and variation are read from the form that sells the quoted product, so a shortcode no longer follows another product's fields.
- "Add tracking code" did nothing on the order screen: the form never opened and the carrier link preview stayed hidden.
- The Joinotify integration was verified against Joinotify 2.3.4, with the supported floor declared as `MIN_VERSION`.

## [3.0.0] - 2026-08-28

### Added

- A modular foundation: an integration registry and a filterable settings schema, so a setting, a card or an endpoint is added by declaring it.
- A HubGo menu of its own, with the Settings, Integrations and License subpages, and the old `hubgo-settings` links still working.
- Four tabs on the settings screen: General, Appearance, Texts and About.
- An Integrations page with an application grid, a category filter and a settings modal per integration.
- One-click install and activation of an integration's plugin from the Integrations page, Joinotify included.
- A License screen to activate, deactivate and revalidate the key.
- Licensing and signed updates (ed25519) through the MDS PHP SDK, with bundle licenses, plan data and an "Automatic updates" toggle.
- A "Check for updates" link on the HubGo row of the Plugins list, which forces a fresh check without reloading the page.
- The delivery date promised at the checkout is stored on the order, on both the classic and the block checkout.
- A daily check fires `Hubgo/Delivery/Overdue` for shipped orders whose promised date has passed, in batches, with a one-day grace period.
- Joinotify gained a "Late delivery" trigger and the delivery date, delivery days and shipping method placeholders.
- Compatibility with the Frenet Shipping Gateway: the delivery time and the carrier are read from Frenet's own response, which puts the promised date back on the calculator.
- Compatibility with the official Melhor Envio plugin, which records the carrier that actually moves the parcel instead of the intermediary's name.
- A "Tracking URL" option on the Melhor Envio card, defaulting to Melhor Rastreio.
- State resolution from the postcode, using the official Correios ranges.
- The postcode finder's "State" field uses HubGo's own select, searchable by name or abbreviation and fed by WooCommerce's state list.
- A measurement field with a unit picker (rem, em, px and %) for the radii, spacing, height, font size and blur on the Appearance tab.
- Smooth transitions across the admin panel and the storefront, honouring the system's "reduce motion" preference.
- Optional `country` parameter on `POST hubgo/v1/shipping/calculate`, which also returns the matched zone while WooCommerce's shipping debug mode is on.
- New public method `Core\Delivery_Estimate::get_days_from_meta()`.
- New REST endpoints: `GET hubgo/v1/integrations`, `POST hubgo/v1/plugins/install`, `POST hubgo/v1/settings/reset`, `GET hubgo/v1/license`, `POST hubgo/v1/license/activate`, `POST hubgo/v1/license/deactivate`, `POST hubgo/v1/license/sync` and `POST hubgo/v1/updates/check`.
- New calculator filters: `Hubgo/Shipping_Calculator/Postcode_State_Map`, `Hubgo/Shipping_Calculator/Resolved_State`, `Hubgo/Shipping_Calculator/Country`, `Hubgo/Shipping_Calculator/Destination` and `Hubgo/Shipping_Calculator/Zone`.
- New delivery filters and action: `Hubgo/Delivery/Promise_Days`, `Hubgo/Delivery/Carrier_Meta_Keys`, `Hubgo/Delivery/Overdue_Enabled`, `Hubgo/Delivery/Overdue_Grace_Days`, `Hubgo/Delivery/Overdue_Query` and `Hubgo/Delivery/Promise_Saved`.
- New integration filters: `Hubgo/Integrations/Cards`, `Hubgo/Integrations/Card`, `Hubgo/Integrations/Categories`, `Hubgo/Integrations/Melhor_Envio/Plugin_Files`, `Hubgo/Integrations/Melhor_Envio/Package_Url` and `Hubgo/Integrations/Melhor_Envio/Skip_Compositions`.
- New admin and core filters: `Hubgo/Admin/Integrations/Cards`, `Hubgo/Admin/Integrations/Bootstrap_Data`, `Hubgo/Admin/System_Status`, `Hubgo/Core/Assets/Admin_Pages`, `Hubgo/Core/License/Payload` and `Hubgo/Core/Plugin_Installer/Allowed_Hosts`.
- `Core\License::allows_updates()` and `::allows_downloads()`, the two permissions every update surface now asks instead of `is_active()`, so MDS can keep serving a site whose key has lapsed.
- Both permissions travel in the license summary, so a screen never has to infer them from the license status.
- `Hubgo/Core/Update_Checker/Payload` gained `allows_updates`, `allows_downloads` and `can_install`, so a release announced without a package says an active license is needed instead of offering a dead update link.
- The License screen tells a customer whose key is not active that the site is still receiving updates, when that is what the server says.
- `Requires at least: 6.0` in the plugin header, matching the product metadata registered on MDS.

### Changed

- **BREAKING:** the settings interface was rewritten in Vue 3 + Vite, and the server-rendered settings form is gone.
- **BREAKING:** every legacy AJAX action was replaced by the `hubgo/v1` REST API, so anything hooked to the old `admin-ajax.php` actions has to move.
- Compatibility with Joinotify v2 and its functional API for triggers, placeholders and workflow dispatch.
- The admin fields were standardised on one height, radius and border, with a square swatch on the colour picker and focus as a 2px primary border without a shadow.
- `Hubgo/Shipping_Calculator/Rates` now receives the matched zone as a third argument.
- MDS PHP SDK to `^1.3`, whose update check is no longer barred by the license being valid but by the update gate the server answers with.
- The SDK's license notices and its auto-registered submenu are switched off through the `features` map it exposes.
- License activation is off and no longer takes the update check with it: HubGo registers under the SDK's `updates_only` preset.
- `License::is_configured()` answers for the MDS credentials alone, so the "Check for updates" link no longer disappears with activation.
- `License::get_license_url()` returns an empty string while there is no License screen to link to.

### Removed

- The bespoke update checker, which polled a static JSON on packages.meumouse.com.
- The license activation flow, while `License::ENABLED` stays off; nothing was deleted, and the constant brings all of it back.
- The Pro tier on the integrations catalog, so the Elementor widget no longer vanishes from pages already using it when a key lapses.

### Fixed

- The shipping calculator ignored shipping zone priority, because the destination state came from the customer session instead of the informed postcode.
- The shipping calculator changed the customer's cart, adding and removing the product, to evaluate free shipping.
- The shipping calculator overwrote the billing and shipping postcode saved in the customer session.
- Free shipping was injected into the table by hand, ignoring the zone, the `woocommerce_package_rates` filter and the option to hide the other rates.
- The calculation wrote to the same session cache used by the checkout.
- An invalid postcode returned the "no shipping method available" message instead of a validation error.
- The plugin silently changed WooCommerce's `woocommerce_default_customer_address` option.
- Frenet: the calculator quoted with a declared value of R$ 0.00, so services priced by declared value never appeared.
- Frenet: the product page showed two calculators, and a new "Hide the Frenet simulator" option turns the other one off.
- Frenet: "Import tracking" now has a real effect, filling the provider and the carrier name at display time without writing to the database.
- Melhor Envio: HubGo's calculator did not work with the plugin active, because the package never identified itself as a product-page quote.
- Melhor Envio: "Import tracking" now has a real effect, importing the code as soon as the plugin writes it and firing the e-mails and automations.
- Melhor Envio: "Mark as shipped" now has a real effect, moving the order to "Order shipped" once per order and leaving cancelled, refunded and failed orders alone.
- Melhor Envio: the product page showed two calculators, and a new "Hide the Melhor Envio simulator" option turns the other one off.
- Melhor Envio: bundles and composite products got the wrong quote, so those methods are left out of the calculator rather than showing a price the store cannot honour.
- Melhor Envio: the delivery time appeared twice on the calculator, once as a promised date and once inside the shipping method's name.

### Security

- REST endpoints now require a capability check plus the `wp_rest` nonce.

## [2.2.0] - 2026-03-12

### Added

- "Order shipped" status in the bulk actions of the orders page.

### Fixed

- Orders in the "Order shipped" status were not listed on the orders page.

## [2.1.0] - 2026-03-06

### Added

- New order status: "Order shipped".
- Order metabox for entering the tracking code.
- Joinotify integration.

## [2.0.0] - 2026-02-25

### Changed

- Architecture change to MACI (Modular Autoload Class Initialization).
- Optimizations.

### Fixed

- Melhor Envio compatibility.

## [1.4.0] - 2025-08-11

### Fixed

- Loading of the shipping options.

## [1.3.0] - 2024-02-16

### Added

- Automatic shipping calculation.

### Changed

- Optimizations.

### Fixed

- Bug fixes.

## [1.2.6] - 2023-12-18

### Fixed

- Bug fixes.

## [1.2.5] - 2023-10-31

### Added

- WooCommerce High Performance Order Storage (HPOS) compatibility.

## 1.2.0 - 2023-10-09

### Changed

- Optimizations.

### Fixed

- Bug fixes.

## 1.1.7 - 2023-07-26

### Changed

- Optimizations.

### Fixed

- Bug fixes.

## 1.1.5 - 2023-07-19

### Fixed

- Bug fixes.

## 1.1.0 - 2023-07-14

### Fixed

- Bug fixes.

## 1.0.0 - 2023-07-13

### Added

- Initial release.

[Unreleased]: https://github.com/meumouse/hubgo/compare/v3.1.0...HEAD
[3.1.0]: https://github.com/meumouse/hubgo/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/meumouse/hubgo/compare/v2.2.0...v3.0.0
[2.2.0]: https://github.com/meumouse/hubgo/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/meumouse/hubgo/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/meumouse/hubgo/compare/v1.4.0...v2.0.0
[1.4.0]: https://github.com/meumouse/hubgo/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/meumouse/hubgo/compare/v1.2.6...v1.3.0
[1.2.6]: https://github.com/meumouse/hubgo/compare/1.2.5...v1.2.6
[1.2.5]: https://github.com/meumouse/hubgo/releases/tag/1.2.5
