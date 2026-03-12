=== TreatPack - Treatment Packages for WooCommerce ===
Contributors: nuxtor
Donate link: https://niftycs.uk
Tags: woocommerce, treatments, packages, deposits, sessions
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 10.0
WC tested up to: 10.3.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell treatment packages with multi-session pricing, automatic deposit calculations, and session tracking — powered by WooCommerce.

== Description ==

**TreatPack** lets clinics, salons, spas, and wellness businesses sell treatment packages online with flexible session-based pricing and automatic deposit handling.

Built as a native WooCommerce extension, it uses your existing payment gateways — no extra setup needed.

= Key Features =

* **Treatment Packages** — Create treatments with multiple package options (e.g. Pay As You Go, Course of 6, Course of 8)
* **Volume Discounts** — More sessions = lower per-session price, with automatic discount calculation
* **Automatic Deposits** — Set fixed or percentage-based deposits per package; customers pay a deposit at checkout with the balance recorded
* **Session Tracking** — Track remaining sessions per customer after purchase
* **Balance Management** — Record payments against outstanding balances from the admin panel
* **WooCommerce Native** — Uses your existing WooCommerce cart, checkout, and payment gateways
* **Shortcode Display** — Show treatment packages on any page with flexible shortcodes
* **Category Filtering** — Organize treatments by category with sidebar navigation
* **Import / Export** — Bulk import and export treatments and packages via JSON
* **HPOS Compatible** — Fully compatible with WooCommerce High-Performance Order Storage

= How It Works =

1. Create treatments as a custom post type (like products)
2. Add packages to each treatment with session counts and pricing
3. The plugin automatically creates hidden WooCommerce products for each package
4. Display treatments on your site using shortcodes
5. Customers select a package, pay the deposit at checkout
6. Sessions and balances are tracked in the admin dashboard

= Shortcodes =

**Display all treatments:**
`[treatment_packages]`

**Display treatments from a specific category:**
`[treatment_packages category="facials"]`

**Display treatments from multiple categories:**
`[treatment_packages category="facials,body-treatments"]`

**Display specific treatments by ID:**
`[treatment_packages ids="12,45,67"]`

**Display a single treatment:**
`[treatment_single id="123"]`

= Shortcode Attributes =

* `category` — Filter by treatment category slug(s), comma-separated
* `ids` — Show specific treatment IDs, comma-separated
* `columns` — Number of columns (default: 3)
* `show_sidebar` — Show category sidebar: yes/no (default: yes)
* `show_intro` — Show intro text: yes/no (default: yes)
* `intro_text` — Custom intro text above the grid
* `orderby` — Sort by: menu_order, title, date (default: menu_order)
* `order` — Sort direction: ASC or DESC (default: ASC)

= Who Is This For? =

* Beauty salons and clinics
* Laser treatment centres
* Physiotherapy and sports therapy practices
* Dental clinics offering treatment courses
* Spas and wellness centres
* Any service business selling multi-session packages

= Requirements =

* WordPress 6.6 or higher
* WooCommerce 10.0 or higher
* PHP 7.4 or higher

== Installation ==

1. Upload the `treatpack` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure WooCommerce is installed and active
4. Go to **Treatments** in the admin menu to create your first treatment
5. Add packages with session counts and pricing to each treatment
6. Use the `[treatment_packages]` shortcode on any page to display your treatments

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. Treatment Packages & Deposits is a WooCommerce extension and requires WooCommerce 10.0 or higher to function.

= How do deposits work? =

When you create a package, you can set a deposit as a fixed amount or a percentage of the total price. At checkout, the customer only pays the deposit. The remaining balance is recorded and can be managed from the admin panel.

= Can I have treatments without deposits? =

Yes. Set the deposit type to "None" on any package and the customer will pay the full price at checkout.

= How is session tracking managed? =

After a customer completes an order, a record is created in the Customer Packages admin screen. From there, you can mark sessions as used and record additional payments against the balance.

= Can I import treatments in bulk? =

Yes. The plugin includes an Import/Export tool under **Treatments > Import/Export**. You can export all treatments and packages to JSON and import them on another site.

= Does it work with my payment gateway? =

Yes. The plugin uses WooCommerce's native cart and checkout, so it works with any payment gateway that WooCommerce supports — Stripe, PayPal, bank transfer, etc.

= Is it compatible with WooCommerce HPOS? =

Yes. The plugin declares full compatibility with WooCommerce High-Performance Order Storage (Custom Order Tables).

= Can I display treatments from multiple categories? =

Yes. Use comma-separated category slugs: `[treatment_packages category="facials,body"]`

== Screenshots ==

1. Treatment packages displayed on the frontend with category sidebar
2. Package pricing dropdown with session options and discounts
3. Admin treatment editor with package management
4. Customer packages admin screen with session tracking
5. Import/Export interface for bulk treatment management

== Changelog ==

= 1.0.0 =
* Initial release
* Treatment custom post type with categories and areas
* Multi-session package management with volume discounts
* Automatic deposit calculation (fixed or percentage)
* WooCommerce product sync for seamless checkout
* Session tracking and balance management
* Frontend shortcodes with category filtering
* Admin customer packages dashboard
* Import/Export functionality (JSON)
* HPOS compatibility

== Upgrade Notices ==

= 1.0.0 =
Initial release. Install WooCommerce 10.0+ before activating.
