Plugin PHPLoc
=============

Runs [PHPLoc](https://github.com/sebastianbergmann/phploc) against your project and records some key metrics.

Configuration
-------------

### Options

* **allow_failures** [bool, optional] - If true, allow the build to succeed even if this plugin fails.
* **directory** - Optional - The directory in which phploc should run. 
* **binary_name** [string|array, optional] - Allows you to provide a name of the binary.
* **binary_path** [string, optional] - Allows you to provide a path to the binary.
* **ignore** 

### Example

Run PHPLoc against the app directory only. This will prevent inclusion of code from 3rd party libraries that are 
included outside of the app directory.

```yml
test:
  php_loc:
    directory: "app"
```
