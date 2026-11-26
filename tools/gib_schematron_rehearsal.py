"""CI-only official GIB Schematron rehearsal, synthetic unsigned documents only."""
import hashlib
import json
import pathlib
import re
import sys
import subprocess
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
    # Synthetic documents only. Safe fingerprints aid comparison without
    # printing assertion messages, XPath locations or embedded customer data.
    counts["finding_fingerprints"] = [
        hashlib.sha256((node.get("test", "") + "|" + node.get("id", "")).encode("utf-8")).hexdigest()[:16]
        for node in report.xpath("//s:failed-assert|//s:successful-report", namespaces={"s": SVRL})
    ][:100]
    return counts


def preserve_rule_namespaces(generated, *rule_trees):
    """Carry source-declared XPath prefixes into the generated stylesheet.

    The ISO XSLT1 skeleton drops xs, which only appears in XPath expressions.
    Do not edit rule text, XPath tests, template bodies or stylesheet version.
    """
    old = generated.getroot()
    namespaces = dict(old.nsmap)
    # sch:ns defines the XPath static context. XML namespace declarations
    # on the schema document itself are a different context (e.g. ef).
    # Import only explicit XPath declarations; never override a conflicting
    # generated binding or silently guess an undeclared prefix.
    declared = {}
    for tree in rule_trees:
        for ns in tree.xpath("//s:ns", namespaces={"s": SCH}):
            prefix, uri = ns.get("prefix"), ns.get("uri")
            if not prefix or not uri:
                raise ValueError("Invalid official XPath namespace declaration")
            if prefix in declared and declared[prefix] != uri:
                raise ValueError("Conflicting official XPath namespace: " + prefix)
            declared[prefix] = uri
    for prefix, uri in declared.items():
        if prefix in namespaces and namespaces[prefix] != uri:
            raise ValueError("Conflicting generated XPath namespace: " + prefix)
        namespaces[prefix] = uri
    # The official rules use xs:date() and castable as xs:date. Check
    # against the XML Schema namespace, never guess a conflicting URI.
    if namespaces.get("xs") != "http://www.w3.org/2001/XMLSchema":
        raise ValueError("Official XML Schema namespace missing")
    new = etree.Element(old.tag, nsmap=namespaces)
    for key, value in old.attrib.items():
        new.set(key, value)
    new.text, new.tail = old.text, old.tail
    for child in list(old):
        new.append(child)
    return new

def finding_keys(report):
    return {(node.get("test", ""), node.get("id", ""))
            for node in report.xpath("//s:failed-assert", namespaces={"s": SVRL})}

def public_rule_hints(report):
    """Fixed categories from official assertion expressions; no values/messages."""
    categories = {
        "signature": ("signature", "xades", "digestvalue"),
        "identity": ("partyidentification", "schemeid", "vkn", "tckn"),
        "profile": ("profileid", "invoicetypecode"),
        "currency": ("currency",),
        "tax": ("tax", "percent"),
        "date": ("issuedate", "issuetime"),
        "unit": ("unitcode", "invoicedquantity"),
        "address": ("postaladdress", "country", "cityname"),
        "number": ("cbc:id", "uuid"),
    }
    hints = []
    for test, rule_id in sorted(finding_keys(report)):
        lower = test.lower()
        category = next((name for name, terms in categories.items()
                         if any(term in lower for term in terms)), "other")
        hints.append({"fingerprint": hashlib.sha256((test + "|" + rule_id).encode()).hexdigest()[:16],
                      "category": category})
    return hints[:100]

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
    # The official schema declares default xslt binding but includes XPath
    # 2.0 path expressions. lxml's constructor compiles the generated
    # stylesheet with libxslt and fails. Separate the standard ISO skeleton
    # compilation stages from execution. Keep generated version=1.0 so Saxon
    # uses XPath 1.0 compatibility semantics where required, with support for
    # newer expression syntax. Do not rewrite or remove official assertions.
    included = isoschematron.iso_dsdl_include(main_rules)
    expanded = isoschematron.iso_abstract_expand(included)
    generated = isoschematron.iso_svrl_for_xslt1(expanded)
    stylesheet = work / "validator.xsl"
    fixed_root = preserve_rule_namespaces(generated, expanded)
    stylesheet.write_bytes(etree.tostring(fixed_root, encoding="UTF-8", xml_declaration=True))
    restored = parse(stylesheet).getroot()
    if restored.nsmap.get("xs") != "http://www.w3.org/2001/XMLSchema":
        raise ValueError("Generated XPath namespace was not serialized")
    print(json.dumps({"namespace_check": "ok", "xs": restored.nsmap["xs"]}), flush=True)
    jar = pathlib.Path("/usr/share/java/Saxon-HE.jar")
    if not jar.is_file():
        raise ValueError("Saxon-HE distribution JAR missing")
    print(json.dumps({"processor": "Saxon-HE", "stylesheet_version": generated.getroot().get("version"),
                      "compiled_sha256": hashlib.sha256(stylesheet.read_bytes()).hexdigest()}), flush=True)

    def transform(document, label):
        # No customer data: all inputs are synthetic. Network namespace is
        # disabled by the CI shell for the transform stage. The local pinned
        # codelist resolves relative to the generated stylesheet.
        input_path = work / (label + ".xml")
        output_path = work / (label + ".svrl")
        input_path.write_bytes(etree.tostring(document, encoding="UTF-8", xml_declaration=True))
        process = subprocess.run(
            ["java", "-Djavax.xml.accessExternalDTD=", "-Djavax.xml.accessExternalSchema=",
             "-cp", str(jar), "net.sf.saxon.Transform", "-ext:off", "-dtd:off",
             "-s:" + str(input_path), "-xsl:" + str(stylesheet), "-o:" + str(output_path)],
            capture_output=True, text=True, timeout=90, cwd=work, check=False)
        if process.returncode:
            # Synthetic-only diagnostic; do not dump input XML or SVRL.
            print(process.stderr[-3000:], file=sys.stderr)
            raise ValueError("Saxon execution failed")
        return parse(output_path)

    results = {}
    for profile in ("TEMELFATURA", "TICARIFATURA"):
        document = parse(candidates / (profile + ".xml"))
        report = transform(document, profile)
        results[profile] = summarize(report)
        if not results[profile]["active-pattern"] or not results[profile]["fired-rule"]:
            raise ValueError("Candidate did not exercise official rules")
        results[profile]["rule_hints"] = public_rule_hints(report)
        nodes = document.xpath("/i:Invoice/cbc:ProfileID", namespaces={
            "i":"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2","cbc":CBC})
        if len(nodes) != 1:
            raise ValueError("Fixture profile missing")
        nodes[0].text = "NEWTIC_INVALID_PROFILE_CONTROL"
        bad_report = transform(document, profile + "_invalid_control")
        bad = summarize(bad_report)
        results[profile + "_invalid_control"] = bad
        print(json.dumps({"profile": profile, "candidate": results[profile], "negative_control": bad}), flush=True)
        if not (finding_keys(bad_report) - finding_keys(report)):
            raise ValueError("Negative control did not demonstrate rule coverage")
    # A candidate can legitimately fail (unsigned/placeholder). Do not claim
    # fiscal readiness, and keep observations distinct from validation success.
    print(json.dumps({"official_rules_executed": True, "candidate_results": results,
                      "submission_allowed": False}), flush=True)

if __name__ == "__main__":
    if len(sys.argv) != 4:
        raise SystemExit("Usage: gib_schematron_rehearsal.py ZIP CANDIDATES WORK")
    main(*(pathlib.Path(p).resolve() for p in sys.argv[1:]))
