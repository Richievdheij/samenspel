# Branching

**Nobody commits to `main`.** Work happens on `develop`, or on a branch cut from
it. That is the whole rule; the rest of this page is why, and how.

## The three kinds of branch

| Branch           | What it means                                         | Who writes to it              |
| ---------------- | ----------------------------------------------------- | ----------------------------- |
| `main`           | Always deployable. The last thing that was released.  | Nobody directly — only merges |
| `develop`        | Where finished work is integrated and tested together | Merges from feature branches  |
| `feature/<name>` | One piece of work, start to finish                    | One person                    |

Two more, used only when they are needed:

| Branch          | For                                                          |
| --------------- | ------------------------------------------------------------ |
| `fix/<name>`    | A defect, cut from `develop` like any other work             |
| `hotfix/<name>` | A production emergency, cut from `main` and merged into BOTH |

## The normal day

```bash
git switch develop
git pull

git switch -c feature/example-list      # one branch, one piece of work
# ... work, committing as you go ...
make verify                             # about five seconds
git push -u origin feature/example-list
# open a pull request into develop
```

When it is reviewed and CI is green, merge it into `develop`. When `develop` is
worth releasing, merge `develop` into `main`.

## Why not just commit to main

Three reasons, and none of them is ceremony:

1. **`main` stays deployable.** If every commit lands on `main`, then `main` is
   whatever the last person happened to push, and nobody can tell whether it
   works. With this model the answer is always yes, by construction.
2. **Review has somewhere to happen.** A pull request is a diff someone can read
   before it is permanent. Committing straight to `main` gives reviewers nothing
   to look at until it is already shipped.
3. **The gates get somewhere to run.** CI runs on pull requests into `develop` and
   `main`, so a red gate blocks a merge rather than arriving after the fact.

## Naming

`type/short-description`, lower case, hyphens, no spaces — the same vocabulary as
the commit grammar:

```
feature/example-list
fix/vite-manifest-missing
hotfix/session-driver
```

Keep a branch small enough that it merges within a few days. A branch that lives
for three weeks stops being a branch and becomes a second project.

## How this connects to the toolchain

- **`make verify` before every push.** The `pre-push` hook runs it for you; it
  takes about five seconds.
- **`make commit-messages` judges what your branch adds.** It resolves the range
  against `origin/develop` or `origin/main`, so it reports on your commits and not
  on everybody else's.
- **CI runs on pull requests, and on pushes to `main` and `develop`.** Feature
  branches are covered through their pull request.
- **Merging keeps the merge commit.** `Merge` subjects are exempt from the commit
  grammar, deliberately: refusing a merge message would fail _after_ the merge, with
  a full index and no clean way back.

## Working together on one branch

Sometimes two people genuinely need the same branch. Then:

- **Pull before you push.** `git pull --rebase` keeps the history linear and
  avoids a merge commit for every exchange.
- **Push often.** A branch that lives only on your laptop is invisible to everyone
  and unrecoverable if the laptop dies.
- **Never force-push a shared branch.** It rewrites history under someone else's
  feet. If you must — and it is rare — say so first, and use
  `--force-with-lease`, which refuses when the remote has moved.

## The one deliberate exception

In the last 72 hours before a deadline, working-but-red beats blocked-and-clean.
`git push --no-verify` skips the local gate and CI still runs. This is agreed out
loud, in advance, so that nobody has to ask permission at the worst moment. See
[toolchain.md](toolchain.md).
