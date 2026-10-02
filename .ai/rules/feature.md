---
paths:
  - 'tests/Feature/**'
---

# Feature

## Process::fake git patterns must not assume a bare `git` prefix
On Windows, Symfony renders a `git` array-command commandline as `"C:\Program Files\Git\cmd\git.EXE" describe ...`, so `Process::fake(['git*' => ...])` never matches and the real binary runs. Match on distinctive substrings (`'*--abbrev=0*'`, `'*describe*'`, `'*porcelain*'`) that are platform-neutral, and use closures for `Process::assertRanTimes()` because the recorded `$process->command` is an array on Windows (and `assertRanTimes` with a string does an exact `===` match).
