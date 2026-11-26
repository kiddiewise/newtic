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
    print(json.dumps({"wsdl_metadata_received":True,"unresolved_import_count":imports,
                      "operations":result,"actual_operation_called":False,
                      "account_entitlement_confirmed":False,"signing_policy_confirmed":False}, indent=2))
    if not names and not binding_names:
        raise ValueError("No local operations; imported WSDL needs separate review")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: edm_wsdl_inventory.py WSDL")
    inspect(sys.argv[1])
