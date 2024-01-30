Commerce Webform Order
======================

This module integrates Webform with Drupal commerce, and it allows creating
orders with the submission data of a Webform via a Webform handler.

### WHAT DOES THIS MODULE PROVIDE?

* A Webform element for embedding the payment method selector (Payment Method).
* A Webform handler for creating a commerce order with a webform submission
  (Commerce Webform Order Handler).

### HOW TO USE

* Go to admin/structure/webform and create or edit a webform, you can find
  extensive documentation in the Webform module
  docs: https://www.drupal.org/docs/8/modules/webform

* In the handlers admin screen
  (admin/structure/webform/manage/`webform_id`/handlers) you should be able
  to add a "Commerce Webform Order Handler" one.

* In the handler settings, you can customize:
  * The store that sells the purchasable entities.
  * Order item (allowing to override certain properties such price or currency).
  * Cart behaviours such as redirection or empty the current cart.

### SPONSORS

* Initial development: [Fundación UNICEF Comité Español](https://www.unicef.es)
* Contrib version: [Hermitage of the Awakened Heart](http://www.hermitageoftheawakenedheart.org)
* Update/Delete order items: [Mile3 Web Development, Inc.](https://www.mile3.com)
* Payment method selector/Purchasable Entity: [Asociación para la defensa de la naturaleza WWF/ADENA](https://www.wwf.es)

### TRY OUT A DEMONSTRATION

Launch a new instance and enable the "Commerce Webform Order Demo" module:
https://simplytest.me/configure?project=commerce_webform_order&version=3.x-dev

More info about how to launch a new instance:
https://www.drupal.org/docs/contributed-modules/simplytestme/quick-setup-for-reviewing-projects

### LIVE EXAMPLE

Here's a live example of this module working in the real world: https://www.unicef.es/hazte-socio

### SIMILAR MODULES

* [Webform Product](https://www.drupal.org/project/webform_product): Webform Product can create a Commerce order from
  any Webform submission. The module currently only works well with off-site
  payment providers.

The main difference between Webform Product (WP) and Commerce Webform Order
(CWO) is that WP sells webform subissions and CWO is simply a layer on top of commerce that allows you to sell any
entity that implements PurchasableEntityInterface.

### CONTACT

Developed and maintained by Cambrico (http://cambrico.net).

Get in touch with us for customizations and consultancy:
http://cambrico.net/contact

#### Current maintainers:
- Pedro Cambra [(pcambra)](http://drupal.org/user/122101)
- Manuel Egío [(facine)](http://drupal.org/user/1169056)
