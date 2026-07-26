---
title: Establish the pre-change baseline before calling an authorization change a regression
date: 2026-07-26
category: workflow-issues
module: security-review
problem_type: workflow_issue
component: development_workflow
severity: medium
related_components:
  - authentication
applies_when:
  - Reviewing a branch diff for authorization or permission regressions
  - A finding claims a resource lost an access check that the base branch never had
  - The diff includes a framework or major-version API port that rewrites surrounding lines
  - Working in a framework that allows by default when no policy is defined
  - Triaging discovery-agent findings before reporting them to a human
symptoms:
  - Two MEDIUM security findings both rejected as false positives by verifiers
  - A default-deny gate reported as an authorization_bypass
  - Pre-existing role-picker code reported as newly introduced privilege_escalation
  - Verifier confidence as low as 2/10 on a reported finding
root_cause: missing_workflow_step
resolution_type: workflow_improvement
tags:
  - security-review
  - false-positive
  - git-baseline
  - authorization
  - filament
  - framework-upgrade
  - code-review-workflow
  - diff-analysis
---

# Establish the pre-change baseline before calling an authorization change a regression

## Context

A `/security-review` ran on the `feat/base-release` branch of the SparkCommerce package (no PR is open for it as of this writing; the work sits on the branch). The workflow was: one discovery agent read the branch diff against `main` and looked for newly-introduced vulnerabilities, then two verifier agents each attacked one candidate finding.

The discovery agent raised two MEDIUM findings. Both were rejected as false positives. Both failed for the same reason: the discovery agent read only the diff. It never checked what the code did **before** the branch.

- **Candidate 1** claimed an `authorization_bypass`. A new trait overrides `canAccess()` with a Gate check, and the claim was that this *replaces* the host app's model policy. The truth was the opposite: before this branch there was no check at all, so the new gate is a tightening.
- **Candidate 2** claimed a `privilege_escalation`. An unrestricted role picker and an unguarded impersonate action showed up as `+` lines in the diff. But they were already on `main`, byte for byte. The `+` lines came from a Filament 3 to Filament 5 API port that rewrote the code *around* them.

Both mistakes are diff-reading mistakes. A diff shows what moved. It does not show what the old behavior was, and it does not show whether a risky line is new or just relocated.

## Guidance

### 1. Before flagging an authorization regression, prove the old code was stricter

A missing check is not a strict check. In frameworks that allow-by-default when no policy exists, "this resource had no explicit authorization" means "everyone who could reach the panel could open it."

So adding a gate on top is **tightening**, even when the new gate does not also chain to the host app's policy. Failing to chain is a hardening suggestion, not a vulnerability the branch introduced. Write it up as:

```php
// Hardening suggestion, not a finding:
public static function canAccess(): bool
{
    return Gate::allows('access-sparkcommerce-admin') && parent::canAccess();
}
```

Run this first, before you write a single word of the finding:

```bash
# Did the base branch have ANY authorization surface in the changed area?
git grep -nE "authorize|Gate|policy|canAccess|canViewAny" main -- src/

# What framework major was the base branch on? The default behavior may differ.
git show main:composer.json | grep -n filament
```

In this session the first command returned **zero hits** on `main` (exit code 1). That single fact killed candidate 1.

Then confirm what the framework does when no policy is present. Do not assume — read the vendor source:

```bash
grep -rn "function canAccess" vendor/filament/filament/src/Resources/Resource/Concerns/HasAuthorization.php
grep -n "function get_authorization_response" -A 45 vendor/filament/filament/src/helpers.php
```

### 2. A framework-version port makes old risky code look new

When a branch ports code from one framework major to another, the mechanical rename touches the lines *next to* unchanged logic. Git then shows the unchanged logic as an addition, because the surrounding hunk changed.

Reading diff hunks cannot tell you if a construct is new. Verify with the file at the base revision:

```bash
# Read the whole old file, not the diff.
git show main:src/Filament/Resources/UserResource.php

# Or grep the old file for the exact construct you are worried about.
git show main:src/Filament/Resources/UserResource.php | grep -n "Role::all\|Impersonate"
git show main:src/Filament/Resources/UserResource/Pages/CreateUser.php | grep -n "assignRole"
git show main:src/Filament/Resources/UserResource/Pages/EditUser.php  | grep -n "syncRoles"
```

Compare the **construct**, not the surrounding API names. `Forms\Form` becoming `Schemas\Schema` is a rename. `Role::all()` with no allow-list on both sides is the same risk on both sides.

### 3. The cheapest baseline source is often not git

On a release branch, the repo usually already tells you what changed and in which direction. Read these before reaching for git:

```bash
grep -n -i "gate\|role\|access\|auth" UPGRADE.md CHANGELOG.md
```

Here, `UPGRADE.md:57` says existing admins are **locked out** of the SparkCommerce resources until they are assigned `sc_admin`. A breaking-change note about users losing access is the exact opposite of an authorization bypass. That one line would have killed candidate 1 in seconds. `CHANGELOG.md:21` lists the gate under **Added**, not Changed — meaning no prior control existed.

If the branch came from a written plan, read it too. The plan that produced this branch records the gate as new and as a hardening that came out of an earlier security review. Note the trap: that plan lives in the untracked parent app tree (`/Users/rahatbaksh/Herd/sparkphp/docs/plans/`), not in the package repo, so a reviewer scoped to the package cannot reach it through git history. Check the surrounding tree, not only the repo.

### 4. Separate "pre-existing risk" from "introduced by this change"

Both can be true at once. A finding that is real but pre-existing belongs in a different bucket than a finding this branch created. Say which bucket, and say why. If the review question is "what did this PR introduce," a pre-existing gap is out of scope for the verdict, but it is still worth naming as follow-up work.

Do not let a false-positive rejection be misread as "this code is fine." Candidate 2's underlying risk is real; only its novelty was wrong.

### 5. Check whether the risky code is even reachable by default

Before assuming a resource is exposed, read the plugin or module registration and see if it is on by default. Opt-in code has a smaller blast radius than always-on code.

### 6. Sweep the whole branch for authorization, not just the flagged lines

A diff-local reading can miss that the same branch adds authorization elsewhere. This branch also added `Gate::allows(...)` to the refund action's visibility and `Gate::authorize(...)` inside its handler (`src/Filament/Resources/OrderResource.php:213` and `:239`). Seeing the branch add two independent checks on the money path makes the "this branch bypasses authorization" reading untenable.

### 7. Cite PR numbers, not commit SHAs

Rebases and squash merges rewrite SHAs, so a SHA cited in a doc goes dead. Reference the PR number. If no PR exists yet, name the branch and say "as of this writing," as this doc does.

## Why This Matters

**A false positive on an authorization finding is expensive.** It reads as urgent. Someone stops what they are doing, re-reads the framework's authorization internals, and often "fixes" a non-problem. Here, two MEDIUM findings consumed two full verifier agents, and both landed at "no change needed."

**Worse, it inverts the signal.** Candidate 1 flagged the exact change that *closed* the hole. The branch moved SparkCommerce from "no authorization at all" to a default-deny gate. Reporting that as a bypass teaches the wrong lesson and could get a real security improvement reverted.

**And it hides the real work.** Candidate 2 found two genuine gaps — no allow-list on the role picker, and an unguarded impersonate action. Because they were framed as "this PR introduced them," and that framing was wrong, the whole finding was dropped. The correct framing — "pre-existing on `main`, worth fixing separately" — would have kept them alive as follow-up.

The cost of the check is one `git grep` and one `git show`. Seconds. The cost of skipping it is a wrong verdict on the highest-stakes category of review.

## When to Apply

- Any security or code review scoped to "what did this branch/PR introduce."
- Any finding about authorization, permissions, roles, or access control on a diff.
- Any branch that includes a framework or library major-version upgrade, where mechanical API renames inflate the diff.
- Any time you are about to write "this replaces the existing check" — stop and prove an existing check existed.
- Any finding where the fix would be "revert this line." Reverting to a weaker state is a real risk.

Skip it only when you are reviewing a whole file or a greenfield module with no base-branch history to compare against. Then say so explicitly, because the review is "is this code safe," not "did this change make it less safe."

## Examples

### Example 1 — "The new gate replaces the host policy" (rejected, confidence 2/10)

**The diff.** `src/Filament/Concerns/HasSparkCommercePanelAccess.php:9-12` adds:

```php
public static function canAccess(): bool
{
    return Gate::allows('access-sparkcommerce-admin');
}
```

The trait is used by all seven resources (`ProductResource`, `CategoryResource`, `ReviewResource`, `OrderResource`, `CouponResource`, `TagResource`, `UserResource`).

**The claim.** Filament's default `Resource::canAccess()` calls `canViewAny()`, which asks the host app's model policy. This override drops that. So a host app with a strict `UserPolicy::viewAny` silently loses it.

The first half of the claim is correct. Verified at `vendor/filament/filament/src/Resources/Resource/Concerns/HasAuthorization.php:28-31`:

```php
public static function canAccess(): bool
{
    return static::canViewAny();
}
```

**What the baseline check showed.**

```bash
$ git grep -nE "authorize|Gate|policy|canAccess|canViewAny" main -- src/
$ echo $?
1
```

Zero hits. On `main` the package targeted Filament 3 (`composer.json:27` on `main` was `"filament/filament": "^3.0"`; the branch moves it to `"~5.0"`). No resource overrode `canAccess()` or `canViewAny()`, and the package ships no policies — the only `*Polic*` file under `src/` is `src/Enums/BackorderPolicy.php`, an inventory enum, not an authorization policy.

**And Filament allows by default when no policy exists.** In `vendor/filament/filament/src/helpers.php`, `get_authorization_response()` (declared at line 31) looks up the policy at line 60 and only delegates to the Gate when the policy exists and has the method (lines 62-64). Otherwise it falls through to the before-callbacks at line 81 and returns `Response::allow()` at line 92 when nothing denied. The strict mode that would throw instead is off by default — `protected bool | Closure $isAuthorizationStrict = false;` at `vendor/filament/filament/src/Panel/Concerns/HasAuth.php:105`.

**So the real direction of change:**

| | `main` | `feat/base-release` |
|---|---|---|
| Resource-level check | none | `Gate::allows('access-sparkcommerce-admin')` |
| Effect for a user with no policy | allowed | denied unless they hold `sparkcommerce.admin_role` |

The new gate at `src/SparkCommerceServiceProvider.php:186-204` is default-deny: it has two explicit deny branches — a missing or empty `sparkcommerce.admin_role` returns `false` (lines 195-197), and a null user or a user model without `hasRole()` returns `false` (lines 199-201) — and only then does access become a positive requirement to hold the role, `$user->hasRole($adminRole)` (line 203). The default role is `sc_admin` (`config/sparkcommerce.php:17`), with a `panel_gate` escape hatch defaulting to `null` (`config/sparkcommerce.php:24`). That override seam is itself a way for a host to delegate back to its own policies, which further weakens the "bypasses host policies" framing.

**Scope of what actually widens.** The trait overrides only `canAccess()`, not `can()`. Per-record abilities still route through `getAuthorizationResponse()` (`vendor/filament/filament/src/Resources/Resource/Concerns/HasAuthorization.php:42-45`) and still consult host policies. Only page-level list access and global search skip the policy. That is the hardening suggestion, and it is narrow.

The behavior change is already documented as a breaking upgrade step in `UPGRADE.md` section 3 (lines 44-56), including the instruction to run `php artisan sc:publish-roles` and assign `sc_admin`, with an explicit warning that existing admins are locked out until then.

**Verdict:** false positive. The branch tightened access. Not chaining `parent::canAccess()` is a suggestion to file, not a vulnerability to report.

### Example 2 — "This branch lets `sc_admin` self-assign `super-admin`" (rejected, confidence 8/10)

**The diff.** `src/Filament/Resources/UserResource.php` shows an unrestricted role picker and `Impersonate::make()`, both appearing as `+` lines:

```php
$fields[] = Select::make('role')
    ->options(
        Role::all()->mapWithKeys(fn (Role $role): array => [$role->name => $role->name])
    )
```

**The claim.** The branch introduces `sc_admin` as the operator role and wires `UserResource` to the same gate. So a shop operator can pick `super-admin` for themselves, or impersonate the owner.

**What the novelty check showed.**

```bash
$ git show main:src/Filament/Resources/UserResource.php | grep -n "Role::all\|Impersonate"
18:use STS\FilamentImpersonate\Tables\Actions\Impersonate;
72:                Impersonate::make(),
116:                    Role::all()->mapWithKeys(fn (Role $role): array => [$role->name => $role->name])
```

Same unrestricted `Role::all()`. Same `Impersonate::make()`. Already on `main`. The write paths too:

```bash
$ git show main:src/Filament/Resources/UserResource/Pages/CreateUser.php | grep -n "assignRole"
33:        $user->assignRole($this->roleName);

$ git show main:src/Filament/Resources/UserResource/Pages/EditUser.php | grep -n "syncRoles"
38:            $this->record->syncRoles([]);
44:        $this->record->syncRoles([$this->roleName]);
```

Both already applied the submitted role with no allow-list. The branch diff for these three files is a Filament 3 to 5 API port (`Forms\Form` to `Schemas\Schema`, `Tables\Actions` to `recordActions`, and the impersonate import moving from `STS\FilamentImpersonate\Tables\Actions\Impersonate` to `STS\FilamentImpersonate\Actions\Impersonate`) plus `method_exists()` guards and multivendor decoupling. It does not add or widen the role-assignment path.

**The mutation paths still authorize, unchanged by this branch.** `EditRecord::authorizeAccess()` calls `canEdit($record)` (`vendor/filament/filament/src/Resources/Pages/EditRecord.php:98-101`), and `CreateRecord::authorizeAccess()` calls `canCreate()` (`vendor/filament/filament/src/Resources/Pages/CreateRecord.php:67-70`). Those route through `getAuthorizationResponse()`, which the trait does not override — it overrides `canAccess()` only.

**Reachability.** `UserResource` is not in the plugin's default resource list. `SparkCommercePlugin::make()` defaults to `ProductResource`, `CategoryResource`, `ReviewResource`, `OrderResource`, `CouponResource` (`src/SparkCommercePlugin.php:39-45`), and that file is identical to `main` — `git diff main...HEAD -- src/SparkCommercePlugin.php` produces no output. A host app must opt in.

**Verdict:** false positive *for this review's scope*. The missing role allow-list and the unguarded `Impersonate` action are real hardening gaps — they should be filed as follow-up work. They are just not something this branch introduced.

### The two commands, side by side

```bash
# 1. BASELINE — what protection existed before? (run before writing an authz finding)
git grep -nE "authorize|Gate|policy|can[A-Z]" <base> -- <dir>

# 2. NOVELTY — is this exact risky construct actually new? (run when a version port is in the diff)
git show <base>:<exact/path/to/file.php> | grep -n "<the construct>"
```

If command 1 finds nothing, the branch cannot have removed a check.
If command 2 finds the same construct, the branch did not introduce it.

## Related

- `UPGRADE.md` section 3 ("Roles and the panel gate", lines 44-56) — the documented breaking change and the `sc:publish-roles` migration step. Line 57 ("existing admins are locked out") is the single cheapest refutation of candidate 1.
- `CHANGELOG.md:21` — the gate listed under **Added**, corroborating the empty baseline.
- `README.md:77` and `:84` — the two halves of the layering. Line 77 says every resource is protected by the package gate; line 84 says the host's `canAccessPanel()` is what lets a user into the panel at all. They compose; the package gate runs behind the host check, not in front of it.
- `src/SparkCommerceServiceProvider.php:186-204` — the `access-sparkcommerce-admin` gate, including the `sparkcommerce.panel_gate` override hook.
- `src/Filament/Resources/OrderResource.php:213` and `:239` — the same gate applied to a custom action's visibility and to its handler, showing that this branch adds authorization on the money path rather than removing it.
- Scope note: the single gate is the panel-level floor, not the package's permanent authorization model. Per-model policies are planned to sit on top of it, with the gate remaining as the floor. Read this doc as guidance about the review process, not as a claim that the gate is the final design.
- Follow-up hardening, both pre-existing on `main` and not tracked anywhere else today: add an allow-list to the `UserResource` role picker so an operator cannot grant a role above their own, and guard `Impersonate::make()` with an explicit ability check.
