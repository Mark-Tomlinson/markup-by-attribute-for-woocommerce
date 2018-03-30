=== Markup by Attribute for WooCommerce ===
Contributors: MarkTomlinson
Donate link: Haven't got one yet.
Tags: WooCommerce, Attribute, Markup
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

This plugin adds product variation markup by attribute to WooCommerce -- the ability to add a markup (or markdown) to an attribute term and have that change the regular and sale price of the associated product variations.

When migrating a store from another online commerce system to WooCommenrce, we ran into a shortcoming of WooCommerce -- the inability to specify a markup or markdown in an attribute term and apply that markup or markdown when setting the price of variations. Various plugins attempt to mitigate the problem with metadata, but that creates its own set of problems.

So, this plugin does one thing -- If you enter a numeric value in an attribute term description, that value will be applied to the price of the associated variation during the bulk edit actions "Set regular price" and "Set sale price". When setting the price, it adds explanative text to the variation description which WooCommerce will display as the variation is selected. The option dropdown will also contain the markup or markdown so the customer can estimate the final price while deciding options.

This plugin is intended for large sets of variations were customizing the price for each is not practical. So, the maximum number of variations that can be created at a time is also raised from 50 to 250. WooCommerce allows us to create variations over 50 by running "Create variations from all attributes" several times. However, this creates additional work reorganizing the variations if the variations are expected to be in a specific order.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mt2-markup-by-attribute` directory, or install the plugin through the WordPress plugins screen directly.
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

== Screenshots ==
1. Note the addition of the "Markup (or markdown)" field on the bottom of the 'Add new...' panel of the attribute editor.
2. Note the addition of the "Markup (or markdown)" field on the bottom of the 'Edit...' panel of the attribute editor.
3. The regular price for variations was set to $19.95 and the sale price was set at $15.95. For this variation, Markup by Attribute added $75 for "Holstein" and $4.00 for "2004". The description field breaks this down for the customer.
4. The customer sees the full range of sale prices available and how much each option costs. Because the description was filled with an explanation, they will also see a clear description of the breakdown.
5. Notice that markdowns (negative markups) can be used as well.

== Changelog ==

= 1.1 =
* Moved markup from term Description to new metadata field.
* Added metadata field to term Add and Edit panels.
* Broke class-mt2-markup-backend.php into class-mt2-markup-backend-attrb.php and class-mt2-markup-backend-product.php for supportability.
* Cleaned code and added comments for readability.

= 1.0 =
* Initial version.

== Upgrade Notice ==

= 1.1 =
* Markup is now stored in metadata, freeing up the Description field. Edits are added to the code so the markup will always be stored in the correct format.
* Code improvements ad to robustness and supportability.

= 1.0 =
Initial version
