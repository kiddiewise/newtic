#!/usr/bin/env python3
"""Offline engineering-only XSD check. Requires Python lxml and an operator-
verified local GIB schema package. Never claims Schematron or fiscal validity.
Use only with trusted schema assets; invoice contents are not printed.
"""
import argparse
import json
import sys
from pathlib import Path
from urllib.parse import unquote, urlparse

from lxml import etree


class LocalSchemaResolver(etree.Resolver):
    def __init__(self, root):
        self.root = root

    def resolve(self, url, public_id, context):
        parts = urlparse(url)
        if parts.scheme not in ("", "file") or parts.netloc:
            raise OSError("External schema resolution blocked")
        path = Path(unquote(parts.path))
        if not path.is_absolute():
            path = self.root / path
        path = path.resolve(strict=True)
        if not path.is_relative_to(self.root) or path.suffix.lower() != ".xsd":
            raise OSError("Schema dependency outside approved package")
        return self.resolve_filename(str(path), context)


def run():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--package", required=True, type=Path)
    parser.add_argument("--xsd", required=True, type=Path, help="Invoice schema relative to package")
    parser.add_argument("--xml", required=True, type=Path)
    args = parser.parse_args()
    result = {"xsd": "not_checked", "schematron": "not_checked", "submission_allowed": False}
    try:
        root = args.package.resolve(strict=True)
        schema_path = (root / args.xsd).resolve(strict=True)
        if not schema_path.is_relative_to(root) or schema_path.suffix.lower() != ".xsd":
            raise ValueError("Invalid schema path")
        schema_parser = etree.XMLParser(resolve_entities=False, load_dtd=False, no_network=True)
        schema_parser.resolvers.add(LocalSchemaResolver(root))
        schema_doc = etree.parse(str(schema_path), schema_parser)
        if schema_doc.docinfo.doctype:
            raise ValueError("DTD not permitted")
        schema = etree.XMLSchema(schema_doc)
        if args.xml.stat().st_size > 16 * 1024 * 1024:
            raise ValueError("XML too large")
        xml = etree.parse(str(args.xml), etree.XMLParser(resolve_entities=False, load_dtd=False, no_network=True))
        if xml.docinfo.doctype:
            raise ValueError("DTD not permitted")
        if xml.getroot().tag != "{urn:oasis:names:specification:ubl:schema:xsd:Invoice-2}Invoice":
            raise ValueError("Not a UBL Invoice")
        if schema.validate(xml):
            result["xsd"] = "passed"
        else:
            result["xsd"] = "failed"
            result["errors"] = [{"line": e.line, "type": e.type_name} for e in list(schema.error_log)[:30]]
    except (OSError, ValueError, etree.LxmlError):
        result["xsd"] = "unavailable_or_invalid"
    print(json.dumps(result))
    # A successful XSD check is not submission approval.
    return 0 if result["xsd"] == "passed" else 1


if __name__ == "__main__":
    sys.exit(run())
