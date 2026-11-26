# EDM invoice integration — foundation and acceptance gates

Sources supplied by merchant: EDM E-Fatura Web API v4.2.1; v4 Request-Response; Sıkça Sorulan Sorular; service endpoint list; Postman collection. No credentials or sample personal data retained.

- SOAP Login, Logout, CheckUser, GetCompany (series and enabled products), CheckCounter, LoadInvoice, SendInvoice, GetInvoice, GetInvoiceStatus. Use the exact test service contract and verify before connecting production. REST Swagger is also provided; transport decision pending a successful read-only sandbox probe.
- EARCHIVE and INTERNETSALES flags and INTERNETSALESDETAILS include webAdresi, odemeSekli, odemeAracisiAdi when applicable, odemeTarihi and carrier identity/dispatch date. Do not invent these fields.
- Request-response example returns RECEIVER 11111111111 for an EARSIVFATURA; only use this fallback when transaction-specific legal eligibility is confirmed. Not a customer identity or an e-Fatura recipient alias.
- LoadInvoice with GENERATEINVOICEIDONLOAD can allocate an invoice serial; GetCompany returns series definitions. The documented special Invoice/ID sentinel is ABC2009123456789. Verify series behavior in test; never generate an uncoordinated fiscal number locally.
- Keep a durable unique UUID, local invoice state, immutable monetary snapshot and transaction lock before any send; uncertain send => query exact UUID, not another invoice. EDM acknowledgement is not final GIB success.
- Current foundation: optional encrypted checkout TCKN, no flash/JSON exposure, no saved-address copy; editable product VAT defaults to 20%, immutable new order line VAT snapshot, historical rows remain null. Prices are treated as VAT-inclusive for future invoice calculations. Shipping and installment tax policy, high-value eligibility, legal notices, retention/access audit, signed XML/PDF delivery and refunds remain release gates.
- No automatic or live invoicing enabled by this change. Never invoice historical orders automatically. Test and live credentials and invoice records must be separated.
