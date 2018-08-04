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
Version:              2.2
Stable tag:           trunk
Requires at least:    4.6
Tested up to:         4.9.8
Requires PHP:         5.2.4
WC requires at least: 3.0
WC tested up to:      3.4.4

This plugin adds product variation markup by attribute to WooCommerce and adjusts product variation regular and sale prices accordingly.

== Description ==

= Varying Prices on Product Variations is Tedious and Error-Prone =

Want to add $5 to every blue product you sell? Maybe you sell jewelry with birthstones and some stones just cost more than others. If all "X-Large" products cost 7.5% more, you have to manually calculate and change every "X-Large" variation of every product.

= Markup by Attribute Adds 'Markup' to Attribute Terms =

Markup by Attribute solves this problem by allowing you to add a markup (or markdown) to individual attribute terms. If the attribute is 'color', then Markup by Attribute allows you to add "+5" to Blue while leaving Green and Yellow alone. When you set regular and sale prices, every blue product will be $5.00 more.

Markup by Attribute:

* Can create a fixed value markup (such as $5), or a percentage markup (such as 5%).
* The markup value can be positive yielding an increase in price, or negative yielding a decrease in price.
* Uses familiar WooCommerce bulk edit actions `Set regular price` and `Set sale price`.
* Puts the price increase (or decrease) in the options drop-down box along side of the terms so customers can make informed decisions.
* Writes a breakdown of the price modifications in the variation description so the itemization is visible to the customer.

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

These instructions assume you are familiar with WooCommerce Product Attributes and Product Variations. If not, you may want to review the WooCommerce documentation on [Product Attributes](https://docs.woocommerce.com/document/managing-product-taxonomies/#section-6) and [Variable Products](https://docs.woocommerce.com/document/variable-product/). Set up a few variable products to get the hang of it. Then come back here.  We'll wait.

Ready to dive in?

Note that steps 1, 3 and 4 below are standard WooCommerce Variable Product stuff. Step 2 is about the only **process change** you need to be concerned with. We've italicized some of the *outcome changes* you will notice in the other steps.

1. Create variation attributes and terms, if you haven't already.
    * For instance, the attribute 'size' may include the terms 'X-Small',  'Small', 'Medium', 'Large', and 'X-Large'.
    * The attribute 'color' may include the terms 'Orange', 'Red', 'Violet', 'Blue', 'Green', and 'Yellow'.

2. **While creating new terms or editing existing ones, add the markup.**
    * **For each term, consider whether a markup or markdown is needed. Adding a logo to a shirt might increase the cost by $5. Extra small shirts might be 10% less.**
    * **Put the amount of the markup in the term's Markup field on either the `Add new *attribute*` panel or the `Edit *attribute*` panel. Examples of valid values include: '-5', '5.95', '+5.678', '7.5%', and '-12%'.**
    * **Strings that are not recognized as numbers will be ignored. Numbers include 0 through 9, of course. But may also start with '+' or '-', include a decimal point, and end with a percent symbol ('%').**
  
3. Create product variations. (You probably have already done this).
    * Change the product type to `Variable product`.
    * Select the attribute(s) you added the markups to on the Attributes tab. Be sure to check the `[X] Used for variations` checkbox.
    * On the Variations tab, select `Create variations from all attributes` and click [Go].

4. Once you create your product variations, use the "Set regular price" and "Set sale price" bulk edit functions as you normally would.
    * **Even if you've already done this before installing Markup by Attribute, you will need to do it again to apply the markup.**
    * *The markup will be applied to the price according to the term associated with the variation.*
    * *A description of the markup will be added to the variation description.*

            <span id="mbainfo">
            Product Price $18.95
            Add $5.00 for Logo
            </span>

    * *TIP: Because the markup description is bracketed in <span> tags, CSS can be used to modify its appearance on the product page.*

            #mbainfo { color: darkblue; }

    * *TIP: If you change the markup at a later date, repeat this step to recalculate the markup for this product. Or do not repeat the step to leave the previous markups unchanged.*
    * *TIP: Always set the regular price before setting a sale price. Percentage markups are calculated on the regular price, so they can not be applied to a sale price if the regular price has not been set.*

== Frequently Asked Questions ==

= Does this support languages other than English? =

Not at this time. I will be adding internationalization in a future release. I would appreciate your assistance in translating it if you want it in another language.

However, a currency other than the US Dollar is supported. The settings page for Markup by Attribute allows changing the currency symbol and changing its placement.

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

= 2.2 =
* Fix Plugin name and Description.
* Fix markup calculation on sale prices when using a percentage markup (percent of the regular price, not sale price).
* Clear Markup by Attribute meta data from database on variation deletion.

= 2.1 =
* Organize `Settings` page with sub-headings.
* Provide a link to the wiki from `Settings` page.
* Expand wiki to include help with settings.
* Improve readme.txt readability.

= 2.0 =
* The new Settings page allows for increasing the number of variations that can be created at a time (override WooCommerce's limit of 50).
* The new Settings page allows for modifying the way pricing markup is added to the variation descriptions (overwrite, append, or ignore).
* The new settings page allows configuration of the way the markup is displayed, including the number of decimals and the currency symbol.
* Markup description now enclosed in <span> tags and can be modified with CSS ( #mbainfo {} ).
* Markup description added to the attribute term description and can be seen in the attribute term list.
* Markup now saved as a floating point number and not limited in digits below the decimal point.
* Corrected issue where Increase/Decrease Regular/Sale Price functions calculated based on variation price rather than base price, yielding incorrect prices when percentages were used.
* Corrected issue where Increase/Decrease Regular/Sale Price functions did not update variation descriptions.
* Corrected issue where markup in the options drop-down was calculated from the sale price.
* Database and code changes to enhance supportability.

= 1.3.2 =

Fix bug where default variation options were not being selected and `Choose an Option` was always shown.

= 1.3.1 =

Remove error_log() statement accidentally left in.

= 1.3 =

Improvements

* Added class backend-pointers for inline instructions.
* Added instructions link to Plugins page.

Patches

* Use only regular price markup in attribute drop-down on the frontend. Percentage markups where appearing different in dropdown and variation description.

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
