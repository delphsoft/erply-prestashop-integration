prestashop-integration
======================

Prestashop integration module

This component is provided as open source and without warranty.
Hiring a PrestaShop expert to do the setup and provide ongoing support is highly recommended.

Please see: http://erply.com/integrations-with-partners/erply-integration-with-prestashop/

### Requierments:
PHP 5.5 min

### Usage:
1. Enter Erply login data.
2. Get Warehouse Locations
3. Choose Warehouse for Export.
4. Sync 'Prestashop => Erply' to sync Customers, Product Categories, Product Attributes, Product Combinations and Product Quantities

NB! If one Reference Code is used for multiple Product Combinations in Prestashop, then it is necessary to uncheck 'Product codes must be unique' in Erply, Settings->Configuration.

5. To export a Prestashop Order in Prestashop admin area, Orders->Any order from list->Sync Order (Below).

If Export fails in the middle of Product export in case of 'request limit reached', it is possible to continue export after the specified time (1 hour atm).

Customer, Category and Attribute Export does not support continuing atm.

It is possible to export everything manually on Configuration page.

### Action Hooks in Prestashop:
1. Automatically syncs Product to Erply on any Product Update or Delete, including Quantities and Combinations (except initail Product Save, which lacks combinations and such).
2. Auto sync on Category Add & Delete.
3. Auto sync on Attribute Add & Delete.

### Caveats:
1. Sync/Categories.php global presta locale name is assigned to erply 'name' request attr.
2. Sync/Products.php images must use JPEG format (set in prestashop Preferences -> Images).
3. Sync/Products.php existing erply matrixes are compared by default language.
4. Sync/Products.php 'Product codes must be unique' must to be unchecked in erply backend if Prestashop Reference code is not unique for each product combination.
5. Sync/Products.php Erply allows maximum 3 Matrix Variations per product. The first 3 Prestashop Product Attributes that are in the array retured by Product::getAttributesGroups are used.
6. Sync/Attributes.php Changing exported matrix dimensions in Erply will lead to failure in sync.
7. Sync/Attributes.php Only new Attribute Create and Delete is synced, not update.
8. Sync/Categories.php Only new Category Create and Delete is synced, not update.
9. Sync/Products.php image sync is turned off
10. No errors shown on Product Update & Delete. Module configuration page indicates if any errors happened.
11. No errors shown on Attribute & Category Save & Delete. Module configuration page indicates if any errors happened.
12. Order resync not working correctly (initial sync OK).
13. Customer, Category and Attribute Export does not support continuing.
14. No import from Erply.
15. Error list action buttons don't do anything.
16. Not everything can be deleted in the mapping list