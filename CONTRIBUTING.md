# Contributing to search-debug

Thanks for considering a contribution — issues and PRs are welcome. This is a single-maintainer
open-source project, so response times may vary.

## Getting started

```
composer install
```

Requires PHP 8.3+ (CI also runs against 8.4). This package is a Spryker module: several of its
classes only make sense wired into a real Spryker shop (Zed/Yves/Client layers, Elasticsearch/
OpenSearch documents). If you're working on a change that needs to be exercised end-to-end, you'll
need a Spryker demo shop with this package installed as a local path repository — see the README's
Installation section.

## Before opening a PR

These are the checks CI runs; running them locally first saves a review round-trip:

```
composer validate --no-check-publish
vendor/bin/phpcs
vendor/bin/phpstan analyse -c phpstan.neon src/
vendor/bin/phpmd src text phpmd.xml
vendor/bin/phpmd src text phpmd-public-methods.xml
composer rector-dry-run
composer check-floors
```

`check-floors` re-resolves dependencies to the lowest versions allowed by `composer.json` and
asserts every vendor symbol used in `src/` still exists at that floor — it's the check most likely
to catch an accidental "works on my shop" dependency bump.

The functional test suite (`tests/SprykerCommunityTest`) is Codeception-based and needs a running
Spryker shop (MySQL + Elasticsearch/OpenSearch) to execute; it isn't runnable via `composer install`
alone. If you can't spin one up, open the PR anyway — CI covers style/static-analysis/dependency-floor
checks, and the functional suite will be run before merging.

## Making a change

- Keep PRs focused — one change per PR.
- Branch from and target `main`; branches are merged via squash, so intermediate commit messages
  don't need to be polished.
- Match the existing code style — `phpcs` and `rector-dry-run` above catch most deviations.
- Update `README.md`/`docs/` when behavior changes.

## Reporting bugs or requesting features

Use the issue templates — they ask for the information needed to reproduce a bug or evaluate a
request. For security issues, see [SECURITY.md](SECURITY.md) instead of opening a public issue.

## License

By contributing, you agree your contribution is licensed under this project's [MIT license](LICENSE).
