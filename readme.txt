=== Markup by Attribute for WooCommerce ===

Plugin Name:          Markup by Attribute for WooCommerce - MT² Tech
Description:          This plugin adds product variation markup by attribute to WooCommerce -- the ability to add a markup (or markdown) to an attribute term and have that change the regular and sale price of the associated product variations.
Plugin URI:           https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
Tags:                 WooCommerce, Attribute, Price, Variation, Markup
Author:               MarkTomlinson
Contributors:         MarkTomlinson
Donate link:          https://www.paypal.me/MT2Dev/15
License:              GPLv3
License URI:          https://www.gnu.org/licenses/gpl-3.0.html
Version:              3.1
Build:                201847.01
Stable tag:           trunk
Requires at least:    4.6
Tested up to:         4.9.8
Requires PHP:         5.6
WC requires at least: 3.0
WC tested up to:      3.5.1

This plugin adds product variation markup by attribute to WooCommerce and adjusts product variation regular and sale prices accordingly.

== Description ==

= Varying Prices on Product Variations is Tedious and Error-Prone =

Want to add $5 to every blue product you sell? Maybe you sell jewelry with birthstones and some stones just cost more than others. If all "X-Large" products cost 7.5% more, you have to manually calculate and change every "X-Large" variation of every product.

= Markup by Attribute Adds 'Markup' to Attribute Terms =

Markup by Attribute solves this problem by allowing you to add a markup (or markdown) to global attribute terms. If the attribute is 'color', then Markup by Attribute allows you to add "+5" to Blue while leaving Green and Yellow alone. When you set regular and sale prices, every blue product will be $5.00 more.

Markup by Attribute:

* Can create a fixed value markup (such as $5), or a percentage markup (such as 5%).
* The markup value can be positive yielding an increase in price, or negative yielding a decrease in price.
* Uses familiar WooCommerce bulk edit actions `Set regular price` and `Set sale price`.
* Puts the price increase (or decrease) in the options drop-down box along side of the terms so customers can make informed decisions. (Or, optionally, does not).
* Can write a breakdown of the price modifications in the variation description so the itemization is visible to the customer.
* Has been tested with Gutenberg and is fully compatible.

== Installation ==

= In this section: =

* Manual Installation
* Automated Installation
* Using Markup by Attribute for WooCommerce 

= Manual Installation =

1. Download the Markup by Attribute for WooCommerce ZIP file.

2. Unzip the plugin files to the `/wp-content/plugins/markup-by-attribute-for-woocommerce` directory.

3. Activate the plugin through the `Plugins` page in WordPress.

= Automated Installation =

1. Locate the Markup by Attribute for WooCommerce plugin on the `Plugins` => `Add New` page in WordPress using the search box.

2. Install the plugin using the `[Install]` button.

3. The `[Install]` button will change to an `[Activate]` button. Use it to activate the plugin.

= Using Markup by Attribute for WooCommerce =

_NOTE:_ These instructions assume you are familiar with WooCommerce global Product Attributes and with WooCommerce Product Variations. If not, you may want to review the WooCommerce documentation on [Product Attributes](https://docs.woocommerce.com/document/managing-product-taxonomies/#section-6) and [Variable Products](https://docs.woocommerce.com/document/variable-product/).

# Three Easy Steps #

1.  **While creating new global attribute terms or editing existing ones, add the markup.**
    * If the option needs a markup, put the amount of the markup in the term’s Markup field on either the `Add new attribute` panel or the `Edit attribute` panel.
    * Examples of valid values include: ‘-5’, ‘5.95’, ‘+5.678’, ‘7.5%’, and ‘-12%’.
1.  **Create product variations as you normally would.**
    * Markup by Attribute requires variable products because it changes the price of each variation.
    * It is recommended that you use the `Create variations from all attributes` function to ensure all variations are represented.
1.  **Use the `Set regular price` and `Set sale price` bulk edit functions as you normally would.**  (_NOTE: If you’ve already set the prices before installing Markup by Attribute, you will need to do it again to apply the markup_).
    * The markup will be applied to the price according to the attribute terms associated with the variation.
    * A description of the markup will be added to the variation description.
      ```
      Product Price 18.95
      Add 2.00 for Logo
      ```
    * _TIP_: If you change the markup at a later date, repeat this step to recalculate the markup for this product. Or do not repeat the step to leave the previous markups unchanged.
    * _TIP_: Always set the regular price before setting a sale price. Percentage markups are calculated on the regular price, so they can not be applied to a sale price if the regular price has not been set.

# Advanced #

Because the markup description is bracketed in `<span>` tags, CSS can be used to modify its appearance on the product page. Simply modify the id "#mbainfo".
    ```
    #mbainfo {
        color: salmon;
    }
    ```

== Frequently Asked Questions ==

= Does this support languages other than English? =

Yes. However, the developer only speaks American English.  I can provide 'Google Translate' versions of other languages, but I would prefer it if a native speaker translated the text. The .POT file is found in the /languages folder of the plugin. If you don't have access to your server, you can also find it on [GitHub](https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce).

What's a .POT file? If you'd like to help but don't know how to use a template file, don't worry. A .POT file is a text file that contains all the English phrases found in Markup by Attribute.  You can simply open it and translate what you read there.  Send me the translations and I will incorporate them in the next release.

Many thanks to [Zjadlbymcos](https://github.com/Zjadlbymcos) on GitHub for his Polish Translation.

= If I change the markup for an attribute, how will product prices change? =

Markup by Attribute works within the framework provided by WooCommerce and sets product variation markups (or markdowns) during the `Set regular price` and `Set sale price` actions. Therefore, you must locate the products affected by this change and reset the regular and sale prices. I'm working on adding an "Attribute" column to the Product list to facilitate this type of activity.

= What if I change an attribute's markups but do not want to change products marked up previously? =

Then do nothing. Prices, descriptions, and option drop-downs for products will remain at whatever value they were set to last time you ran the `Set regular price` or `Set sale price` bulk variation activities.

= I'd like to donate. =

Thanks! The donation button assumes $15.00 USD. But feel free to adjust that amount up or down as you feel it's appropriate. I'm a retired guy who's living off his savings, so every little bit helps. Besides, I need the motivation!

== Screenshots ==

1. Note the addition of the "Markup (or markdown)" field on the bottom of the `Add new *attribute*` panel of the attribute editor.
2. Note the addition of the "Markup (or markdown)" field on the bottom of the `Edit *attribute*` screen of the attribute editor.
3. The regular price is $18.95. Markup by Attribute added $6 for a logo and $1.42 for extra large.
4. The customer sees the full range of sale prices available and how much each option costs, plus a clear description of the breakdown.
5. Markdowns (negative markups) can be used as well.
6. Markups are applied to sale prices just as they are to regular prices.
7. The settings page allows configuration of how the markup is displayed.

== Changelog ==

= 3.1 =
* Feature: Added ability to round percentage markups so prices will retain digits below decimal. For shops that want to end all prices with .00, .95, .99  or whatever. Requested feature from shop where all prices end in .00.
* Feature: Fully tested with Gutenberg.
* Feature: Added Polish language files.

= 3.0 =
* Feature: Now supports Internationalization and translation.
* Maintenance: Simplified usage directions in readme.txt.
* Maintenance: Restructured libraries and renamed files and classes for better organization.
* Maintenance: Rebuilt admin notice class for supportability and improved performance.
* Maintenance: Reorganized main module for understandability.
* Maintenance: General code clean-up and redundancy removal.

= 2.5 =
* Fix: Corrected "Requires PHP" version number in readme.txt.
* Fix: Updated "WC tested up to" version number in readme.txt.
* Fix: Eliminated unused "Docs" folder

= 2.4 =
* Feature: Use the WooCommerce currency formatting settings.
* Fix: Re-ensure documentation is clear that this works on "global" attributes.

= 2.3 =
* Feature: Add option to not display markup in the options drop-down box.
* Fix: Add missing Author: tag.
* Fix: Ensure documentation is clear that this works on "global" attributes.
* Fix: Version 2.3 exposes a problem in an earlier version's database conversion where percentage markups show incorrectly in the options drop-down (For instance, a 10% markup on $40 shows as $10 instead of $4). To patch around it, version 2.3 will put the percentage in the drop-down instead of the actual markup. These items will need to have their regular prices reset in order to have the actual markup appear.

= 2.2 =
* Fix: Plugin name and Description.
* Fix: Markup calculation on sale prices when using a percentage markup (percent of the regular price, not sale price).
* Fix: Clear Markup by Attribute metadata from the database on variation deletion.

= 2.1 =
* Feature: Organize `Settings` page with sub-headings.
* Feature: Provide a link to the wiki from `Settings` page.
* Feature: Expand wiki to include help with settings.
* Fix: Improve readme.txt readability.

= 2.0 =
* Feature: The new Settings page allows for increasing the number of variations that can be created at a time (override WooCommerce's limit of 50).
* Feature: The new Settings page allows for modifying the way pricing markup is added to the variation descriptions (overwrite, append, or ignore).
* Feature: The new settings page allows configuration of the way the markup is displayed, including the number of decimals and the currency symbol.
* Feature: Markup description now enclosed in <span> tags and can be modified with CSS ( #mbainfo {} ).
* Feature: Markup description added to the attribute term description and can be seen in the attribute term list.
* Feature: Markup now saved as a floating point number and not limited in digits below the decimal point.
* Feature: Database and code change to enhance supportability.
* Fix: Corrected issue where Increase/Decrease Regular/Sale Price functions calculated based on variation price rather than base price, yielding incorrect prices when percentages were used.
* Fix: Corrected issue where Increase/Decrease Regular/Sale Price functions did not update variation descriptions.
* Fix: Corrected issue where markup in the options drop-down was calculated from the sale price.

= 1.3.2 =

Fix: Eliminate bug where default variation options were not being selected and `Choose an Option` was always shown.

= 1.3.1 =

Fix: Remove error_log() statement accidentally left in.

= 1.3 =

* Feature: Added class backend-pointers for inline instructions.
* Feature: Added instructions link to Plugins page.
* Fix: Use only regular price markup in attribute drop-down on the frontend. Percentage markups where appearing different in dropdown and variation description.

= 1.2.0 =

* Change backend-attrb and backend-product to allow percentage markup.
* Change backend-product and frontend to store actual product-attribute markups in post meta.

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

= 1.3.1 =

* Includes clearer instructions and inline help.
* Repairs how percentage markups are displayed.

= 1.2.0 =

* Now allows the use of percentage (5%) markups and markdowns as well as fixed values ($5).
* And it stores the actual markup value displayed in attribute drop-down with the product. This allows changing of the markup in the attribute \without affecting the markup displayed with the product.

= 1.1.1 =

Prevents null prices (due to an apparent bug in WooCommerce sale_price) from being adjusted with a markup.

= 1.1 =

* Markup is now stored in metadata, freeing up the Description field. Edits are added to the code so the markup will always be stored in the correct format.
* Code improvements to add robustness and supportability.

= 1.0 =

Initial version
