Plugin Phing
============

This plugin allows you to use the Phing build system to build your project.

Configuration
-------------

### Options

* **allow_failures** [bool, optional] - If true, allow the build to succeed even if this plugin fails.
* **directory** - Relative path to the directory in which you want to run phing.
* **build_file** - Your phing build.xml file.
* **targets** - Which build targets you want to run.
* **properties** - Any custom properties you wish to pass to phing.
* **property_file** - A file containing properties you wish to pass to phing.
* **binary_name** [string|array, optional] - Allows you to provide a name of the binary.
* **binary_path** [string, optional] - Allows you to provide a path to the binary.

### Examples

```yml
phing:
      build_file: 'build.xml'
      targets:
        - "build:test"
      properties:
        config_file: "php-censor"
```
