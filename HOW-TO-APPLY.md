# How to apply this update to the repo

Copy everything in this folder into the root of your local clone of `moodle-block_aipdfassistant` (overwrite README.md; keep your existing code unchanged), then:

```bash
git add -A
git commit -m "Docs: marketplace link, screenshots, icon, CI, issue templates, security policy"
git push
git tag -a v0.1.1 -m "0.1.1"          # skip if the tag already exists
git push origin v0.1.1
```

Then on GitHub: Releases > Draft a new release > choose tag v0.1.1 > paste the text of `RELEASE_NOTES_v0.1.1.md`.
Also: set the repository website to the marketplace link, add topics (`moodle`, `moodle-plugin`, `moodle-block`, `ai`, `pdf`, `edtech`), and use `docs/images/feature-grid.png` as the social preview (Settings > General).

Note: `.github/workflows/ci.yml` will run on the first push. Expect some phpcs/phpdoc findings: those two steps are set to `continue-on-error` so they will not block, but read them.
