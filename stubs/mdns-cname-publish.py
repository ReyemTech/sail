#!/usr/bin/env python3
"""Publish an mDNS CNAME: <alias> -> this host's avahi FQDN (e.g. myproj.local
-> creed.local), so <project>.local resolves to the host's LAN IP via the shared
proxy. A CNAME (not an A record) is used deliberately: avahi-publish -a owns the
reverse PTR 1:1, so it cannot map many projects onto one shared IP and collides on
the host's own address. CNAMEs have no PTR, so every project can alias one host.
The target is self-discovered via Avahi's Server.GetHostNameFqdn(), so no hostname
needs to be plumbed in.

The publisher stays resident and RE-REGISTERS whenever avahi-daemon restarts: a
daemon restart (e.g. when Sail applies the `allow-interfaces` host fix) tears down
every client's entry group, so we watch the bus's own NameOwnerChanged for
org.freedesktop.Avahi and re-publish each time the daemon reappears. Requires the
host avahi-daemon reachable over /run/dbus."""
import sys

import dbus
from dbus.mainloop.glib import DBusGMainLoop
from gi.repository import GLib

alias = sys.argv[1]


def encode_name(name):
    out = b""
    for label in name.split("."):
        out += bytes([len(label)]) + label.encode()
    return out + b"\x00"


IN_CLASS, CNAME_TYPE, TTL = 0x01, 0x05, 60

DBusGMainLoop(set_as_default=True)
bus = dbus.SystemBus()


def publish():
    server = dbus.Interface(
        bus.get_object("org.freedesktop.Avahi", "/"), "org.freedesktop.Avahi.Server"
    )
    target = str(server.GetHostNameFqdn())
    group = dbus.Interface(
        bus.get_object("org.freedesktop.Avahi", server.EntryGroupNew()),
        "org.freedesktop.Avahi.EntryGroup",
    )
    group.AddRecord(
        -1, -1, dbus.UInt32(0), alias, IN_CLASS, CNAME_TYPE, TTL,
        dbus.ByteArray(encode_name(target)),
    )
    group.Commit()
    print(f"Published CNAME {alias} -> {target}", flush=True)


def publish_with_retry():
    try:
        publish()
        return False  # success: stop any retry timer
    except dbus.DBusException:
        return True   # avahi not ready yet: retry on the next tick


def on_owner_changed(name, old, new):
    # avahi-daemon (re)appeared on the bus — the previous entry group died with the
    # old instance, so re-register. Fires on every daemon restart.
    if new and publish_with_retry():
        GLib.timeout_add_seconds(2, publish_with_retry)


bus.add_signal_receiver(
    on_owner_changed,
    dbus_interface="org.freedesktop.DBus",
    signal_name="NameOwnerChanged",
    arg0="org.freedesktop.Avahi",
)

if publish_with_retry():
    GLib.timeout_add_seconds(2, publish_with_retry)

GLib.MainLoop().run()
