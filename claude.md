# Claude Instructions — Treatment Packages & Deposits Plugin (WordPress + WooCommerce)

You are assisting in building a **WordPress plugin** that extends **WooCommerce** by adding:

- Treatment services  
- Multiple-session packages  
- Automatic deposit payments  
- Session tracking after purchase  

The plugin must **reuse WooCommerce’s existing payment gateways** — no new gateways.

---

# 1. PROJECT GOAL

Build a plugin that allows clinics/salons to sell treatment packages online:

- Each treatment has multiple purchase options (Pay as you go, Course of 6, Course of 8, etc.).
- More sessions = cheaper per-session price.
- Customer pays **deposit** at checkout (fixed or %).
- Remaining balance stored for manual or later payment.
- Customer’s sessions tracked inside admin.

This plugin must look and behave like a **WooCommerce extension**, not a standalone system.

---

# 2. PLUGIN ARCHITECTURE

### ROOT FOLDER:
```
treatment-packages-deposits/
```

### REQUIRED STRUCTURE:

```
treatment-packages-deposits.php
claude.md
src/
  Plugin.php
  DB/
    Installer.php
  PostTypes/
    TreatmentPostType.php
    TreatmentTaxonomies.php
  Packages/
    PackageModel.php
    PackageRepository.php
    PackageAdminUI.php
  Woo/
    ProductsSync.php
    CartHandler.php
    OrderHandler.php
  Customer/
    CustomerPackagesRepository.php
  Frontend/
    Shortcodes.php
  Admin/
    AdminMenu.php
assets/
  css/frontend.css
  js/frontend.js
```

---

# 3. DEVELOPMENT RULES FOR CLAUDE

Whenever I ask for code:

### ✔ Provide **full file content**  
Not fragments unless asked.

### ✔ Include the file path at the top  
Example:
```php
// File: src/PostTypes/TreatmentPostType.php
```

### ✔ Follow namespaces:
```
namespace TreatmentPackages\{Subfolder};
```

### ✔ Use WordPress standards:
- escape outputs (`esc_html`, `esc_attr`)
- sanitize inputs
- capability checks (`manage_options` or `manage_woocommerce`)
- WP nonces for all saving actions

### ✔ Use WooCommerce APIs correctly  
- Use `WC()->cart->add_to_cart()`  
- Adjust deposit in `woocommerce_before_calculate_totals`  
- Listen to order hooks (`woocommerce_thankyou`)

---

# 4. FUNCTIONAL REQUIREMENTS

## 4.1 Custom Post Type: “Treatment”
- CPT: `treatment`
- Supports:
  - Title
  - Editor
  - Featured image
- Custom meta:
  - default deposit (optional)

### TAXONOMIES:
- `treatment_category`
- (optional) `treatment_area`

---

## 4.2 Packages (Sessions + Pricing)

Each treatment can have multiple packages:

### FIELDS:

| Field | Description |
|-------|-------------|
| sessions | number of sessions |
| total_price | total package price |
| per_session_price | automatically computed |
| discount_percent | automatically computed |
| deposit_type | none / fixed / percentage |
| deposit_value | amount or % |
| wc_product_id | WooCommerce product mapping |
| sort_order | sorting in UI |

### Database Table: `wp_tp_packages`

Columns required:
```
id BIGINT primary key
treatment_id BIGINT
name VARCHAR
sessions INT
total_price DECIMAL
per_session_price DECIMAL
discount_percent DECIMAL
deposit_type VARCHAR
deposit_value DECIMAL
wc_product_id BIGINT
sort_order INT
created_at DATETIME
updated_at DATETIME
```

---

## 4.3 WooCommerce Integration

### Product Syncing
For each package:

- Create/update a **hidden WooCommerce SIMPLE product**:
  - `virtual = true`
  - `price = total_price`
- Store Woo product ID in DB.

### Deposits

When package added to cart:

- Calculate deposit
- Adjust cart item price to deposit
- Save:
  - Total price
  - Deposit paid
  - Remaining balance

---

## 4.4 Order Handling

When WC order is completed:

- Detect items with package meta
- Insert into `wp_tp_customer_packages`:
  - user_id
  - treatment_id
  - package_id
  - sessions purchased
  - deposit paid
  - remaining balance
  - sessions_remaining = sessions

---

## 4.5 Frontend (Shortcode)

Shortcode:  
```
[treatment_packages category="women-packages"]
```

Shows:

- Treatment card
- "From £X per session"
- "Up to XX% off"
- Package dropdown
- “Order” button → add associated WC product to cart.

---

# 5. DEVELOPMENT PHASES FOR CLAUDE

When I request next steps, follow this sequence:

---

### PHASE 1 — Scaffold plugin
- Create main plugin file + autoloader.
- Create `Plugin.php`, register components.

---

### PHASE 2 — DB Installer
- Create both required tables using `dbDelta()`.

---

### PHASE 3 — Treatment CPT + Taxonomies

---

### PHASE 4 — Package Repository + Model

---

### PHASE 5 — Admin UI for Packages
- Meta box on Treatment edit screen.
- Repeater UI.
- Save logic.

---

### PHASE 6 — WooCommerce Product Sync

---

### PHASE 7 — Deposits + Cart Handling

---

### PHASE 8 — Order Handler (Session record creation)

---

### PHASE 9 — Frontend shortcode + UI

---

### PHASE 10 — Admin “Customer Packages” screen

---

# 6. NON-GOALS (Do NOT implement yet)

- No booking calendar  
- No automatic remaining balance charging  
- No Gutenberg blocks  
- No Vue/React build tools  

---

# 7. HOW TO RESPOND

When I ask:  
👉 “Generate file ___”  
Claude should produce **complete code** with full paths.

When I ask:  
👉 “Continue with next phase”  
Claude should proceed sequentially.

When I ask conceptual questions:  
👉 Provide short, clear reasoning.

---

# END OF SPEC
