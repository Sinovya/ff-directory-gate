# FF Directory Gate

Paid-access gate for **directory.freightforge.com**. Carriers list free; buyers pay.

Anonymous visitors get a **teaser** — counts, zone, equipment, authority status and a
*banded* power-unit range — with carrier names, USDOT/MC, addresses, contacts, logos and
profile links stripped from the HTML. Paying members get the real data.

**Status:** live and enforcing on production since 2026-07-31.

---

## The one thing to know

```
wp option update ff_dir_gate_mode enforce   # gate on
wp option update ff_dir_gate_mode off       # kill switch - instantly public again
```

`off` is the default, so the plugin can be deployed to a live site inert.

Two concepts are deliberately kept separate and must not be blurred:

| | Question | Affected by mode? |
|---|---|---|
| `ff_dir_user_can()` | is this user **entitled**? | **no** |
| `ff_dir_gate_enabled()` | are we **enforcing**? | yes |

Mode `off` must never *open* a door that was already closed — so anything that reveals
member-only content ignores the mode. Only the gates and the layout tags respect it.

---

## Access states

| State | When | Sees data | Message |
|---|---|---|---|
| `full` | within the paid period (or admin, or gate off) | yes | — |
| `grace` | past paid-through, inside the grace window | **yes** | "Payment overdue — access continues for *N* more days" |
| `expired` | grace exhausted, under a year | no | "Your access ended on *date*" → **Renew access** |
| `none` | never bought, or lapsed over a year | no | "…member-only" → **Request access** |

Hard cutoff = `paid_through + grace_days`. Grace counts as **not** expired — that is what
preserves access during it.

### Billing fields (per user)

`ff_dir_paid_through` · `ff_dir_grace_days` · `ff_dir_billing_cycle`

The cycle is bookkeeping only — the gate never reads it. It says what to advance
`paid_through` to on the next payment. Legacy `ff_dir_access_expires` is still honoured.

Edit in **Users → Edit User → "Directory access"**, or via WP-CLI:

```
wp ff-dir grant <user> --features=search,contacts,export --paid-through=2026-09-01 --grace=14 --cycle=monthly
wp ff-dir check  <user>
wp ff-dir revoke <user>     # true offboarding only - makes them a STRANGER, not a lapsed customer
```

> **Do not `revoke` a non-paying customer.** It deletes their entitlement, so they get
> "Request access" instead of "Renew access" and you lose the signal they ever bought.
> To lapse someone, set an earlier `--paid-through`.

---

## What's in here

### `ff-directory-gate.php`
The gate. Role, entitlement function, the four states, all gated surfaces (archive teaser,
carrier/lane singles, WP REST, feeds, identity facets, permalinks), shortcodes and Bricks
dynamic tags.

### `mu-companions/`
Separate mu-plugins that shipped alongside the gate. Two are general email fixes, not gate
code — kept here because they were built together.

| File | Why it exists |
|---|---|
| `ff-legacy-auth-redirects.php` | Front door: `/` → `/carriers/`. Also 301s the superseded `/login/`, `/lost-password/`, `/password-reset/` to the current `car-dir-*` pages, preserving query strings (reset links carry `?key=`). Host-guarded to the Directory. |
| `ff-bricksforge-ajax-guard.php` | Bricksforge registers its email-designer test-mail AJAX action for `wp_ajax_nopriv_` with **no nonce and no capability check**, taking the recipient from `$_POST`. Requires an authenticated admin instead. |
| `ff-email-from-header-fix.php` | Bricksforge builds `From: Name<addr>` with no space, so parsers strip a character and the brand name arrives truncated ("FreightForg"). Repairs the header. |

### `tools/`
One-off maintenance and verification scripts, run with `wp eval-file`. Most take a `dry`
argument and back up to a postmeta key before writing. They are **not** loaded by WordPress.

Notable: `test-billing-states.php` (exercises all four states with throwaway users),
`test-reset-submit.php` (proves the password reset actually changes the password),
`make-demo-carrier.php` (invented carrier for screenshots — refuses to run outside sandbox).

---

## Gotchas that cost real time

- **Production runs opcache** (`revalidate_freq=60`). A PHP change takes up to 60s to take
  effect — testing inside that window shows the *old* behaviour. `freightforge.com` has
  opcache **off**; the two servers differ.
- **Never write a PHP file straight into `mu-plugins/`.** A parse error there 500s the whole
  site. Write to `/tmp`, `php -l`, then copy.
- **`update_post_meta()` is blocked on `_bricks_page_content_2`** by Bricks' meta guard. Use
  `$wpdb->update()` + `clean_post_cache()`.
- **Bricks header templates use `_bricks_page_header_2`**, not `_bricks_page_content_2`.
- **A 302 has a 0-byte body.** `bytes=0` is not evidence of breakage — read the status code.
- **`wp eval` + `Bricks\Frontend::render_data()` is not a valid harness for query-loop
  content** — the loop re-reads elements from Bricks' own store, so a new element renders as
  absent. Verify loop templates over real HTTP.
- **Mint auth cookies with a single `wp eval` into a file.** Doing it inside a shell loop over
  SSH silently produces malformed cookies and every state then looks anonymous.

---

## Not done yet

- No **billing integration** — dates are moved by hand. `dlrh_ff_billing_accounts` and
  `dlrh_ff_billing_account_users` already model companies and seats (5 plans defined, 0
  accounts). Hook it via the `ff_dir_user_can` filter; no gate code changes needed.
- No **email reminder** during grace (needs a scheduled task; FluentCRM is the intended home).
- `ff_contact_access` (the "Contact This Carrier" box) is a **separate entitlement store** that
  the `contacts` door does not drive.
