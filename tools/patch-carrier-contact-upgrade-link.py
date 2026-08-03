#!/usr/bin/env python3
"""
Fix the dead "Upgrade Now" link in ff-carrier-contact.

The premium prompt links to /pricing/, which returns 404 on the Directory -
so a customer without the contacts door is shown an upgrade path that goes
nowhere. Point it at the gate's state-aware destination instead, which sends a
lapsed customer to renewal and a stranger to the sales page.

Usage: python3 patch-carrier-contact-upgrade-link.py <path-to-ff-carrier-contact.php>
"""

import io
import sys

if len(sys.argv) < 2:
    print("usage: patch-carrier-contact-upgrade-link.py <file>")
    raise SystemExit(1)

path = sys.argv[1]
src = io.open(path, encoding="utf-8").read()

if "ff_contact_upgrade_url" in src:
    print("ALREADY PATCHED")
    raise SystemExit(0)

# 1. helper that prefers the gate's destination, with a sane fallback
helper = """
/**
 * Where to send someone who cannot email carriers yet.
 *
 * /pricing/ does not exist on the Directory, so the old hardcoded link was a
 * 404. The Directory gate already works out the right destination per visitor
 * - renewal for a lapsed customer, the sales page for a stranger - so use that
 * when it is available.
 */
function ff_contact_upgrade_url() {

    if (function_exists('ff_dir_access_state') && function_exists('ff_dir_renew_url')) {
        return 'expired' === ff_dir_access_state()
            ? ff_dir_renew_url()
            : ff_dir_redirect_url(array('from' => 'contact'));
    }

    if (function_exists('ff_dir_redirect_url')) {
        return ff_dir_redirect_url(array('from' => 'contact'));
    }

    return 'https://freightforge.com/directory/';
}

function ff_contact_user_can_email() {"""

anchor = "function ff_contact_user_can_email() {"

if anchor not in src:
    print("ANCHOR NOT FOUND")
    raise SystemExit(1)

src = src.replace(anchor, helper, 1)

# 2. the dead link itself
old_link = "'<a href=\"/pricing/\" class=\"button\""
new_link = "'<a href=\"<?php echo esc_url(ff_contact_upgrade_url()); ?>\" class=\"button\""

if old_link not in src:
    print("LINK ANCHOR NOT FOUND")
    raise SystemExit(1)

src = src.replace(old_link, new_link, 1)

io.open(path, "w", encoding="utf-8", newline="").write(src)
print("PATCHED")
