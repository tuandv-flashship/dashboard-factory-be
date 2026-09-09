# Lessons

## Test payloads must match what the client actually sends

**Context.** The user update endpoint gained `role_ids` support. Every test
passed, but the deployed build returned 500 on the first real request with
`There is no role named '37' for guard 'api'`.

**What went wrong.** The tests built role ids with `$role->getHashedKey()`.
`HASH_ID=false` in every environment of this project, so that helper returns
the raw integer key, and the test payload carried `[2]`. The dashboard
serialises ids as strings, so the real payload carried `["2"]`. Spatie's
`getStoredRole()` branches on type: an int is looked up with `findById`, a
string with `findByName`. The string ids were therefore read as role *names*
and threw `RoleDoesNotExist`.

The bug was invisible because `CreateUserAction` and `SyncUserRolesAction`
declare `int ...$roleIds`, so PHP coerces the strings for free. A new action
declared `array|null $roleIds`, which does no coercion.

**Rule for next time.**

- When a test exercises an id, a flag or a numeric field that arrives over
  JSON, assert with the same *type* the client sends. Prefer copying a real
  request body from the browser or from a curl command over inventing one.
- Before relying on a test helper such as `getHashedKey()`, check what it
  returns under this project's config rather than under Apiato defaults.
- When replacing a variadic `int ...$args` contract with an array parameter,
  cast explicitly. The implicit coercion that made the old code work is gone.

## Sanitize reads raw input, so validation rules do not gate anything

**Context.** `UpdateUserRequest` guarded `status` with
`Rule::excludeIf(!$isAdmin)`, which looked like an admin-only field.

**What went wrong.** Apiato registers `sanitize()` as a macro over
`$this->all()`, not over `validated()`. Rules such as `Rule::excludeIf()` only
shape `validated()`, so any field a controller pulls through `sanitize()`
reaches the model regardless of the rule. A plain user could move their own
account from pending to active.

**Rule for next time.** Treat `sanitize()` output as unvalidated input.
Authorisation for individual fields belongs in the controller or the action as
an explicit `can()` check. A validation rule may document the intent, but it
never enforces it on a sanitized payload.

## A nullable column plus a hashed cast hides a destructive write

**Context.** Every profile update wrote `password = NULL`, locking users out
with `The user credentials were incorrect.`

**What went wrong.** `'password' => $request->new_password` was passed to
`sanitize()`. A string key in Apiato's `Sanitizer` means "key => default" and
is *always* written, so an absent `new_password` became `null`. The column is
nullable and Laravel's `hashed` cast returns `null` untouched, so the wipe
persisted with no error anywhere.

**Rule for next time.** Only add a field to an update payload when the request
actually carried it. For credentials and other unrecoverable values, add a
test that performs the *neighbouring* operation, updating a profile without
touching the password, and asserts the untouched value survived.
