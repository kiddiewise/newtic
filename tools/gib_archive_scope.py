"""Inspect the pinned GIB archive report schema and stylesheet without executing."""
import hashlib
import json
import pathlib
import sys
import zipfile
import xml.etree.ElementTree as ET

PIN = "36abf382079d857f96450f81aa294a863e919d3bb7f58d41f3c41cf75151a8f8"
XSD = "http://www.w3.org/2001/XMLSchema"
XSL = "http://www.w3.org/1999/XSL/Transform"

def parse_member(archive, filename):
    matches = [entry for entry in archive.infolist() if pathlib.PurePosixPath(entry.filename).name == filename]
    if len(matches) != 1 or matches[0].file_size > 8 * 1024 * 1024:
        raise ValueError("Ambiguous or oversized pinned package member")
    data = archive.read(matches[0])
    if b"<!DOCTYPE" in data.upper() or b"<!ENTITY" in data.upper():
        raise ValueError("Unexpected document type")
    return ET.fromstring(data)

def inspect(path):
    with open(path, "rb") as stream:
        if hashlib.file_digest(stream, "sha256").hexdigest() != PIN:
            raise ValueError("Archive package differs from reviewed digest")
    with zipfile.ZipFile(path) as archive:
        schema = parse_member(archive, "eArsiv.xsd")
        rules = parse_member(archive, "earsiv_schematron.xsl")
        if schema.tag != "{%s}schema" % XSD or rules.tag not in ("{%s}stylesheet" % XSL, "{%s}transform" % XSL):
            raise ValueError("Unexpected archive rule file type")
        roots = [node.get("name") for node in schema.findall("{%s}element" % XSD)]
        matches = [node.get("match", "") for node in rules.iter("{%s}template" % XSL)]
        report_rules = [item for item in matches if "earsivraporu" in item.lower()]
        # Classify scope from actual schema roots, not a file name alone.
        result = {"schema_target_namespace":schema.get("targetNamespace"), "schema_global_elements":roots,
                  "stylesheet_version":rules.get("version"), "template_count":len(matches),
                  "report_root_templates":len(report_rules),
                  "report_schema_confirmed":"eArsivRaporu" in roots,
                  "ubl_invoice_validation_confirmed":False, "rules_executed":False}
        print(json.dumps(result, ensure_ascii=True), flush=True)
        if not result["report_schema_confirmed"]:
            raise ValueError("Expected GIB report root not found; manual review needed")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: gib_archive_scope.py ZIP")
    inspect(sys.argv[1])
