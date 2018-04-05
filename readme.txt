=== Markup by Attribute for WooCommerce ===
Contributors:         MarkTomlinson
Donate link:          https://www.paypal.me/MT2Dev/15
Tags:                 WooCommerce, Attribute, Term, Variation, Markup, Markdown, Mark-up, Mark-down, Price
Version:              1.2.0
Requires at least:    4.6
Tested up to:         4.9.5
Stable tag:           4.3
Requires PHP:         5.2.4 or later
License:              GPLv3
License URI:          https://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 3.0
WC tested up to:      3.3.4

This plugin adds product variation markup by attribute to WooCommerce and adjusts product variation regular and sale prices accordingly.

== Description ==

WooCommerce does not have the native ability to specify a price markup or markdown in an attribute term and apply that markup or markdown when setting the price of variations. Various plugins attempt to mitigate the problem by applying metadata to simple products, but that creates its own set of problems -- especially in regard to managing inventory. This plugin adds product variation price markup by attribute term to WooCommerce.

  * Can create a fixed value markup (such as $5), or a percentage markup (such as 5%).
  * The markup value can be positive yielding an increase in price, or negative yielding a decrease in price.
  * Uses familiar WooCommerce bulk edit actions "Set regular price" and "Set sale price".
  * Writes a breakdown of the price modifications in the variation description so the itemization is visible to the customer.
  * Puts the price increase (or decrease) in the options drop-down box along side of the terms.

== Installation ==

= Manual =
1. Download the Markup by Attribute for WooCommerce ZIP file.

2. Unzip the plugin files to the `/wp-content/plugins/markup-by-attribute-for-woocommerce` directory.

3. Activate the plugin through the 'Plugins' page in Wordpress.

= Automated =
1. Locate the Markup by Attribute for WooCommerce plug in on the 'Plugins' => 'Add New' page in Wordpress using the search box.

2. Install the plugin using the 'Install' button.

3. The 'Install' button will change to an 'Activate' button. Use it to activate the plugin.

== Using Markup by Attribute for WooCommerce ==
1. Install and activate the plugin according to the Installation instructions.

2. Create variation attributes and terms if you haven't already. For instance, the attribute 'Style' may include the terms 'Turtleneck', 'V-neck', 'Button-down', and 'Polo'.

3. While creating new terms or editing existing ones, add the markup.
  * For each term, consider whether a markup or markdown is needed. Adding a logo to a shirt might increase the cost by $5. Extra small shirts might be 10% less.
  * Put the amount of the markup in the term's Markup field on either the 'Add new...' panel or the 'Edit...' panel. Examples of valid values are:
    - -5
    - 5.95
    - +5.67
    - 7.5%
    - -12%
  * Strings that are not recognized as numbers (including percentages) will be ignored. Markups with several numbers below the decimal will be rounded to the second decimal place (hundreds) before being saved.

5. Once you create your product variations, use the "Set regular price" and "Set sale price" as you normally would.
  * The markup will be applied to the price according to the term associated with the variation.
  * The variation description will be overwritten with text to note the markup.
  * If you change the markup at a later date, repeat this step to recalculate the markup for this product, or do not repeat the step to leave the previous markups for this product.

== Frequently Asked Questions ==

= Does this support languages other than English, or currency other than the US dollar? =
Not at this time. I will be adding internationalization in a future release.

= If I change the markup for an attribute, how will product prices change? =
Markup by Attribute works within the framework provided by WooCommerce and sets product variation markups (or markdowns) during the 'Set regular price' and 'Set sale price' actions. Therefore, you must locate the products affected by this change and reset the regular and sale prices. I'm working on adding an "Attribute" column to the Product list to facilitate this type of activity.

= What if I change an attribute's markups but do not want to change products marked up previously? =
Then do nothing. Prices, descriptions, and option drop-downs for products will remain at whatever value they were set to last time you ran the 'Set regular price' or 'Set sale price' bulk variation activities.

= I'd like to donate. =
Thanks! The donation button assumes $15.00 USD. But feel free adjust that amount up or down as you feel it's appropriate. I'm a retired guy who's living off his savings, so every little bit helps. Besides, I need the motivation!

== Screenshots ==
1. Note the addition of the "Markup (or markdown)" field on the bottom of the 'Add new...' panel of the attribute editor.
2. Note the addition of the "Markup (or markdown)" field on the bottom of the 'Edit...' panel of the attribute editor.
3. The regular price is $18.95. Markup by Attribute added $6 for a logo and $1.42 for extra large.
4. The customer sees the full range of sale prices available and how much each option costs, plus a clear description of the breakdown.
5. Markdowns (negative markups) can be used as well.
6. Markups are applied to sale prices just as they are to regular prices.

== Changelog ==

= 1.2.0 =
* Change backend-attrb and backend-product to allow percentage markup.
* Change backend-product and frontend to store actual product-attribute markups in postmeta.

= 1.1.1 =
* Added code to class backend-attrb to prevent adjusting the price when price field is NULL.

= 1.1 =
* Moved markup from term Description to new metadata field.
* Added metadata field to term Add and Edit panels.
* Broke class-mt2-markup-backend.php into class-mt2-markup-backend-attrb.php and class-mt2-markup-backend-product.php for supportability.
* Cleaned code and added comments for readability.

= 1.0 =
* Initial version.

== Upgrade Notice ==

= 1.2.0 =
* Now allows the use of percentage (5%) markups and markdowns as well as fixed values ($5).
* Store actual markup value displayed in attribute drop-down with the product.
  - This allows changing of the markup in the attribute without affecting the markup displayed with the product.
  - This allows displaying the actual calculated markup value on product attribute drop-down when the markup is a percentage.

= 1.1.1 =
Prevents null prices (due to apparent bug in WooCommerce sale_price) from being adjusted with a markup.

= 1.1 =
* Markup is now stored in metadata, freeing up the Description field. Edits are added to the code so the markup will always be stored in the correct format.
* Code improvements ad to robustness and supportability.

= 1.0 =
Initial version