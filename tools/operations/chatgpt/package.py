"""Build the operator-owned ChatGPT package; never provision or publish a service."""

import argparse
import hashlib
import io
import ipaddress
import json
from pathlib import Path
from urllib.parse import urlsplit
import zipfile


def build_package(server_url):
    url = urlsplit(server_url)
    if (url.scheme != "https" or not url.hostname or url.username or url.password
            or url.path != "/mcp" or url.query or url.fragment or url.port not in (None, 443)
            or any(c.isspace() or ord(c) < 32 for c in server_url)):
        raise ValueError("Use the approved public HTTPS endpoint ending in /mcp.")
    host = url.hostname.lower()
    if "." not in host or host.endswith((".localhost", ".local", ".invalid", ".test", ".example")):
        raise ValueError("A public service hostname is required.")
    try:
        ipaddress.ip_address(host)
    except ValueError:
        pass
    else:
        raise ValueError("Use the approved service hostname, not an IP address.")
    manifest = json.loads(Path(__file__).with_name("plugin.json").read_text())
    mcp = {"$schema": "https://agent-plugins.org/schemas/1.0.0/mcp.schema.json",
           "mcpServers": {"mrn-operations": {"type": "streamable-http", "url": server_url}}}
    result = io.BytesIO()
    with zipfile.ZipFile(result, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        # An explicit file list excludes operator configuration, secrets, local
        # paths, executables, dependencies, databases and unrelated repo files.
        for name, content in (("plugin.json", manifest), ("mcp.json", mcp)):
            info = zipfile.ZipInfo(name, (2020, 1, 1, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, json.dumps(content, indent=2, sort_keys=True) + "\n")
    return result.getvalue()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--server-url", required=True)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    try:
        package = build_package(args.server_url)
        with args.output.open("xb") as file:
            file.write(package)
    except (ValueError, OSError):
        parser.exit(1, "Package not written. Check the approved HTTPS endpoint and a new output file path.\n")
    print(json.dumps({"packageCreated": True, "sha256": hashlib.sha256(package).hexdigest(),
                      "hostingVerified": False, "chatgptAcceptanceVerified": False}))


if __name__ == "__main__":
    main()
