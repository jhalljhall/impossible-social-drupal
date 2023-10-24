# Webform Product

Webform Product can create a Commerce order from any Webform submission.

This module can be used for websites that have commerce for payment of 
predefined product types, but in need of a more flexible product for temporary 
product types or highly customisable product types, like a quick donation form 
or a promotional product.

With Webform you can create simple or very complex forms, combine this with 
the easy to setup handler and you got a new product, ready to be paid with 
a payment provider defined in Drupal Commerce.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/webform_product).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/webform_product).

## Requirements

This module requires the following modules:

- [Webform](https://www.drupal.org/project/webform)
- [Commerce Core](https://www.drupal.org/project/commerce)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

For more details on the configuration view the following video:
[Drupal Webform Product module](https://www.youtube.com/watch?v=zf1ZGKZVGQQ).

1. Create a webform
2. Add elements
   1. Order ID (Number)
   2. Order URL (URL)
   3. Order status (Text field)
   4. Product (Select)
3. Set permissions for 'Order *'-fields to be visible only for administrators.
4. Setup the values of the element Product
   1. Use the option value and price to simulate product variations
   2. Use the Price inside Element settings below to create multiple 
   products with one price
5. Setup the Webform Product Handler and map the fields 
(Payment status, Order ID, Order URL)
6. Create a commerce link-field (no title) in the Order Type selected at the 
handler called 'field_link_order_origin'. (admin/commerce/config/order-types)
7. Save the webform and create a submission

## Known issues
The module currently only works well with off-site payment providers 
(for example: saferpay or paypal).
A workaround could be to select a different "Checkout step" for the 
Webform Product Handler. "Order information" or "Review" may get in 
early enough in the process.
