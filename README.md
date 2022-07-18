# Import plugin for Kimai

Import data features for Kimai.

## Features

TODO

## Installation

This plugin is compatible with the following Kimai releases:

| Bundle version | Minimum Kimai version |
|----------------|-----------------------|
| 1.0            | 1.21                  |

You find the most notable changes between the versions in the file [CHANGELOG.md](CHANGELOG.md).

### Copy files

Extract the ZIP file and upload the included directory and all files to your Kimai installation to the new directory:  
`var/plugins/ImportBundle/`

The file structure needs to look like this afterwards:

```bash
var/plugins/
├── ImportBundle
│   ├── ImportBundle.php
|   └ ... more files and directories follow here ... 
```

### Clear cache

After uploading the files, Kimai needs to know about the new plugin. It will be found, once the cache was re-built:

```bash
cd kimai2/
bin/console kimai:reload --env=prod
```

## Updating the plugin

Updating the bundle works the same way as the installation does.

- Delete the directory `var/plugins/ImportBundle/` (to remove deleted files)
- Execute all installation steps again:
    - Unzip latest package & copy files
    - Clear cache

## Screenshots

Screenshots are available [in the store page](https://www.kimai.org/store/import-bundle.html).
