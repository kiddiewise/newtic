"""Regression tests for namespace context propagation; no network or fiscal data."""
import unittest
from lxml import etree
from gib_schematron_rehearsal import preserve_rule_namespaces

XSL = "http://www.w3.org/1999/XSL/Transform"
XS = "http://www.w3.org/2001/XMLSchema"
SCH = "http://purl.oclc.org/dsdl/schematron"

def tree(xml):
    return etree.ElementTree(etree.fromstring(xml.encode()))

class NamespaceContextTest(unittest.TestCase):
    def schema(self):
        return tree(f'<sch:schema xmlns:sch="{SCH}" xmlns:ef="urn:xml-document">'
                    f'<sch:ns prefix="ef" uri="urn:xpath-context"/>'
                    f'<sch:ns prefix="xs" uri="{XS}"/></sch:schema>')

    def stylesheet(self, uri="urn:xpath-context"):
        return tree(f'<xsl:stylesheet xmlns:xsl="{XSL}" xmlns:ef="{uri}" version="1.0">'
                    '<xsl:template match="/"><xsl:if test="xs:date(.)">'
                    '<xsl:value-of select="ef:node"/></xsl:if></xsl:template></xsl:stylesheet>')

    def test_xml_and_xpath_namespaces_are_distinct(self):
        root = preserve_rule_namespaces(self.stylesheet(), self.schema())
        restored = etree.fromstring(etree.tostring(root))
        self.assertEqual(restored.nsmap["ef"], "urn:xpath-context")
        self.assertEqual(restored.nsmap["xs"], XS)
        self.assertEqual(restored.get("version"), "1.0")
        self.assertEqual(restored.xpath("//xsl:if/@test", namespaces={"xsl": XSL}), ["xs:date(.)"])
        self.assertEqual(restored.xpath("//xsl:value-of/@select", namespaces={"xsl": XSL}), ["ef:node"])

    def test_conflicting_generated_binding_fails_closed(self):
        with self.assertRaisesRegex(ValueError, "Conflicting generated XPath namespace: ef"):
            preserve_rule_namespaces(self.stylesheet("urn:wrong"), self.schema())

    def test_missing_xs_declaration_fails_closed(self):
        with self.assertRaisesRegex(ValueError, "Official XML Schema namespace missing"):
            preserve_rule_namespaces(self.stylesheet(), tree(f'<sch:schema xmlns:sch="{SCH}"/>'))

if __name__ == "__main__":
    unittest.main()
