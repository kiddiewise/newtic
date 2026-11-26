# EDM invoice integration — foundation and acceptance gates

Sources supplied by merchant: EDM E-Fatura Web API v4.2.1; v4 Request-Response; Sıkça Sorulan Sorular; service endpoint list; Postman collection. No credentials or sample personal data retained.

- SOAP Login, Logout, CheckUser, GetCompany (series and enabled products), CheckCounter, LoadInvoice, SendInvoice, GetInvoice, GetInvoiceStatus. Use the exact test service contract and verify before connecting production. REST Swagger is also provided; transport decision pending a successful read-only sandbox probe.
- EARCHIVE and INTERNETSALES flags and INTERNETSALESDETAILS include webAdresi, odemeSekli, odemeAracisiAdi when applicable, odemeTarihi and carrier identity/dispatch date. Do not invent these fields.
- Request-response example returns RECEIVER 11111111111 for an EARSIVFATURA; only use this fallback when transaction-specific legal eligibility is confirmed. Not a customer identity or an e-Fatura recipient alias.
- LoadInvoice with GENERATEINVOICEIDONLOAD can allocate an invoice serial; GetCompany returns series definitions. The documented special Invoice/ID sentinel is ABC2009123456789. Verify series behavior in test; never generate an uncoordinated fiscal number locally.
- Keep a durable unique UUID, local invoice state, immutable monetary snapshot and transaction lock before any send; uncertain send => query exact UUID, not another invoice. EDM acknowledgement is not final GIB success.
- Current foundation: optional encrypted checkout TCKN, no flash/JSON exposure, no saved-address copy; editable product VAT defaults to 20%, immutable new order line VAT snapshot, historical rows remain null. Prices are treated as VAT-inclusive for future invoice calculations. Shipping and installment tax policy, high-value eligibility, legal notices, retention/access audit, signed XML/PDF delivery and refunds remain release gates.
- No automatic or live invoicing enabled by this change. Never invoice historical orders automatically. Test and live credentials and invoice records must be separated.

## Phase 2 — read-only SOAP test connection (v1.0.33)
Based on supplied EDM E-Fatura Web API v4 Request-Response §§1.1, 1.2, 1.12 and Postman SOAPAction headers. Fixed test endpoint, encrypted independent settings, read-only login/counter/logout, strict SOAP response handling, bounded timeouts, no redirect or retry, no raw fault display, revision guard. Negative balance is preserved and is not proof of eligibility to issue invoices. Test fixtures use synthetic credentials; actual EDM account access must be verified via admin. No live endpoint, sending operation, or scheduler is enabled. GetCompany and CheckUser are deferred until sandbox authentication is verified.
