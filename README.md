# Magento 2 Module - Reports Module

![https://www.augustash.com](http://augustash.s3.amazonaws.com/logos/ash-inline-color-500.png)

**This is a private module and is not currently aimed at public consumption.**

## Overview

The `Augustash_Reports` module changes how dashboard report totals are calculated. Due to many integrations leaving Magento orders in a pending or processing state, this module includes those orders in the totals.

## Installation

### Via Local Module

Install the extension files directly into the project source:

```bash
mkdir -p app/code/Augustash/Reports/
git archive --format=tar --remote=git@github.com:augustash/magento2-module-reports.git 0.9.0 | tar xf - -C app/code/Augustash/Reports/
bin/magento module:enable --clear-static-content Augustash_Reports
bin/magento setup:upgrade
bin/magento cache:flush
```

### Via Composer

Install the extension using Composer using our development package repository:

```bash
composer config repositories.augustash composer https://packages.augustash.com/repo/private
composer require augustash/module-reports:~0.9.0
bin/magento module:enable --clear-static-content Augustash_Reports
bin/magento setup:upgrade
bin/magento cache:flush
```

## Uninstall

After all dependent modules have also been disabled or uninstalled, you can finally remove this module:

```bash
bin/magento module:disable --clear-static-content Augustash_Reports
rm -rf app/code/Augustash/Reports/
composer remove augustash/module-reports
bin/magento setup:upgrade
bin/magento cache:flush
```

## Structure

[Typical file structure for a Magento 2 module](http://devdocs.magento.com/guides/v2.3/extension-dev-guide/build/module-file-structure.html).
