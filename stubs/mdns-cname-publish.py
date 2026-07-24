#!/usr/bin/env python3
"""Publish an mDNS CNAME: <alias> -> this host's avahi FQDN (e.g. myproj.local
-> creed.local), so <project>.local resolves to the host's LAN IP via the shared
proxy. A CNAME (not an A record) is used deliberately: avahi-publish -a owns the
reverse PTR 1:1, so it cannot map many projects onto one shared IP and collides on
the host's own address. CNAMEs have no PTR, so every project can alias one host.
The target is self-discovered via Avahi's Server.GetHostNameFqdn(), so no hostname
needs to be plumbed in. Requires the host avahi-daemon reachable over /run/dbus."""
import sys
import time

import dbus

alias = sys.argv[1]


def encode_name(name):
    out = b""
    for label in name.split("."):
        out += bytes([len(label)]) + label.encode()
    return out + b"\x00"


IN_CLASS, CNAME_TYPE, TTL = 0x01, 0x05, 60

bus = dbus.SystemBus()
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

while True:
    time.sleep(3600)
