# Asseteer
_Composer post-install hook to copy external static resources to their public http location_

## Why
Your PHP application uses external static resources (js/css/images/fonts) that are publicly available (eg. jQuery, Bootstrap etc.).
You can use Composer to handle the static files as regular dependencies of your project, but you need to place them inside your HTTP root tree, rather than their Composer vendor location.

Asseteer is a dependency for yor project, that you can invoke at "composer install" goal, in order to perform the file-copy operations.

## Usage
You pilot Asseteer from the `composer.json` file:

- Declare the extenral files to download, as regular `require` dependencies.
- Optionally define their specifics (URL to download) in the `packages` section. 
- Then, configure the copy operations in the `extra` section.
- Finally, invoke the `post-install-cmd` hook in `scripts` section.

Suppose your application uses jQuery.

##### `require` section

~~~~javascript

  "require": {
    ...
    "static-assets/jquery": "2.1.3",
    ...
  }
~~~~
