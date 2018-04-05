=== Markup by Attribute for WooCommerce ===
Contributors: MarkTomlinson
Donate link: https://www.paypal.me/MT2Dev/15
Tags: WooCommerce, Attribute, Term, Variation, Markup, Markdown, Mark-up, Mark-down, Price
Requires at least: 4.6
Tested up to: 4.9.4
Stable tag: 4.3
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 3.0
WC tested up to: 3.3.1

This plugin adds product variation markup by attribute to WooCommerce and adjusts product variation regular and sale prices accordingly.

== Description ==

WooCommerce does not have the native ability to specify a price markup or markdown in an attribute term and apply that markup or markdown when setting the price of variations. Various plugins attempt to mitigate the problem by applying metadata to simple products, but that creates its own set of problems -- especially in regard to managing inventory. This plugin adds product variation price markup by attribute term to WooCommerce.

  * Can create a fixed value markup (such as $5), or a percentage markup (such as 5%).
  * The markup value can be positive yeilding an increase in price, or negative yeilding a decrease in price.
  * Uses familiar WooCommerce bulk edit actions "Set regular price" and "Set sale price".
  * Writes a breakdown of the price modifications in the variation description so the itemization is visible to the customer.
  * Puts the price increase (or decrease) in the options drop-down box along side of the terms.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/markup-by-attribute-for-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.

2. Activate the plugin through the 'Plugins' screen in WordPress.

3. Create variation attributes and terms, if you haven't already. For instance, the attribute 'Style' may include the terms 'Turtleneck', 'V-neck', 'Button-down', and 'Polo'.

4. While creating new terms or editing existing ones, add the markup.
  * For each term, consider whether a markup or markdown is needed. V-neck shirts might need to be marked down by a dollar or two, while button-down shirts might require a markup of a couple dollars.
  * Put the amount of the markup in the term's Markup field on either the 'Add new...' panel or the 'Edit...' panel. Any floating point number is valid; "5", "5.95", "+5.00", "-5", and "-1.2345" are all good.
  * Strings that are not recognized as numbers will be ignored. Markups with several numbers below the decimal will be rounded to the second decimal place (hundreds) before being saved.

5. Once you create your product variations, use the "Set regular price" and "Set sale price" as you normally would.
  * The markup will be applied to the price according to the term associated with the variation.
  * The variation description will be overwritten with text to note the markup.
  * IMPORTANT NOTE: If you change the markup afterward, you *must* set the variation prices again. If you do not, the drop-down box the customer sees and the price that is calculated will not match.

== Frequently Asked Questions ==

= Does this support languages other than English, or currency other than the US dollar? =

Not at this time. I will be adding internationalization in a future release.

= If I change the markup for an attribute, how will product prices change? =

Markup by Attribute works within the framework provided by WooCommerce and sets product variation markups (or markdowns) during the 'Set regular price' and 'Set sale price' actions. Therefore, you must locate all products affected by this change and reset the regular and sale prices. While this sounds cumbersome, it is a massive improvement over finding each and every variation, doing the math for each variation, and changing them manually. I'm working on adding an "Attribute" column to the Product list to facilitate this type of activity.

= I'd like to donate. =

Thanks! The donation button assumes $15.00 USD. But feel free adjust that amount up or down as you feel it's appropriate. I'm a retired guy who's living off his savings, so every little bit helps.

== Screenshots ==
1. Note the addition of the "Markup (or markdown)" field on the bottom of the 'Add new...' panel of the attribute editor.
2. Note the addition of the "Markup (or markdown)" field on the bottom of the 'Edit...' panel of the attribute editor.
3. The regular price is $19.95 and the sale price is $15.95. Markup by Attribute added $75 for "Holstein" and $4.00 for "2004".
4. The customer sees the full range of sale prices available and how much each option costs, plus a clear description of the breakdown.
5. Notice that markdowns (negative markups) can be used as well.

== Changelog ==

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

= 1.1.1 =
Prevents null prices (due to apparent bug in WooCommerce sale_price) from being adjusted with a markup.

= 1.1 =
* Markup is now stored in metadata, freeing up the Description field. Edits are added to the code so the markup will always be stored in the correct format.
* Code improvements ad to robustness and supportability.

= 1.0 =
Initial version