# Behat DKAN Context

This creates a feature context for DKAN and NuCivic specific steps.

## Install

1. Create a ``composer.json`` file with the following:

```json
{
  "require": {
    "nucivic/dkanextension": "dev-master"
  },
  "config": {
    "bin-dir": "bin/"
  },
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

* ``Given datasets``
* ``Given resources``
* ``Given groups``
* ``Given groups memberships``

Example:

```yaml
 Background:
    Given users:
      | name    | mail             | roles                |
      | John    | john@test.com    | administrator        |
    Given groups:
      | title    | author | published |
      | Group 01 | Admin  | Yes       |
    And group memberships:
      | user    | group    | role on group        | membership status |
      | Gabriel | Group 01 | administrator member | Active            |
    And datasets:
      | title      | format | author  | published        | Date         | tags   |
      | Dataset 01 | CSV    | Gabriel | Yes              | Feb 01, 2015 | Health |
    And resources:
      | title       | dataset    | published |
      | Resource 01 | Dataset 01 | Yes       |
```


## TODO

- [ ] Make sure scripts works on install
- [ ] Add tests
- [ ] Deploy on DKAN and related modules

## Hat tip
Scripts directory inspired by https://github.com/BR0kEN-/behat-drupal-propeople-context
