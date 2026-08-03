# Shortcodes & Bricks tags

Everything `ff-directory-gate` exposes for placing and conditioning content in Bricks.
Almost all of it keys off which of four **access states** the visitor is in.

Readable version: https://claude.ai/code/artifact/3d4cf735-7601-4d9a-bbbd-3fdaa1272174

---

## The four access states

Returned by `{ff_dir_access_state}`. Hard cutoff = **paid-through + grace days**; grace
deliberately does *not* count as expired, which is what keeps access on during it.

| State | When | Sees data | Message |
|---|---|---|---|
| `full` | within the paid period; also any admin, and everyone when the gate is off | yes | — |
| `grace` | past paid-through, inside that customer's grace window | **yes** | "Payment overdue — access continues for *N* more days" |
| `expired` | grace exhausted, lapsed under a year | no | "Your access ended on…" → **Renew access** |
| `none` | never bought, or lapsed over a year | no | "…member-only" → **Request access** |

---

## Shortcodes

Use a shortcode where the value must be computed per request — a logout link carries a nonce,
a grace countdown changes daily. Anything static is better as a Bricks element with a condition.

### `[ff_dir_account_bar]`
Sign-in / signed-in-as + log out, for the site header.

- Guests: `Sign in` link to the Bricks login page.
- Signed in: "Signed in as **Name**" + `Log out`. A lapsed customer also gets `(access expired)`.
- Attributes: `signed_in_label` ("Signed in as"), `logout_label` ("Log out"), `login_label` ("Sign in").
- **Must** be a shortcode — the logout URL carries a per-request nonce.
- Returns nothing on the login / lost-password / reset pages, where naming the session user
  would contradict the form.

### `[ff_dir_account_notice]`
Page-level banner for a customer in `grace` or `expired`. Already placed above the carrier results.

- Grace: "Your payment is overdue. Your Directory access continues for **12** more days (until 14 August 2026)."
- Lapsed: "Your Directory access ended on…"
- Attributes: `link` ("Contact us to renew").
- `[ff_dir_expired_notice]` is an older name for the same thing and still works.

### `[ff_dir_locked]`
The per-card "member-only" line. State-aware: strangers get **Request access**, lapsed customers
get **Renew access** — never asked to request something they already bought.

- Attributes: `feature` (search), `text`, `link`, `expired_text`, `expired_link`.
- ⚠️ The production carrier card does **not** use this — it is Bricks elements driven by
  `{ff_dir_locked_message}` / `{ff_dir_cta_label}` so it can be styled. Use this for one-off placements.

### `[ff_dir_gate feature="…"]…[/ff_dir_gate]`
Shows the wrapped content only to someone entitled to that door (`search`, `contacts`, `export`).
Checks **entitlement**, so it ignores the master switch — turning the gate off can never reveal
content this protects.

### `[ff_dir_member_only]` / `[ff_dir_guest_only]`
Simple either/or wrappers, no per-door check. If you need to distinguish lapsed from never-bought,
condition on `{ff_dir_access_state}` instead.

### `[ff_dir_units]`
Fleet size — exact for members (`18`), banded for everyone else (`11-25`).
Bands: 1-10, 11-25, 26-50, 51-100, 101-250, 251-500, 501+.
Attributes: `id` (0 = current carrier), `suffix`.

> An exact fleet size plus a zone plus an equipment type starts to identify a small carrier —
> which is why non-members get a band, not a number.

---

## Dynamic tags

In the Bricks picker under **FreightForge Directory Gate**.

| Tag | Returns | Use it for |
|---|---|---|
| `{ff_dir_access_state}` | full \| grace \| expired \| none | One condition to drive a whole block. Prefer this. |
| `{ff_dir_can_search}` | 1 or empty | The member/teaser layout switch. |
| `{ff_dir_is_member}` | 1 or empty | Member-only blocks where lapsed vs never-bought doesn't matter. |
| `{ff_dir_is_grace}` | 1 or empty | Warn only someone with an overdue payment. |
| `{ff_dir_is_expired}` | 1 or empty | Renewal messaging for a lapsed customer. |
| `{ff_dir_grace_days_left}` | e.g. `12` | "You have 12 days left". Empty when not in grace. |
| `{ff_dir_expiry_date}` | e.g. 14 August 2026 | When access actually ends — grace included. |
| `{ff_dir_units}` | 18 or 11-25 | Fleet size in a loop. |
| `{ff_dir_locked_message}` | state-aware sentence | Card copy correct for stranger and lapsed alike. |
| `{ff_dir_cta_label}` | Request access \| Renew access | Teaser button text. |
| `{ff_dir_cta_url}` | URL | Teaser button destination, matched to the label. |
| `{ff_dir_request_access_url}` | URL | Always the sales page. |
| `{ff_dir_renew_url}` | URL | Always the renewal route. |

---

## Two recipes

**Condition a block on one state**

```
Condition → Dynamic data
  {ff_dir_access_state}   equals   grace
```

Two condition *groups* act as OR, so a block can show for `grace` **or** `expired` — that is how
the notice above the carrier results is set up.

**One card, right copy for everyone**

```
Text element   → {ff_dir_locked_message}
Button label   → {ff_dir_cta_label}
Button link    → {ff_dir_cta_url}
```

Keeps one set of styled elements instead of duplicate blocks with opposing conditions.

---

## Two things that catch people out

- **Bricks writes a dynamic tag straight into an `href`.** A link field takes `{ff_dir_cta_url}`
  with nothing around it — and a stray trailing space ends up inside the URL.
- **The master switch only affects the gates and the layout tags.** The entitlement shortcodes
  ignore it, so `ff_dir_gate_mode=off` never opens a door that was already shut.
