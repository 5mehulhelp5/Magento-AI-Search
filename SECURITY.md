<!--
davidbel/magento-ai-search by David Belicza
SPDX-License-Identifier: MIT
https://github.com/DavidBelicza/Magento-AI-Search
-->

# Security Policy

## Reporting a vulnerability

Report suspected security vulnerabilities privately through GitHub:

**[Report a vulnerability][private-report]**

Do not open a public issue for a security vulnerability. Include the affected module version,
Magento version, steps to reproduce the issue, and the potential impact.

There is currently no bug bounty program.

## Supported versions

Security fixes are provided for the latest published version. Earlier versions are not
supported and should be upgraded.

| Version | Supported |
|---|---|
| Latest published version | Yes |
| Earlier versions | No |

## Operational security

- Product content and search queries may be sent to the configured remote AI server. Review
  that provider's data-handling policy before enabling the integration.
- Use HTTPS for remote AI server endpoints.
- Store API keys in Magento configuration using the provided encrypted configuration field.
- Restrict access to the Magento Admin, database, logs, OpenSearch, and the AI server according
  to normal production security practices.
- Keep Magento, OpenSearch, PHP, and Composer dependencies updated with current security fixes.

This policy covers the Magento AI Search module. It does not cover Magento, OpenSearch, or any
third-party AI server configured by the user.

[private-report]: https://github.com/DavidBelicza/Magento-AI-Search/security/advisories/new
