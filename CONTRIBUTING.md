<!--
davidbel/magento-ai-search by David Belicza
SPDX-License-Identifier: MIT
https://github.com/DavidBelicza/Magento-AI-Search
-->

# Contributing

Bug reports, fixes, and focused improvements are welcome. By participating, you agree to the
[Code of Conduct](CODE_OF_CONDUCT.md).

## Report an issue

Use the provided GitHub issue forms for bug reports and feature requests. Search existing
issues first to avoid duplicates. Report security vulnerabilities privately as described in
[SECURITY.md](SECURITY.md).

## Development setup

The repository is a standalone Magento Composer package. Install its development dependencies
from the package root:

```shell
composer install
```

The package requires PHP 8.3 or newer. A Magento installation is needed only for integration
and manual runtime verification.

## Quality checks

Run the complete package quality suite before submitting a change:

```shell
composer qa
```

The suite validates Composer metadata, audits dependencies, checks PHP syntax and coding
standards, runs static analysis, and executes the PHPUnit tests.

Behavior changes should include focused tests where practical. Keep changes limited to one
clear concern and explain any Magento, database, OpenSearch, or compatibility impact.

## Code style

- Follow the existing directory and namespace structure.
- Use explicit class, method, and variable names.
- Keep Magento extension points compatible with plugins, preferences, and generated classes.
- Include the existing package header in every new project file.
- Avoid unrelated refactoring in the same contribution.

## Pull requests

Describe what changed, why it is needed, and how it was verified. All required CI checks must
pass before a change can be reviewed for inclusion.
