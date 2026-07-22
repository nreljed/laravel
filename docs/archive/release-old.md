# Release Process

## Automated Release

Releases are created automatically by the CI/CD pipeline on push to `main`:

1. `git-cliff` parses commit history
2. Bumps semver based on conventional commits:
   - `feat:` → minor bump
   - `fix:` → patch bump
   - `BREAKING CHANGE:` → major bump
3. Generates `CHANGELOG.md` + `release-notes.md`
4. GitHub Release created with tag `vX.Y.Z`
5. `deploy.zip` attached as release asset

## Manual Release

```bash
# Generate changelog for unreleased changes
npx git-cliff -o CHANGELOG.md --unreleased

# Commit and push
git add CHANGELOG.md
git commit -m "chore(release): v1.2.3"
git tag v1.2.3
git push && git push --tags
```

## Versioning

Follows [Semantic Versioning](https://semver.org/):

- **MAJOR** — Breaking API/DB changes
- **MINOR** — New features, non-breaking
- **PATCH** — Bug fixes, hotfixes
