# mt2-markup-by-attribute

This plugin adds product variation markup by attribute to WooCommerce -- the ability to add a markup (or markdown) to an attribute term and have that change the regular and sale price of the associated product variations.

DESCRIPTION

When migrating a store from another online commerce system to WooCommenrce, we ran into a shortcoming of WooCommerce -- the inability to specify a markup or markdown in an attribute term and apply that markup or markdown when setting the price of variations. Various plugins attempt to mitigate the problem with metadata, but that creates its own set of problems.

So, this plugin does one thing -- If you enter a numeric value in an attribute term description, that value will be applied to the price of the associated variation during the bulk edit actions "Set regular price" and "Set sale price". When setting the price, it adds explanative text to the variation description which WooCommerce will display as the variation is selected. The option dropdown will also contain the markup or markdown so the customer can estimate the final price while deciding options.

This plugin is intended for large sets of variations where customizing the price for each is not practical. So, the maximum number of variations that can be created at a time is also raised from 50 to 250. WooCommerce allows us to create variations over 50 by running "Create variations from all attributes" several times. However, this creates additional work reorganizing the variations if the variations are expected to be in a specific order.

USAGE

1. Install and activate the plugin as you would any other WordPress plugin.

2. Create variation attributes and terms, if you haven't already. For instance, the attribute 'Style' may include the terms 'Turtleneck', 'V-neck', 'Button-down', and 'Polo'.

3. While creating new terms or editing existing ones, add the markup.
  * For each term, consider whether a markup or markdown is needed. V-neck shirts might need to be marked down by a dollar or two, while button-down shirts might require a markup of a couple dollars.
  * Put the amount of the markup in the term's Markup field on either the 'Add new...' panel or the 'Edit...' panel. Any floating point number is valid; "5", "5.95", "+5.00", "-5", and "-1.2345" are all good.
  * Strings that are not recognized as numbers will be ignored. Markups with several numbers below the decimal will be rounded to the second decimal place (hundreths) before being saved.

4. Once you create your product variations, use the "Set regular price" and "Set sale price" as you normally would.
  * The markup will be applied to the price according to the term associated with the variation.
  * The variation description will be overwritten with text to note the markup.
  * IMPORTANT NOTE: If you change the markup afterward, you *must* set the variation prices again. If you do not, the drop-down box the customer sees and the price that is calculated will not match.

DEVELOPMENT GOALS

My intentions for future versions include --
* Localization so languages other than English can be used in the description.
* The ability to set the number of maximum variations per "Create variations from all attributes".
* Optional verbiage for the variation descriptions.
* Optional display formats for the options drop-down.
