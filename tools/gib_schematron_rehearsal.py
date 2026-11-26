"""CI-only official GIB Schematron rehearsal, synthetic unsigned documents only."""
import hashlib
import json
import pathlib
import re
import sys
import zipfile
from lxml import etree, isoschematron

PIN = "12901476cd424fd65c779a4fb44cee798f9921ce55b003f8a076c936ddf8b583"
SCH = "http://purl.oclc.org/dsdl/schematron"
SVRL = "http://purl.oclc.org/dsdl/svrl"
CBC = "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
PREFIX = "e-FaturaPaketi/schematron/"
FILES = ["UBL-TR_Main_Schematron.xml", "UBL-TR_Common_Schematron.xml", "UBL-TR_Codelist.xml"]

def parser():
    return etree.XMLParser(resolve_entities=False, no_network=True, load_dtd=False, huge_tree=False)

def parse(path):
    tree = etree.parse(str(path), parser())
    if tree.docinfo.doctype:
        raise ValueError("DTD forbidden")
    return tree

def summarize(report):
    if report is None or report.getroot().tag != "{%s}schematron-output" % SVRL:
        raise ValueError("Unexpected SVRL")
    counts = {name: len(report.xpath("//s:" + name, namespaces={"s": SVRL}))
              for name in ("active-pattern", "fired-rule", "failed-assert", "successful-report")}
    counts["no_findings"] = counts["active-pattern"] > 0 and counts["fired-rule"] > 0 and not (counts["failed-assert"] or counts["successful-report"])
    # Do not print raw assertions, invoice values or personal data.
    return counts

def main(zip_path, candidates, work):
    with open(zip_path, "rb") as stream:
        if hashlib.file_digest(stream, "sha256").hexdigest() != PIN:
            raise ValueError("Official package differs from reviewed inventory")
    work.mkdir(mode=0o700, parents=True, exist_ok=False)
    with zipfile.ZipFile(zip_path) as archive:
        for name in FILES:
            item = archive.getinfo(PREFIX + name)
            if item.file_size > 16 * 1024 * 1024:
                raise ValueError("Rule file too large")
            (work / name).write_bytes(archive.read(item))
    main_rules = parse(work / FILES[0])
    common = parse(work / FILES[1])
    parse(work / FILES[2])
    root = main_rules.getroot()
    binding = root.get("queryBinding", "xslt")
    print(json.dumps({"schema_namespace": etree.QName(root).namespace, "query_binding": binding,
                      "rule_files": {name: hashlib.sha256((work / name).read_bytes()).hexdigest() for name in FILES}}), flush=True)
    if root.tag != "{%s}schema" % SCH or binding not in ("xslt", "xslt1", "xpath"):
        raise ValueError("Unsupported official query binding; do not downgrade")
    # Only these pinned rule files may be included. Do not fetch remote rules.
    for tree in (main_rules, common):
        for href in tree.xpath("//s:include/@href", namespaces={"s": SCH}):
            file = href.split("#", 1)[0]
            if file and file not in FILES:
                raise ValueError("Unexpected rule include")
    compiler = isoschematron.Schematron(main_rules, store_xslt=True, validate_schema=False)
    # This is a read-only CI runner with synthetic data. Block network and
    # all writes by the resulting stylesheet; permit the pinned local codelist.
    transform = etree.XSLT(compiler.validator_xslt, access_control=etree.XSLTAccessControl(
        read_file=True, read_network=False, write_file=False, create_dir=False, write_network=False))
    results = {}
    for profile in ("TEMELFATURA", "TICARIFATURA"):
        document = parse(candidates / (profile + ".xml"))
        report = transform(document)
        results[profile] = summarize(report)
        nodes = document.xpath("/i:Invoice/cbc:ProfileID", namespaces={
            "i":"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2","cbc":CBC})
        if len(nodes) != 1:
            raise ValueError("Fixture profile missing")
        nodes[0].text = "NEWTIC_INVALID_PROFILE_CONTROL"
        bad = summarize(transform(document))
        results[profile + "_invalid_control"] = bad
        print(json.dumps({"profile": profile, "candidate": results[profile], "negative_control": bad}), flush=True)
        if bad["failed-assert"] <= results[profile]["failed-assert"]:
            raise ValueError("Negative control did not demonstrate rule coverage")
    # A candidate can legitimately fail (unsigned/placeholder). Do not claim
    # fiscal readiness, and keep observations distinct from validation success.
    print(json.dumps({"official_rules_executed": True, "candidate_results": results,
                      "submission_allowed": False}), flush=True)

if __name__ == "__main__":
    if len(sys.argv) != 4:
        raise SystemExit("Usage: gib_schematron_rehearsal.py ZIP CANDIDATES WORK")
    main(*(pathlib.Path(p).resolve() for p in sys.argv[1:]))
