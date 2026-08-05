## What & why



## Checklist

- [ ] `composer validate --no-check-publish` passes
- [ ] `vendor/bin/phpcs` passes
- [ ] `vendor/bin/phpstan analyse -c phpstan.neon src/` passes
- [ ] `vendor/bin/phpmd src text phpmd.xml` / `phpmd-public-methods.xml` pass
- [ ] `composer rector-dry-run` passes
- [ ] `composer check-floors` passes
- [ ] Tests added/updated, or explained why not needed
- [ ] `README.md`/`docs/` updated if behavior changed
