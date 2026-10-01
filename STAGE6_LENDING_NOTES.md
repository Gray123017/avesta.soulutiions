# Stage 6 — Lending improvements

Implemented on top of Stage 5.

- Added a borrower pre-application checklist.
- Added clearer calculator disclosure and made the displayed interest rate read-only.
- Added a live commitment summary showing principal, interest, and total repayable.
- Corrected document upload guidance to match the hardened backend: JPG, PNG, WebP, PDF; 8 MB each.
- Added server-authoritative loan calculations on new submissions. The API derives the canonical interest rate from the selected duration and recomputes interest and total repayment instead of trusting browser-supplied figures.
- Existing lending terms, approval workflow, security options, repayment methods, borrower portal, staff/admin flow, and Stage 5 IT assistant are retained.

## Important pre-production review
Financial product wording, rates, late-payment terms, collateral/default clauses, privacy wording, and regulatory disclosures should be reviewed by the business and appropriate Zambia-qualified legal/compliance advisers before production launch. This build improves software integrity and UX; it does not certify legal or regulatory compliance.
