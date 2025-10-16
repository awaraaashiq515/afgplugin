Army Fitness & Training — eCommerce + Internal Kitchen + Subscription 
Management (WordPress + WooCommerce)

2 — High-level goals
Sell supplements and packages online.
Manage internal “Deshi Kitchen” stock & daily sales for trainees (onsite 
consumption).
Support monthly recurring charges for trainees staying/training.
Notify customers and admins via WhatsApp on key events (new order, payment 
received, credit over-limit, monthly invoice/renewal).
Allow users to submit queries on-site and track them in the admin panel.
Simple, mobile-first UI with fast checkout and secure payments.
3 — Main user types & roles
Guest / Customer — browse products, buy online, apply for training or 
packages, view order history.
Trainee (Customer with package) — buys subscription/meal packages; may 
have internal ledger/credit for kitchen items.
Kitchen Cashier / Counter Staff — record daily consumption by trainees, 
issue bills, mark payments.
Admin (Site Owner) — manage products, packages, inventory, orders, 
students, reports.
Accountant / Billing — monitor monthly charges, reconcile payments, export 
reports.
Support / Query Manager — respond to user queries via WP dashboard.
4 — Core features (what the website must do)
A. eCommerce (public-facing)
Product catalog for supplements (protein, shakes, bars, accessories) with 
categories, attributes (weight, flavor), stock status.
Product pages with images, description, SKU, nutrition facts.
Cart & checkout (guest + registered).
Payment gateway integration (Razorpay / Stripe / PayPal — choose per 
region).
Order confirmation, invoice generation (PDF), order history.
B. Deshi Kitchen (internal sales + inventory)
Internal product list (daily consumables, tea, shakes, snacks) — separate 
from public shop or overlap with visibility flags.
POS-like quick-sell UI for kitchen staff: select trainee, add items, 
record consumption, optionally accept payment in cash or add to trainee’s 
internal credit.
Trainee Ledger / Wallet: balance, charges (consumption), payments 
(cash/online/other), statement.
Auto-bill option: at end of month, generate consolidated invoice for 
trainee (accommodation + training package + kitchen charges).
Stock tracking: decrement inventory on both public orders and internal 
sales; low-stock alerts.
C. Packages & Subscriptions
Training packages (daily/weekly/monthly), accommodation packages (room + 
meals), special bootcamp packages.
Recurring billing / subscriptions for monthly packages (charge monthly or 
manual invoicing + webhook-based payment confirmation).
Option to add one-off add-ons (nutrition consultation, physiotherapy).
Student enrollment flow: apply online → admin approves → package 
activated.
D. Payments & Credit Controls
Support for online payments via chosen payment gateway(s).
Track payment status; send WhatsApp/SMS/email notifications on payment 
success, failed payment, invoice due.
Credit threshold alerts: if trainee credit (owed) exceeds configurable 
limit, notify admin and the trainee, and optionally block further kitchen 
purchases until resolved.
Payment reconciliation / manual payment entry (for cash payments).
E. Notifications (WhatsApp)
WhatsApp messages for: order placed, payment success, subscription 
renewal, monthly invoice, low stock, credit over-limit.
Use official WhatsApp Business Cloud API or Twilio WhatsApp for sending 
messages (via backend webhook).
Keep templates configurable in admin panel.
F. Support & Queries
Frontend "Apply / Query" form where users/students submit issues/requests.
Ticket list in admin with status (New, In Progress, Resolved).
Email + WhatsApp notification to support when a new query arrives.
Option to attach documents/images.
G. Admin Dashboard & Reports
Unified dashboard: revenue, outstanding trainee balances, low-stock items, 
active subscriptions, today’s kitchen sales.
Reports: daily kitchen sales, monthly subscription income, outstanding 
receivables, product-wise sales.
Export CSV / PDF.
H. Security & Operations
SSL, secure payment handling, role-based access control, backups, logging, 
GDPR-compliant opt-in for messages.
Daily backups and staging environment recommended.
5 — Suggested WordPress architecture & plugins
(You can use these as-is or build custom plugin for specialized 
workflows.)

WordPress + WooCommerce — product catalogue, checkout, orders.
WooCommerce Subscriptions — recurring billing for monthly training 
packages.
WooCommerce Memberships (optional) — manage trainee access/benefits.
Advanced Custom Fields (ACF) — add custom fields to trainee profile (room, 
package).
WP User Manager / Profile Fields — enhanced user/trainee profiles.
Custom plugin (recommended) — “Deshi Kitchen Manager” to handle internal 
POS, ledger, monthly invoices, credit limit enforcement, and integration 
with WooCommerce inventory.
WPNotif or custom integration — for WhatsApp & SMS notifications (or use 
Twilio / WhatsApp Cloud API directly).
Razorpay / Stripe / PayPal plugin for payments depending on target market.
WP All Export / WP All Import — for data export/import.
WP Mail SMTP — for reliable emails.
WP Activity Log — audit trail for admin actions.
UpdraftPlus / Jetpack backup — backups.
User Role Editor — customize admin roles.
WooCommerce POS (optional) — if you want a ready in-browser POS for 
Kitchen.
Note: For reliable WhatsApp messaging for transactional messages use 
Meta’s WhatsApp Business Cloud API or Twilio’s WhatsApp channel (requires 
approval and phone number setup). Messages must conform to WhatsApp 
templates for non-session messages (like invoices) — templates are 
pre-approved.
6 — Database / data model (simplified)
You can implement this largely with WooCommerce tables plus a custom 
plugin with these tables:

wp_users + profile meta
wp_posts (WooCommerce products & orders)
wp_postmeta (product metadata)
wp_deshi_products — id, title, sku, price, is_public, stock_qty, unit
wp_deshi_sales — id, product_id, trainee_id, qty, price, sale_date, 
payment_method, payment_status
wp_trainee_ledger — id, trainee_id, type (charge/payment), amount, 
balance_after, note, created_at
wp_subscriptions (extend WooCommerce subscriptions or custom) — 
trainee_id, package_id, start_date, next_billing_date, status
wp_kitchen_stock_movements — product_id, change_qty, reason, created_at
wp_queries — id, user_id, subject, message, attachments, status, 
admin_notes, created_at
7 — Admin workflows (how staff will actually use it)
Kitchen sale (counter)
Cashier logs into kitchen POS screen.
Selects trainee (search by name or scan ID) or “walk-in”.
Adds items — system reduces wp_deshi_products stock and adds a 
wp_deshi_sales record.
Choose payment method:
Cash: record payment, update trainee ledger (payment entry).
Add to credit: create ledger entry (charge), update trainee balance.
Online: if linked to payment terminal, confirm online payment and update 
ledger.
If trainee credit > limit, show warning and optionally block adding items.
Monthly billing
Admin reviews generated monthly consolidated invoices (accommodation + 
package fee + kitchen charges).
System emails/WhatsApps invoice with payment link.
On payment gateway webhook, mark invoice paid and send confirmation.
Low stock & reorder
Admin receives low-stock alerts (email + WhatsApp).
Admin can generate purchase orders (basic CSV/print) for suppliers.
8 — WhatsApp message templates (examples)
(Use approved templates for non-session messages)

Order confirmation:
 Hi {name}, your order #{order_id} for {brief_items} has been placed. 
Amount: {amount}. Thank you!
Payment received:
 Payment received for invoice #{invoice_id}. Amount: {amount}. Thank you — 
your balance is now {balance}.
Monthly invoice:
 Hi {name}, your monthly invoice of {amount} is ready, due {due_date}. Pay 
now: {payment_link}
Credit over-limit:
 Alert: Your account balance {balance} exceeds the allowed limit of 
{limit}. Please settle to continue kitchen purchases.
Low stock (to admin):
 Low stock: {product_name} — {remaining_qty} left.
9 — UX / Pages (site structure)
Home (hero: training & packages + shop highlights)
Shop (supplements)
Deshi Kitchen (menu for trainees — internal)
Packages (training + accommodation + subscription CTA)
Trainee Portal / Dashboard (subscription status, ledger, invoices, order 
history)
Kitchen POS (admin-only page)
Support / Apply (query form)
Admin Dashboard (custom widgets: balances, renewals, sales)
Privacy, Terms, Contact
10 — Acceptance criteria (how to know it’s done)
Customers can buy online and receive confirmation emails and WhatsApp 
messages.
Kitchen staff can record internal sales and trainee ledger updates.
Inventory syncs correctly for both public orders and internal sales.
Monthly invoices for trainees are generated and can be paid online.
WhatsApp messages are sent successfully on the defined events.
Credit threshold blocks further purchases + notifies when exceeded.
Admin can view daily sales, subscriptions, and outstanding trainee 
balances.
11 — Non-functional requirements
Mobile-first responsive design.
PCI-compliant checkout (don’t store card numbers on server).
Fast load times (cache + optimized images).
Role-based access with audit logs.
Backup & restore plan.
12 — Implementation notes & integration choices
Payments: For India consider Razorpay + Razorpay Subscriptions; for 
international Stripe is reliable. Use their official WooCommerce plugins.
WhatsApp: Use WhatsApp Cloud API (Meta) or Twilio. Implement a small 
middleware/service (can be a lightweight PHP service) that receives events 
(order created, payment success) and calls WhatsApp templates.
Subscriptions: WooCommerce Subscriptions handles recurring payments + 
renewal webhooks.
Custom plugin: Build one plugin "deshi-kitchen" for POS, ledger, monthly 
invoices, credit rules, and kitchen-specific reports. Keep logic separated 
from WooCommerce core for maintainability.
Webhook handling: Implement endpoints to receive payment gateway webhooks 
that will update order/subscription status and call notification service.
Testing: test payment flows (success, failure, partial), stock concurrency 
(two sales at once), and notification delivery.
13 — Sample product description (you can reuse)
Protein Whey 2kg — Army Strength Blend

High-quality whey protein blend tailored for intense training: 24g protein 
per scoop, 5.5g BCAAs, low sugar. Ideal post-workout for muscle recovery. 
Available in chocolate & vanilla. SKU: AF-PW-2KG.
