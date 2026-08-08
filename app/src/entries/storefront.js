/**
 * Storefront entry: the shipping calculator rendered on the shop.
 *
 * Separate from the admin entries because it loads its own plain stylesheet —
 * the Tailwind build is scoped under `.hubgo-app` and would be inert out here.
 *
 * @since 3.1.0
 */
import '../storefront/styles/calculator.css';
import { boot } from '../storefront/mount';

boot();
