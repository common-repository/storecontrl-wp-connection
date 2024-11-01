=== StoreContrl Woocommerce ===
Contributors: arture
Donate link: https://www.arture.nl/abonnementen/
Tags: storecontrl, woocommerce, arture, kassakoppeling
Requires at least: 1.6.1
Tested up to: 6.5.5
Stable tag: 4.1.0
Requires PHP: 0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatiseer eenvoudig je productvoorraad-beheer van en naar je Woocommerce webshop. Geen handmatige invoer op meerdere plekken maar alles geregeld vanuit één centrale plek. Via de uitgebreide instellingen pas je de plugin eenvoudig aan naar je wensen. StoreContrl Cloud is geschikt voor zowel kleine webshops met een beperkt assortiment, als voor webshops met duizenden producten.

== Description ==

<strong>Wat bieden we:</strong>
<ol>
 	<li>"Real-time" synchronisatie van je producten en wijzigingen</li>
 	<li>"Real-time" synchronisatie van de voorraad</li>
 	<li>Woocommerce orders direct in StoreContrl</li>
 	<li>Volledig naar wens instelbaar</li>
 	<li>Altijd up-to-date</li>
 	<li>Inclusief support</li>
</ol>

<strong>Wat verwerken we:</strong>
Alle beschikbare informatie vanuit StoreContrl. Het is altijd mogelijk om data verrijking toe te passen binnen de webshop. Denk bijvoorbeeld aan SEO teksten, extra eigenschappen of uitgebreidere product informatie.
<ol>
 	<li>Product teksten</li>
 	<li>Product afbeeldingen ( Featured en Gallery )</li>
 	<li>Product categorie structuur</li>
 	<li>Product voorraad</li>
 	<li>Product eigenschappen o.a. merk, kleur, maat en artikelnummer</li>
 	<li>Product tags</li>
 	<li>Kortingen ( Ook de mogelijkheid om in te plannen )</li>
 	<li>Webshop orders</li>
</ol>

<strong>Wat kost het:</strong>
Voor slechts € 42,50 per maand ben je verzekerd van een volledige integratie tussen StoreContrl CLoud en Woocommerce. Schaf eenvoudig je licentie aan via https://www.arture.nl/abonnementen/

<strong>Wat is mogelijk:</strong>
Specifieke wensen voor uw webshop? Via de diverse hooks kunnen we als Arture hier zeker bij van dienst zijn.

<strong>We hebben een vraag:</strong>
Heeft u vragen of opmerkingen? Ons development team staat tot uw beschikking. Naast alle informatie die te vinden is in de support/status sectie van de plugin en op onze webshop, kunt u ook direct een support aanvraag indienen via support@arture.nl

<strong>Wat is er meer:</strong>
ALs Arture leveren we naast onze product koppeling ook de volgende koppelingen voor SToreContrl.
<ol>
 	<li>StoreContrl - Exact Online</li>
 	<li>StoreContrl - Snelstart</li>
 	<li>StoreContrl - Mailchimp</li>
</ol>

== Installation ==

1. Installeer de plugin.
2. Schaf een licentie aan op https://www.arture.nl/abonnementen/
3. Vul je API credentials in op de plugin tab
4. Configureer de instellingen voor een juiste verwerking van jouw producten
5. Vergeet niet bij livegang de optie; Verwerk bestellingen te activeren

== Frequently Asked Questions ==

= Voor wie is deze plugin =
Voor alle bestaande of nieuwe gebruikers van het StoreContrl Cloud kassasysteem.

= Kan ik dit zelf instellen =
Het betreft een plug-and-play plugin. Wij bieden echter de mogelijkheid om de configuratie voor u uit te voeren.

= Zijn er kosten aan verbonden =
Na activatie van de plugin kan je een activatie key aanvragen. Deze geeft toegang tot de tool. Voor slechts € 42,50 per maand bent u verzekerde van een up-to-date plugin inclusief support.

= Hoe bepaal ik welke producten er op mijn webshop komen =
In de webshop module van StoreContrl bepaal je welke producten er online komen. Alleen die producten zullen ook verwerkt worden.

= Werkt dit ook met een bestaande webshop =
Gezien alle product data zoals categorieën, afbeeldingen, prijzen als ook eigenschappen ( merk, kleur en maat ) volledig automatisch worden verwerkt vanuit StoreContrl is ons advies om altijd te starten met een lege webshop.

= Zijn er minimale vereisten voor mijn webshop =
Wij adviseren altijd een up-to-date webshop. De plugin is generiek en kan werken met alle Woocommerce webshops mits deze voldoen aan de normale standaarden.

= Zijn er minimale vereisten voor mijn hosting =
Om te kunnen verbinden met het Cloud platform is het noodzakelijk dat poort 1443 open staat voor verkeer!

== Screenshots ==

1. API instellingen
2. Configuratie instellingen
3. Status en logging

== Changelog ==

= 4.1.0 =
* HPOS updates

= 4.0.9 =
* Fix for empty product batches

= 4.0.8 =
* Processing flow improvements for better server load
* Bugfix with order with coupons and total of zero

= 4.0.7 =
* Added "kaartje" to possible fee lines
* Code optimalisations

= 4.0.6 =
* Convert/remove unknown characters form variation names
* Alias bugfix with name as space
* Minor improvements

= 4.0.5 =
* Performance upgrade for bulk import

= 4.0.4 =
* Bulk and single product import flow change
* Added function for bulk discount
* Added function for processing variation alias_name if exist
* Orderpickingapp add-on

= 4.0.3 =
* Optimalisations/upgrades
* Added product bulk import flow
* Extra checks on product variation processing

= 4.0.2 =
* Added fee process for "inpakken"
* Bugfix for processing of products with more than 100 variations
* Check for existing parent product on variation save

= 4.0.1 =
* Bugfix with specific sale price situation

= 4.0.0 =
* Migration of premium and basic into one stable version
* Extra check on valid sc credit
* Optimized version of stock status by CRUD

= 1.6.3 =
* Optimalisation: SkuChanged performance
* Bugfix with sku response below 300 results

= 1.6.2 =
* Feature: Product block for syncing a specific product
* Bugfix with flow of old discount product and varation changes

= 1.6.0 =
* Bugfix for last variation and pagination calls
* Bugfix with backorder status of products

= 1.5.9 =
* Bugfix with barcode and sku check
* msrp processing

= 1.5.8 =
* Bugfix with negative stock_total
* Changed batch identifier to timestamp
* Added empty variation check with delete action

= 1.5.7 =
* Variation duplicates remove
* Queue process improvements

= 1.5.6 =
* Tags / Structure
* Bugfix with FTP close

= 1.5.5 =
* Woocommerc CRUD

= 1.5.4 =
* Code optimalisation

= 1.5.3 =
* Default payment and shipment options for external marketplaces like Bol.com
* Approved API version check
* Bugfix for expired product in Sale category
* Bugfix for coupon exist check
* Category brands relation for better filters

= 1.5.2 =
* Changed fee apply to new coupon flow for credit cheques

= 1.5.1 =
* Sale category connect for planned and expired sale products
* Credit fee order bugfix
* custom_get_variation_id_by_sku removed

= 1.5.0 =
* Tags as category process fix
* CancelOrder call for deleting existing order in SC
* custom_get_variation_id_by_sku rewrite
* SkuRemoved functie disabled/unused
* Delete custom translate function
* GetDiscountChanged added

= 1.4.9 =
* SkuRemoved action by 25 records

= 1.4.8 =
* Custom update post meta with clean function
* Bugfix with existing products and SKU_added

= 1.4.7 =
* Added fee/discount check for orders with Bulk Discount
* Added fee check for orders with additional shipping costs
* Bugfix with variation status inside woocommerce.
* last_update value change form h:i to H:i

= 1.4.6 =
* Removed woocommerce_applied_coupon hook
* Bugfix with variation stock status by stock 0
* Bugfix with variation stock lower than 0
* Bugfix with defining Sale categorie with sale periods in the future
* Bugfix with defining Sale categorie with sale periods in the future

= 1.4.5 =
* Bugfix for GetVariationChanged
* Remove sale category from products without discount(anymore)
* Bugfix for shipping postcode
* Bugfix with API notice
* Set _backorder value to no for variations because Woocommerce won't update the status if stock is 0

= 1.4.4 =
* CPU load check on cronjobs
* Extra check on existing main_group
* Added fixed for clean slugs with *
* Sub_group option to process as attribute for filtering
* Bugfix with existing parent terms
* New setting for keep images
* Status en version check only for plugins and SC screen

= 1.4.3 =
* API authorisation check/message
* Double check for barcode on product find
* Logging of add attribute failures
* Show error messages when order can't processed
* Order | Selling price round bugfix
* SkuAdded changes pagination
* GetAllSkuInfo pagination
* Bugfix with image titles with double spaces
* SSL curl setting
* Added info for the import settings
* Arture API maintenance check

= 1.4.2 =
* Bugfix with product status by new added products
* Check for empty supplier_sku with fallback on product ID as SKU
* Check for empty comments field in order
* Bugfix for SKU added/changed call with product variation terms

= 1.4.1 =
* Check for removed images form SC product
* Add shipping_tax check in order processing
* Bugfix with barcode as sku search
* Disabled update backorders option

= 1.4.0 =
* Check for init sync in live modus
* Set default size attribute by variations update

= 1.3.9 =
* Admin option for keeping product title
* Admin option for keeping product categories

= 1.3.8 =
* Added phone number to order data
* Added comments number to order data
* Bugfix with product visibility on manual update

= 1.3.7 =
* Fix for init sync by existing connection
* Fix for changing existing discounts

= 1.3.6 =
* Fix for retail_price by new products with discount

= 1.3.5 =
* Fix for calculated taxes in Woocommerce

= 1.3.4 =
* Check for remaining batches and SKU processing
* Manage stock always no for product

= 1.3.3 =
* Bugfix for discount with fixed price
* Performance checks and optimalisation

= 1.3.1 =
* Bugfix with duplicate products and query variations
* Adding check for category like n.v.t.
* Image bugfix for new products
* Extra check for main product status
