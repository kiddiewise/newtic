"""Read-only EDM test WSDL metadata inventory. No credentials or SOAP requests."""
import json
import pathlib
import sys
import xml.etree.ElementTree as ET

WSDL = "http://schemas.xmlsoap.org/wsdl/"
XSD = "http://www.w3.org/2001/XMLSchema"
SOAP = "http://schemas.xmlsoap.org/wsdl/soap/"
SOAP12 = "http://schemas.xmlsoap.org/wsdl/soap12/"
TARGETS = ("Login", "Logout", "GetCompany", "CheckUser", "GetInvoiceStatus",
           "LoadInvoice", "LoadInvoiceModel", "SendInvoice", "GetInvoice", "GetInvoiceSerial")


def contract_details(root):
    """Inventory local declarations only; no imports or remote type resolution."""
    schema_nodes = root.findall(".//{%s}schema" % XSD)
    def named_nodes(tag, name):
        return [node for schema in schema_nodes for node in schema.findall("{%s}%s" % (XSD, tag))
                if node.get("name") == name]

    def complex_type(element):
        inline = element.find("{%s}complexType" % XSD)
        if inline is not None:
            return inline
        type_name = element.get("type", "").split(":")[-1]
        candidates = named_nodes("complexType", type_name)
        return candidates[0] if len(candidates) == 1 else None

    def sequence_for(element):
        ctype = complex_type(element)
        if ctype is None:
            return None
        seq = ctype.find("{%s}sequence" % XSD)
        if seq is None:
            seq = ctype.find("{%s}complexContent/{%s}extension/{%s}sequence" % (XSD,XSD,XSD))
        return None if seq is None else seq.findall("{%s}element" % XSD)

    def fields(nodes):
        if nodes is None:
            return None
        return [item.get("name") or item.get("ref", "").split(":")[-1] for item in nodes]

    operations = {}
    for name in ("GetInvoice", "GetInvoiceStatus", "LoadInvoice", "LoadInvoiceModel", "SendInvoice"):
        request = named_nodes("element", name + "Request")
        response = named_nodes("element", name + "Response")
        req_seq = sequence_for(request[0]) if len(request) == 1 else None
        res_seq = sequence_for(response[0]) if len(response) == 1 else None
        actions = sorted({item.get("soapAction") for op in root.findall(".//{%s}binding/{%s}operation" % (WSDL,WSDL))
                          if op.get("name") == name for item in op.findall("{%s}operation" % SOAP)
                          if item.get("soapAction")})
        nested = {}
        for item in req_seq or []:
            field = item.get("name")
            if field in ("INVOICE_SEARCH_KEY", "INVOICE", "REQUEST_HEADER"):
                nested[field] = fields(sequence_for(item))
        operations[name] = {"soap11_actions": actions, "request_fields":fields(req_seq),
                            "response_fields":fields(res_seq), "request_nested_fields":nested}
    return operations

def relative_order(actual, intended):
    """None = local metadata incomplete; do not turn unknown into success."""
    if actual is None:
        return None
    if any(actual.count(item) != 1 for item in intended):
        return False
    indexes = [actual.index(item) for item in intended]
    return indexes == sorted(indexes)

def inspect(path):
    data = pathlib.Path(path).read_bytes()
    if not data or len(data) > 16 * 1024 * 1024 or b"\x00" in data or b"<!DOCTYPE" in data.upper() or b"<!ENTITY" in data.upper():
        raise ValueError("Unsafe or oversized WSDL")
    root = ET.fromstring(data)
    if root.tag != "{%s}definitions" % WSDL:
        raise ValueError("Endpoint did not return WSDL definitions")
    names = {item.get("name") for item in root.findall(".//{%s}portType/{%s}operation" % (WSDL,WSDL))}
    binding_names = {item.get("name") for item in root.findall(".//{%s}binding/{%s}operation" % (WSDL,WSDL))}
    schemas = {item.get("name") for item in root.findall(".//{%s}schema/{%s}element" % (XSD,XSD))}
    imports = len(root.findall(".//{%s}import" % WSDL)) + len(root.findall(".//{%s}import" % XSD)) + len(root.findall(".//{%s}include" % XSD))
    # Do not follow imports or trust their URLs. Do not output endpoint
    # addresses, arbitrary schema content, session IDs, or server errors.
    result = {}
    for name in TARGETS:
        result[name] = {"port_operation_seen":name in names,
                        "binding_operation_seen":name in binding_names,
                        "request_element_seen":name+"Request" in schemas,
                        "response_element_seen":name+"Response" in schemas}
    contracts = contract_details(root)
    # Compare selected serialized elements, not full schema validation.
    expected = {
        "GetInvoice": ["REQUEST_HEADER","INVOICE_SEARCH_KEY","HEADER_ONLY","INVOICE_CONTENT_TYPE"],
        "GetInvoiceStatus": ["REQUEST_HEADER","INVOICE"],
        "LoadInvoice": ["REQUEST_HEADER","INVOICE","SENDER","RECEIVER","GENERATEINVOICEIDONLOAD"],
    }
    comparisons = {}
    for name, sequence in expected.items():
        contract = contracts[name]
        comparisons[name] = {"request_relative_order_matches":relative_order(contract["request_fields"],sequence),
                             "soapaction_matches":name+"Request" in contract["soap11_actions"] if contract["soap11_actions"] else None}
    comparisons["GetInvoice"]["search_key_relative_order_matches"] = relative_order(
        contracts["GetInvoice"]["request_nested_fields"].get("INVOICE_SEARCH_KEY"),
        ["LIMIT","UUID","SENDER","RECEIVER","READ_INCLUDED","DIRECTION","ISARCHIVED"])
    print(json.dumps({"contract_details":contracts, "newtic_contract_comparison":comparisons,
                      "comparison_is_metadata_only":True}, indent=2))
    print(json.dumps({"wsdl_metadata_received":True,"unresolved_import_count":imports,
                      "operations":result,"actual_operation_called":False,
                      "account_entitlement_confirmed":False,"signing_policy_confirmed":False}, indent=2))
    if not names and not binding_names:
        raise ValueError("No local operations; imported WSDL needs separate review")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: edm_wsdl_inventory.py WSDL")
    inspect(sys.argv[1])
