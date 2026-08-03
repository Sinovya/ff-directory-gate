#!/usr/bin/env python3
"""
Point ff-carrier-contact at the Directory gate's `contacts` door.

Before this, "Contact This Carrier" had its OWN entitlement store - the
ff_contact_access usermeta - which the gate knew nothing about. A customer
granted the contacts door still saw "0 emails remaining", and there was no
admin bypass, so it read 0 for everyone including administrators.

Delegation is deliberately CONDITIONAL on the gate enforcing, exactly like the
CSV export patch: with the gate off, the plugin's own logic is untouched, so
switching the gate off can never open or close this door unexpectedly.

Usage: python3 patch-carrier-contact-entitlement.py <path-to-ff-carrier-contact.php>
"""

import io
import sys

if len(sys.argv) < 2:
    print("usage: patch-carrier-contact-entitlement.py <file>")
    raise SystemExit(1)

path = sys.argv[1]
src = io.open(path, encoding="utf-8").read()

if "ff_dir_gate_enabled" in src:
    print("ALREADY PATCHED")
    raise SystemExit(0)

# --- 1. the main gate -------------------------------------------------------
anchor1 = "function ff_contact_user_can_email() {"
add1 = anchor1 + """

    // Directory gate: once it is ENFORCING, ff_dir_user_can() is the single
    // source of truth for the contacts door. While the gate is off the
    // plugin's own logic below is untouched.
    if (function_exists('ff_dir_gate_enabled') && ff_dir_gate_enabled()) {
        return ff_dir_user_can('contacts');
    }
"""

if anchor1 not in src:
    print("ANCHOR 1 NOT FOUND")
    raise SystemExit(1)

src = src.replace(anchor1, add1, 1)

# --- 2. the credit counter --------------------------------------------------
anchor2 = "function ff_contact_get_remaining_credits() {"
add2 = anchor2 + """

    // Same source of truth. A customer holding the contacts door is not
    // metered by this plugin's monthly credits.
    if (function_exists('ff_dir_gate_enabled') && ff_dir_gate_enabled()) {
        return ff_dir_user_can('contacts') ? 'unlimited' : 0;
    }
"""

if anchor2 not in src:
    print("ANCHOR 2 NOT FOUND")
    raise SystemExit(1)

src = src.replace(anchor2, add2, 1)

io.open(path, "w", encoding="utf-8", newline="").write(src)
print("PATCHED")
