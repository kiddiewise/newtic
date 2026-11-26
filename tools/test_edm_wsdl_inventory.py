"""Local tests for the metadata-only EDM schema inspection."""
import unittest
import xml.etree.ElementTree as ET
from edm_wsdl_inventory import contract_details, relative_order

class ContractInventoryTest(unittest.TestCase):
    def test_named_and_inline_schema_sequences_are_resolved(self):
        root=ET.fromstring('''<w:definitions xmlns:w="http://schemas.xmlsoap.org/wsdl/"
        xmlns:x="http://www.w3.org/2001/XMLSchema" xmlns:t="http://tempuri.org/"
        xmlns:s="http://schemas.xmlsoap.org/wsdl/soap/">
        <w:types><x:schema targetNamespace="http://tempuri.org/">
        <x:element name="GetInvoiceRequest" type="t:Req"/>
        <x:complexType name="Req"><x:sequence><x:element name="REQUEST_HEADER"/>
        <x:element name="INVOICE_SEARCH_KEY"><x:complexType><x:sequence>
        <x:element name="UUID"/><x:element name="DIRECTION"/>
        </x:sequence></x:complexType></x:element><x:element name="HEADER_ONLY"/>
        <x:element name="INVOICE_CONTENT_TYPE"/></x:sequence></x:complexType>
        <x:element name="GetInvoiceResponse"><x:complexType><x:sequence>
        <x:element name="INVOICE"/></x:sequence></x:complexType></x:element>
        </x:schema></w:types>
        <w:binding name="Fixture"><w:operation name="GetInvoice">
        <s:operation soapAction="GetInvoiceRequest"/></w:operation></w:binding>
        </w:definitions>''')
        result=contract_details(root)["GetInvoice"]
        self.assertEqual(result["soap11_actions"],["GetInvoiceRequest"])
        self.assertEqual(result["request_fields"],["REQUEST_HEADER","INVOICE_SEARCH_KEY","HEADER_ONLY","INVOICE_CONTENT_TYPE"])
        self.assertEqual(result["request_nested_fields"]["INVOICE_SEARCH_KEY"],["UUID","DIRECTION"])
        self.assertEqual(result["response_fields"],["INVOICE"])

    def test_unknown_order_never_counts_as_success(self):
        self.assertIsNone(relative_order(None,["UUID"]))
        self.assertFalse(relative_order(["UUID","UUID"],["UUID"]))
        self.assertFalse(relative_order(["DIRECTION","UUID"],["UUID","DIRECTION"]))
        self.assertTrue(relative_order(["LIMIT","UUID","SENDER","DIRECTION"],["UUID","DIRECTION"]))


    def test_extension_includes_base_header_before_derived_fields(self):
        root=ET.fromstring('''<w:definitions xmlns:w="http://schemas.xmlsoap.org/wsdl/"
        xmlns:x="http://www.w3.org/2001/XMLSchema" xmlns:t="http://tempuri.org/">
        <w:types><x:schema targetNamespace="http://tempuri.org/">
        <x:complexType name="Base"><x:sequence><x:element name="REQUEST_HEADER"/></x:sequence></x:complexType>
        <x:complexType name="Req"><x:complexContent><x:extension base="t:Base"><x:sequence>
        <x:element name="INVOICE"/></x:sequence></x:extension></x:complexContent></x:complexType>
        <x:element name="GetInvoiceStatusRequest" type="t:Req"/>
        </x:schema></w:types></w:definitions>''')
        self.assertEqual(contract_details(root)["GetInvoiceStatus"]["request_fields"],["REQUEST_HEADER","INVOICE"])

    def test_unresolved_base_is_not_treated_as_complete_schema(self):
        root=ET.fromstring('''<w:definitions xmlns:w="http://schemas.xmlsoap.org/wsdl/"
        xmlns:x="http://www.w3.org/2001/XMLSchema" xmlns:t="http://tempuri.org/">
        <w:types><x:schema><x:element name="GetInvoiceStatusRequest"><x:complexType>
        <x:complexContent><x:extension base="t:Missing"><x:sequence><x:element name="INVOICE"/></x:sequence>
        </x:extension></x:complexContent></x:complexType></x:element></x:schema></w:types></w:definitions>''')
        self.assertIsNone(contract_details(root)["GetInvoiceStatus"]["request_fields"])

if __name__=="__main__":
    unittest.main()
