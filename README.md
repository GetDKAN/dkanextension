# Behat DKAN Context

This creates a feature context for DKAN and NuCivic specific steps.

## Install

1. Create a ``composer.json`` file with the following:

```yml
{
  "require": {
    "nucivic/dkanextension": "dev-master"
  },
  "config": {
    "bin-dir": "bin/"
  }
  "scripts": {
    "post-install-cmd": "mv bin/bddkan bin/behat"
  }
}
```

2. Install dependencies: ``composer install``

3. Initialize: ``behat --init`` 

You should have a ``features/bootstrap/DKANFeatureContext.php`` file that inherets ``DKANContext``.

## Steps

Some of the included steps are:

* ``Given Dataset``
* ``Given Group``

## TODO

- [ ] Make sure scripts works on install
- [ ] Add tests
- [ ] Deploy on DKAN and related modules

## Hattip
Scripts directory inspired by https://github.com/BR0kEN-/behat-drupal-propeople-context
