=== Markup by Attribute for WooCommerce ===

Plugin Name:			Markup by Attribute for WooCommerce
Description:			This plugin adds product variation markup by attribute to WooCommerce -- the ability to add a markup (or markdown) to an attribute term and have that change the regular and sale price of the associated product variations.
Plugin URI:				https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/
Tags:					WooCommerce, Attribute, Price, Variation, Markup
Author:					MarkTomlinson
Contributors:			MarkTomlinson
Donate link:			https://www.paypal.me/MT2Dev/5
License:				GPLv3
License URI:			https://www.gnu.org/licenses/gpl-3.0.html
Version:				4.1
Build:					202447.07
Stable tag:				trunk
Tested up to:			6.7.1
Requires at least:		4.6
PHP tested up to:		8.3.13
Requires PHP:			5.6
WC tested up to:		9.4.2
WC requires at least:	3.0
MySQL tested up to:		8.0.40

This plugin adds product variation markup by attribute to WooCommerce and adjusts product variation regular and sale prices accordingly.

== Description ==

= Varying Prices on Product Variations is Tedious and Error-Prone =

Want to add $5 to every blue product you sell? Maybe you sell jewelry with birthstones, and some stones just cost more than others. If all “X-Large” products cost 7.5% more, you have to manually calculate and change every “X-Large” variation of every product.

= Markup by Attribute Adds 'Markup' to Attribute Terms =

Markup by Attribute solves this problem by allowing you to add a markup (or markdown) to global attribute terms. If the attribute is 'color', then Markup by Attribute allows you to add “+5” to Blue while leaving Green and Yellow alone. When you set regular and sale prices, every blue product will be $5.00 more.

Markup by Attribute:

* Can create a fixed value markup (such as $5), or a percentage markup (such as 5%).
* The markup value can be positive, yielding an increase in price, or negative, yielding a decrease in price.
* Uses familiar WooCommerce bulk edit actions `Set regular price` and `Set sale price`.
* Puts the price increase (or decrease) in the options drop-down box alongside of the terms, so customers can make informed decisions. (Or, optionally, does not).
* Can write a breakdown of the price modifications in the variation description, so the itemization is visible to the customer.

== Installation ==

= In this section: =

* Manual Installation
* Automated Installation
* Using Markup-by-Attribute for WooCommerce 

= Manual Installation =

1. Download the Markup by Attribute for WooCommerce ZIP file.

2. Unzip the plugin files to the `/wp-content/plugins/markup-by-attribute-for-woocommerce` directory.

3. Activate the plugin through the WordPress `Plugins` page.

= Automated Installation =

1. Locate the Markup by Attribute for WooCommerce plugin using the search box on the in WordPress `Plugins` ⇾ `Add New` page.

2. Install the plugin using the `[Install]` button.

3. The `[Install]` button will change to an `[Activate]` button. Use it to activate the plugin.

= Using Markup by Attribute for WooCommerce =
(Detailed instructions can be found at [Markup by Attribute Wiki](https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/wiki).)

_NOTE:_ These instructions assume you are familiar with WooCommerce global Product Attributes and with WooCommerce Product Variations. If not, you may want to review the WooCommerce documentation on [Product Attributes](https://docs.woocommerce.com/document/managing-product-taxonomies/#section-6) and [Variable Products](https://docs.woocommerce.com/document/variable-product/).

# Three Easy Steps #

1.	**While creating new global attribute terms or editing existing ones, add the markup.**
	 * If the option needs a markup, put the amount of the markup in the term’s Markup field on either the `Add new attribute` panel or the `Edit attribute` panel.
	 * Examples of valid values include: ‘-5’, ‘5.95’, ‘+5.678’, ‘7.5%’, and ‘-12%’.
2.	**Create product variations as you normally would.**
	 * Markup by Attribute requires variable products because it changes the price of each variation.
	 * Using `Create variations from all attributes` is the easiest way to ensure you have all combinations.
	 * Do not have a variation with “Any” in an attribute with a markup because WooCommerce will choose the “Any” variation regardless of others that may exist. So, if XX-Large is 5% more, you cannot have one variation for “XX-Large” and another for “Any size” to cover the rest because WooCommerce assumes “Any size” includes “XX-Large”.
3.	**Use the `Set regular price` and `Set sale price` bulk edit functions as you normally would.**
	 * The markup will be applied to the price according to the attribute terms associated with the variation.
	 * A description of the markup will be added to the variation description.
		```
		Product Price 18.95
		Add 2.00 for Logo
		```
	 * _TIP_: Always set the regular price before setting a sale price. Percentage markups are calculated on the regular price, so they cannot be applied to a sale price if the regular price has not been set.
	 * _TIP_: If you change a markup value later, you can quickly update affected products using:
		- The "Reapply Markups" bulk action on the Products list
		- The refresh icon beneath the attributes of individual products in the list
		- The "Reapply markups to prices" option in the product's variation bulk actions

== Frequently Asked Questions ==

= If I change the markup for an attribute, how will product prices change? =

After changing a markup value, you have several ways to update the affected products:
1. For new products or variations, Markup-by-Attribute is incorporated into the `Set regular price` bulk action to set the variation price with the appropriate markup.
2. For existing products:
   - Use the "Reapply Markups" bulk action to update multiple products at once
   - Click the refresh icon next to individual products to update them one at a time
   - In the product editor, use "Reapply markups to prices" in the variations bulk actions

= It's not working. Why? =

When I get this question, it is usually because:

1. For new products or variations: You need to use the `Set regular prices` function to initially apply the variation prices with markups.
2. For existing products after changing a markup: You need to use one of the "Reapply markups" methods to update the prices

Also, make sure you don't have variations with "Any" attributes if those attributes have markups. WooCommerce will always choose the "Any" variation, regardless of others that may exist.

If it's none of these things, please check the support forums.

= What if I change an attribute's markups but do not want to change products marked up previously? =

Then do nothing. Prices, descriptions, and option drop-downs for products will remain at whatever value they were set to last time you ran the `Set regular price` or `Reapply markups to price` bulk variation actions.

= Does this support languages other than English? =

Yes. However, the developer only speaks American English.  I can provide translations via OpenAI's ChatGPT or Anthropic's Claude. But, I would prefer it if a native speaker translated the text. The .POT file is found in the /languages folder of the plugin. If you don't have access to your server, you can also find it on [GitHub](https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce).

What's a .POT file? If you'd like to help but don't know how to use a template file, don't worry. A .POT file is a text file that contains all the English phrases found in Markup-by-Attribute.  You can simply open it and translate what you read there.  Send me the translations and I will incorporate them in the next release.

Many thanks to [Zjadlbymcos](https://github.com/Zjadlbymcos) on GitHub for his Polish Translation and @silentstepsch for several variations of German.

= I'd like to donate. =

Thanks! The donation button assumes $5.00 USD. But please don't hesitate to adjust that amount up or down as you feel it's appropriate. I'm a retired guy who's living off his savings, so every bit helps.

If you use Markup-by-Attribute and want to see me continuing support for it, I encourage you to encourage me with a small donation.

== Screenshots ==

1. Note the addition of the “Markup (or markdown)” field on the bottom of the `Add new *attribute*` panel of the attribute editor.
2. Note the addition of the “Markup (or markdown)” field on the bottom of the `Edit *attribute*` screen of the attribute editor.
3. The regular price is $18.95. Markup-by-Attribute added $6 for a logo and $1.42 for extra large.
4. The customer sees the full range of sale prices available and how much each option costs, plus a clear description of the breakdown.
5. Markdowns (negative markups) can be used as well.
6. Markups are applied to sale prices just as they are with regular prices.
7. The settings page allows configuration of how the markup is displayed.

== Changelog ==

= 4.1 =
_Build 202447.06_
**FEATURE**: Added settings option to add the attribute label to the Add/Subtract text in the description.
  - Choose either "_Add $1.50 for Blue_" or "_Add $1.50 for Color: Blue_".
  - Choose either "_Subtract $3.97 for XXX-Small_" or "_Subtract $3.97 for Size: XXX-Small"_.
  - Use "Reapply Markups" introduced in version 4.0 to update all descriptions quickly.
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, MySQL, and PHP
_Build 202447.07_
**MAINTENANCE**: Converted spaces into tabs for compactness.

= 4.0.2 =
_Build 202447.05_
**ENHANCEMENT**: Addition of Markup Reapplication Workflow:
  - No longer need to manually reset prices to reapply changed markups
  - Added "Reapply markups to prices" to variable product variation bulk actions for single-product updates
  - Added quick-action icon on Products list for individual products
  - Added "Reapply Markups" bulk action on Products list for updating multiple products
  - Visual feedback during all operations with 'success'/'error' indicators
  - Progress tracking for bulk operations
**MAINTENANCE**: Added full Italian and French translations
**MAINTENANCE**: Realigned all translations with current text
**MAINTENANCE**: Consolidated and improved admin CSS styling
**MAINTENANCE**: Optimized JavaScript for better performance and user feedback
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, MySQL, and PHP

= 3.14.2 =
Build 202440.03
**FIX**: Corrected error with plugin links that cause critical error in the admin console. 
**MAINTENANCE**: Cleaned up many translations.

= 3.14 =
Build 202440.01
**FEATURE**: Optimized code; should run faster.
**FEATURE**: Added some Italian translations (customer-facing). Added more French, Swedish, and German translations.
**MAINTENANCE**: Converted to use namespaces.
**MAINTENANCE**: Ensured compatibility with the newest versions of WordPress, WooCommerce, and PHP.

= 3.13.2 =
Build 202428.02
**FEATURE**: Added a column for the attributes to the 'All Products' page. Can also filter on the individual attributes. This will make finding products whose markups have changed much easier. Column can be added or removed on the WooCommerce>>Settings>>Markup-by-Attribute page.

= 3.13.1 =
Build 202428.01
**FEATURE**: Added a column for the attributes to the 'All Products' page. Can also filter on the individual attributes. This will make finding products whose markups have changed much easier.
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP. New versions of WooCommerce and PHP.

= 3.12.2 =
Build 202425.01
**FIX**: Correct code to eliminate "Creation of dynamic property" depreciation notices.
**FIX**: Hid WooCommerce [Add price] button because it is redundant with 'Set regular prices' function, and does not hook into this plugin.
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP.

= 3.12.1 =
Build 202414.01
**FIX**: Add code to handle manually added attributes for GitHub issue #28. (https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce/issues/28) Thanks to g-alfieri for suggesting a fix.

= 3.12 =
Build 202414.01
**MAINTENANCE**: Extensive revisions to /src/product.php for readability and performance.
**MAINTENANCE**: Smaller revisions throughout code for readability and performance.
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP.

= 3.11.3 =
Build 202343.01
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP.
**FIX**: Added compatibility to WooCommerce HPOS (High-Performance Order Storage). No changes to operation; just added a compatibility declaration and admin message.

= 3.11.2 =
Build 202332.01
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP.
**FIX**: Corrected a minor formatting error in the variation descriptions.

= 3.11.1 =
Build 202308.01
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP.
Build 202308.02
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP.

= 3.11.0 =
Build 202245.01
**MAINTENANCE**: Ensured compatibility with current versions of WordPress, WooCommerce, and PHP.
**MAINTENANCE**: Resolved a PHP 'depreciation' warning.
**FIX**: Fixed a bug where Markup-by-Attribute would get confused about the decimal separator because the server and WooCommerce localization settings conflict.
Build 202245.02
**MAINTENANCE**: Changed the default on `Sale Price Markup` to 'yes'.

= 3.10.5 =
Build 202208.01
**FIX**: Correct Doubled currency symbol.
**MAINTENANCE**: Tested with PHP 8.0.16 and updated `PHP tested up to:` information.
**MAINTENANCE**: Tested with WordPress 5.9.1 and updated `Tested up to:` information.
**MAINTENANCE**: Tested with WooCommerce 6.2.1 and updated `WC tested up to:` information.
**MAINTENANCE**: Added `Apache tested up to:` 2.4.41 information.
**MAINTENANCE**: Added `MySQL tested up to:` 8.0.28 information.
Build 202208.02
**MAINTENANCE**: Corrected versioning information.

= 3.10.4 =
Build 202207.01
**FIX**: Correct floating-point conversion error for percentage markups over four digits long (< -1,000, > +1,000).

= 3.10.3 =
Build 202205.01
**MAINTENANCE**: Extensive clean-up.
**MAINTENANCE**: Used wc-price() function instead of DIY formatting for better compatibility.
**MAINTENANCE**: Tested with PHP 8.0.15 and updated `PHP tested up to:` information.
**MAINTENANCE**: Tested with WordPress 5.9 and updated `Tested up to:` information.
**MAINTENANCE**: Tested with WooCommerce 6.1.1 and updated `WC tested up to:` information.

= 3.10.1 =
Build 202152.01
**FIX**: Commented out unneeded module testing because it prevents Markup-by-Attribute from working on some sites. Will re-include after I determine why it did not work.

= 3.10 =
Build 202152.01
**FEATURE**: Allows you to calculate percentage markups on sale prices instead of always using the regular price calculation.
**FEATURE**: Adds a sortable Markup column to the attribute list view and eliminates the markup notation from the description.
**MAINTENANCE**: Added test to not load unneeded modules.
**MAINTENANCE**: Minor code and comment cleanup.
**MAINTENANCE**: Tested with PHP 8.0.14 and updated `PHP tested up to:` information.
**MAINTENANCE**: Tested with WordPress 5.8.2 and updated `Tested up to:` information.
**MAINTENANCE**: Tested with WooCommerce 6.0.0 and updated `WC tested up to:` information.

= 3.9.6 =
Build 202113.02
**FIX**: Empty and non-zero evaluations are no longer the same in PHP 8. Corrected to check each individually.
**MAINTENANCE**: Tested with PHP 8.0.3 and update `PHP tested up to:` information.
Build 202113.03
**MAINTENANCE**: Add customer facing Swedish translations.
**MAINTENANCE**: Tested with PHP 8.0.8, WordPress 5.7.2, WooCommerce 5.4.1.

= 3.9.5 =
**MAINTENANCE**: Tested with WordPress 5.7 and include new `Tested up to:` information.
**MAINTENANCE**: Tested with WooCommerce 5.1.0 and include new `WC tested up to:` information.

= 3.9.4 =
**FIX**: Consolidate constants.

= 3.9.3 =
**FIX**: Corrected issue with ATTRB_MARKUP_DESC_END vs. ATTRB_MARKUP_END.

= 3.9.2 =
**MAINTENANCE**: Tested with WooCommerce 4.3.0 and include new `Tested to:` information.

= 3.9.1 =
**FIX**: Corrected issue when website directory path contains mixed case.

= 3.9 =
**FIX**: Corrected issue where Markup-by-Attribute might overwrite another plugin or theme's option selection.
**FEATURE**: Add option to overwrite the term name to include the markup.
**MAINTENANCE**: General clean up and commenting.

= 3.8 =
 * Translation: Further corrections to language files and created versions for German variations. (Thanks to silentstepsch.)
**MAINTENANCE**: Include new `Tested to:` information.

= 3.7 =
 * Translation: Corrected German language files -- thanks to Roland Pohl.

= 3.6 =
**FEATURE**: Add option to hide base price in the product description.
 * Translation: Add German translation

= 3.5 =
**FIX**: Correct 'hide' option of option drop-down box.
**FIX**: Remove non-functioning or incorrectly implemented options.
**FIX**: Corrected the way the markup was saved to metadata (stopped rounding).
**MAINTENANCE**: Include new `Tested to:` information.

= 3.4 =
**FIX**: Show hidden attribute terms to correct error where WordPress/WooCommerce wrongly considers the term as unused.
**MAINTENANCE**: Updated instructions.
**MAINTENANCE**: Removed v2.4 upgrade message.

= 3.3. =
**FEATURE**: For compatibility with plugins that remove the options drop-down box, the `Include the Increase (Decrease) in the Term Name` option allows markups to show when the drop-down box is not available.
**FEATURE**: For compatibility with plugins that remove the options drop-down box, the `Do NOT show the markup in the options drop-down box` option now doesn't load the MT2MBA_FRONTEND_OPTIONS class.
**FIX**: Fixed bug where adding and removing a sales price would leave the markup as the new sales price.

= 3.2 =
**FEATURE**: Add option to calculate percentage markups from sale prices rather than regular prices.
**FIX**: Option 'Do NOT show the markup in the options drop-down box' showed slug in drop-down box instead of term name. Corrected to always show name for global attributes.
**MAINTENANCE**: Renamed Attrb.php to Term.php, since it actually affects the terms and not the general attribute.
**MAINTENANCE**: Added donation language to Settings page.

= 3.1 =
**FEATURE**: Added ability to round percentage markups, so prices will retain digits below decimal. For shops that want to end all prices with .00, .95, .99 or whatever. Requested feature from shop where all prices end in .00.
**FEATURE**: Fully tested with Gutenberg.
**FEATURE**: Added Polish language files.

= 3.0 =
**FEATURE**: Now supports Internationalization and translation.
**MAINTENANCE**: Simplified usage directions in readme.txt.
**MAINTENANCE**: Restructured libraries and renamed files and classes for better organization.
**MAINTENANCE**: Rebuilt admin notice class for supportability and improved performance.
**MAINTENANCE**: Reorganized main module for understandability.
**MAINTENANCE**: General code clean-up and redundancy removal.

= 2.5 =
**FIX**: Corrected “Requires PHP” version number in readme.txt.
**FIX**: Updated “WC tested up to” version number in readme.txt.
**FIX**: Eliminated unused “Docs” folder

= 2.4 =
**FEATURE**: Use the WooCommerce currency formatting settings.
**FIX**: Re-ensure documentation is clear that this works on “global” attributes.

= 2.3 =
**FEATURE**: Add option to not display markup in the options drop-down box.
**FIX**: Add missing 'Author:' tag.
**FIX**: Ensure documentation is clear that this works on “global” attributes.
**FIX**: Version 2.3 exposes a problem in an earlier version's database conversion where percentage markups show incorrectly in the options drop-down (For instance, a 10% markup on $40 shows as $10 instead of $4). To patch around it, version 2.3 will put the percentage in the drop-down instead of the actual markup. These items will need to have their regular prices reset to have the actual markup appear.

= 2.2 =
**FIX**: Plugin name and Description.
**FIX**: Markup calculation on sale prices when using a percentage markup (percent of the regular price, not sale price).
**FIX**: Clear Markup-by-Attribute metadata from the database on variation deletion.

= 2.1 =
**FEATURE**: Organize `Settings` page with subheadings.
**FEATURE**: Provide a link to the wiki from `Settings` page.
**FEATURE**: Expand wiki to include help with settings.
**FIX**: Improve readme.txt readability.

= 2.0 =
**FEATURE**: The new Settings page allows for increasing the number of variations that can be created at a time (override WooCommerce's limit of 50).
**FEATURE**: The new Settings page allows for modifying the way pricing markup is added to the variation descriptions (overwrite, append, or ignore).
**FEATURE**: The new settings page allows configuration of the way the markup is displayed, including the number of decimals and the currency symbol.
**FEATURE**: Markup description now enclosed in <span> tags and can be modified with CSS (#mbainfo {}).
**FEATURE**: Markup description added to the attribute term description and can be seen in the attribute term list.
**FEATURE**: Markup now saved as a floating-point number and not limited to only two digits below the decimal point.
**FEATURE**: Database and code change to enhance supportability.
**FIX**: Corrected issue where Increase/Decrease Regular/Sale Price functions calculated based on variation price rather than base price, yielding incorrect prices when percentages were used.
**FIX**: Corrected issue where Increase/Decrease Regular/Sale Price functions did not update variation descriptions.
**FIX**: Corrected issue where markup in the options drop-down was calculated from the sale price.

= 1.3.2 =

FIX: Eliminate bug where default variation options were not being selected and `Choose an Option` was always shown.

= 1.3.1 =

FIX: Remove the error_log() statement accidentally left in.

= 1.3 =

**FEATURE**: Added class backend-pointers for inline instructions.
**FEATURE**: Added instructions link to Plugins page.
**FIX**: Use only regular price markup in attribute drop-down on the frontend. Percentage markups were appearing different in dropdown and variation description.

= 1.2.0 =

 * Change backend-attrb and backend-product to allow percentage markup.
 * Change backend-product and frontend to store actual product-attribute markups in post meta.

= 1.1.1 =

 * Added code to class backend-attrb to prevent adjusting the price when the price field is NULL.

= 1.1 =

 * Moved markup from term Description to a new metadata field.
 * Added metadata field to term Add and Edit panels.
 * Broke class-mt2-markup-backend.php into class-mt2-markup-backend-attrb.php and class-mt2-markup-backend-product.php for supportability.
 * Cleaned code and added comments for readability.

= 1.0 =

 * Initial version.

== Upgrade Notice ==

= 3.11 =

Ensured compatibility with current versions of WordPress, WooCommerce, and PHP. Resolved a PHP 'depreciation' warning.

Fixed a bug where Markup-by-Attribute would get confused about the decimal separator because the server and WooCommerce localization settings conflicted.

= 3.9 =

Added a new feature that allows Markup-by-Attribute to add the markup to the name of the option. This is useful when the dropdown box has been replaced by color swatches, checkboxes, or some other selector. As long as the name of the option is displayed (for instance, when the cursor hovers over it), then the markup will be seen by your customer.

Fixed a bug where Markup-by-Attribute would overwrite the options' selector for some themes and other plugins. This occurs if the theme or plugin provided changed the function of the options' selector (for instance, to color swatches), and did not code it so that they take precedence.

= 1.3.1 =

 * Includes clearer instructions and inline help.
 * Repairs how percentage markups are displayed.

= 1.2.0 =

 * Now allows the use of percentage (5%) markups and markdowns, as well as fixed values ($5).
 * And it stores the actual markup value displayed in the attribute drop-down with the product. This allows changing of the markup in the attribute \without affecting the markup displayed with the product.

= 1.1.1 =

Prevents null prices (due to an apparent bug in WooCommerce sale_price) from being adjusted with a markup.

= 1.1 =

 * Markup is now stored in metadata, freeing up the Description field. Edits are added to the code, so the markup will always be stored in the correct format.
 * Code improvements to add robustness and supportability.

= 1.0 =

Initial version
