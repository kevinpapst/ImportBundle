# Changelog

## 2.1.3

Compatible with Kimai 2.0

- Fixed: deactivate listener for import, reducing extra DB queries (now 1k imported rows = ~7k queries, instead of 22k before)
- Fixed: improved boolean parsing
  - strings `yes`, `true`, `1` = true
  - everything else (e.g. `no`, `false`, `0`, empty string) = false

## 2.1.2

Compatible with Kimai 2.0

- Fixed: catch any Exception during CSV import

## 2.1.1

Compatible with Kimai 2.0

- Fixed: use UserService to create new user with default settings

## 2.1.0

Compatible with Kimai 2.0

- Added: new tabs for different importer
- Added: support creating user during import
- Added: new importer for Clockify migrations
- Added: option to choose between global and project-specific activities
- Fixed: Highlight errors

## 2.0.2

Compatible with Kimai 2.0

- Fixed: proper error handling for invalid date-times
- Fixed: proper error handling for unknown users

## 2.0.1

Compatible with Kimai 2.0

- Fixed: fixed import form validation
- Fixed: replaced "Sensio-FrameworkExtraBundle" with Symfony attribute

## 2.0

Compatible with Kimai 2.0

- Fixed: compatibility with 2.0
- Added: moved Kimai 1 import command from core to plugin 

## 1.2

Compatible with Kimai 1.22.0

- Fixed: import of float values for "rate", "hourly rate", "fixed rate" and "internal rate"

## 1.0

Compatible with Kimai 1.21.0

- Initial version with support for:
  - Customer
  - Project
