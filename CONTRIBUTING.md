# Contributing

RAN Plugin Library follows the Rockets Are Nostalgic organisation community-health baseline. Keep public contributions free of credentials, secrets, private source or repository/site identities, customer data, full production logs, vulnerability details, and other sensitive material.

## Development

Install dependencies from the tracked Composer lockfile and run the repository quality gate before proposing a change:

```sh
composer install --no-interaction --prefer-dist
composer qa
```

Run `composer test:integration` when the changed boundary requires the integration suite. Use `composer format` for the repository-owned formatting pass and rerun `composer qa` afterwards.

Follow the repository's current source conventions and tests rather than copying assumptions from another RAN package. Changes to public APIs or compatibility behavior should include focused tests and corresponding documentation.

## Contributions

Use Conventional Commit subjects for commits and pull-request titles where the repository's merge/release policy requires them. Keep changes focused and update documentation when public behavior changes.

By submitting a contribution, you agree that it may be distributed under this project's MIT License.

Use the repository issue chooser for ordinary non-sensitive bugs, feature requests, and support. Vulnerabilities must be reported through GitHub's private vulnerability-reporting route when available; never put vulnerability details in a public issue or pull request. If private reporting is unavailable, use the public security-reporting-help route only to request confidential contact and include no technical vulnerability details.

The organisation Code of Conduct, security, support, issue and pull-request defaults are inherited from `RocketsAreNostalgic/.github` unless this repository adds a justified local override.
