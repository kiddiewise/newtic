"""Inventory a trusted-source GIB ZIP without extraction or execution."""
import collections
import hashlib
import json
import pathlib
import re
import sys
import zipfile

def inspect(path):
    with open(path, "rb") as stream:
        digest = hashlib.file_digest(stream, "sha256").hexdigest()
    counts = collections.Counter()
    candidates = []
    with zipfile.ZipFile(path) as archive:
        entries = archive.infolist()
        if len(entries) > 10000 or sum(entry.file_size for entry in entries) > 512 * 1024 * 1024:
            raise ValueError("Unexpected ZIP inventory size")
        for item in entries:
            if item.is_dir():
                continue
            name = item.filename.replace("\\", "/")
            if name.startswith("/") or ".." in pathlib.PurePosixPath(name).parts or re.match(r"^[A-Za-z]:", name):
                raise ValueError("Unexpected archive path")
            suffix = pathlib.PurePosixPath(name).suffix.lower()
            counts[suffix] += 1
            if suffix in (".sch", ".xsl", ".xslt", ".xsd") or "schematron" in name.lower():
                row = {"path": name, "bytes": item.file_size}
                if item.file_size <= 8 * 1024 * 1024:
                    content = archive.read(item)
                    row["sha256"] = hashlib.sha256(content).hexdigest()
                    match = re.search(rb'queryBinding\s*=\s*["\x27]([A-Za-z0-9_-]{1,32})["\x27]', content[:65536])
                    if match:
                        row["query_binding"] = match.group(1).decode("ascii")
                    match = re.search(rb'<(?:\w+:)?(?:stylesheet|transform)\b[^>]{0,4096}\bversion\s*=\s*["\x27]([0-9.]{1,8})["\x27]', content[:65536])
                    if match:
                        row["xslt_version"] = match.group(1).decode("ascii")
                candidates.append(row)
    return {"zip_sha256": digest, "counts": dict(counts), "rule_candidates": candidates,
            "official_validation_confirmed": False}

if __name__ == "__main__":
    if len(sys.argv) != 3 or sys.argv[2] not in ("invoice", "archive"):
        raise SystemExit("Usage: gib_rule_inventory.py ZIP invoice|archive")
    print(json.dumps({"package": sys.argv[2], **inspect(sys.argv[1])}, ensure_ascii=True, indent=2))
