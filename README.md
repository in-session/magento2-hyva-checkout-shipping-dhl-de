DHL Germany partner module for Magento

- Marketplace Link: https://commercemarketplace.adobe.com/dhl-shipping-m2.html
- Github Link: https://github.com/netresearch/dhl-shipping-m2
- Docs: https://github.com/netresearch/dhl-module-carrier-paket/wiki/Documentation-English
- Reference: https://www.dhl.de/de/geschaeftskunden/paket/versandsoftware/partnersysteme/magento.html

## Features
- Print DHL labels for parcels: Fast and easy label creation of your standard domestic and cross border orders incl. its optional services
- Print Warenpost labels for national and international shipping of small-format goods
- Print Deutsche Post Internetmarke stamps: Fast and easy stamp creation for postal deliveries of your suitable domestic and cross border orders
- Create Auto tracking codes: Automatically receive a tracking code for each label you create (if tracking is available for the selected product)
- Use additional delivery services: such as preferred delivery services (delivery day, drop-off location, neighbor), visual check of age or Packstation delivery 
- Bulk printing: print a number of labels at the same time
- Review order fulfillment status: check whether or not a shipping label was created for an order by a separate Post & DHL Label Status column in the order list
- Delete labels or reprint labels: in case your printer experienced any issues.
- Offer return shipments: enables merchants and consumers to create DHL return shipment labels, either together with the regular shipping label or on-demand. The DHL Feature to request, store and track on-demand return labels is now also available for the Open Source editions (Community editions).
- Deutsche Post DATAFACTORY AUTOCOMPLETE: Customers can now supplement their postal data easily. As soon as your customers enter the first alphanumerical characters in an address input field, they will receive up to 15 suggestions for valid German addresses. (an optional service of the 100% subsidiary: Deutsche Post Direkt)
- Deutsche Post ADDRESSFACTORY DIRECT: enables to check the customer’s delivery address in Germany before shipping the order (an optional service of the 100% subsidiary: Deutsche Post Direkt)

## Supported Features: 
- [X] Frontend Checkout:
    - [X] DHL Packstation, a parcel store or a post office branch (MapBox API token)
          RestApi: rest/de/V1/nrshipping/delivery-locations/dhlpaket/search
    - [X] Delivery Day
    - [X] Drop-off Delivery
    - [X] Package announcement
    - [X] Preferred neighbor delivery
    - [X] No neighbor delivery
    - [X] Drop-off location
    - [X] GoGreen plus
- [ ] Submoduls (Optional):
    - [ ] ADDRESSFACTORY - https://commercemarketplace.adobe.com/deutschepost-module-autocomplete-m2.html
    - [ ] DATAFACTORY Autocomplete - https://commercemarketplace.adobe.com/deutschepost-module-addressfactory-m2.html
