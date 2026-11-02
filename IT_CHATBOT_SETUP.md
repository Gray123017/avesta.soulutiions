# G.I.T OpenAI connection on Hostinger

Saved troubleshooting stays available without an API key. AI is opt-in: only the current question is sent, never customer records or chat history.

Put `avesta-secrets.php` one directory ABOVE this site's `public_html`, returning a PHP array with `OPENAI_API_KEY`. Alternatively set that server environment variable. Never commit the key, put it in HTML/JavaScript or a public folder. PHP cURL must be enabled. This migration keeps the existing credential loading unchanged; it does not create or rotate a key.

After an approved deployment, open `/assistant-api.php?action=status`. `configured: true` means a plausible key was found and cURL is available; it does not validate the key, billing or model access. The response never returns the key. The migration's version marker is `20261016-luna`. Select “Use AI and online sources” in the chatbot and send a non-sensitive test question to verify the provider.

The connector uses OpenAI Responses (`POST /v1/responses`) with `gpt-6-luna`, `reasoning.effort: none`, `web_search` with low search context, `tool_choice: required`, `max_tool_calls: 1`, and up to 800 output tokens. Explicit `none` avoids Luna's default medium reasoning and preserves the existing non-reasoning response budget. Search is required with a one-tool-call ceiling; provider errors/refusals can still prevent a successful search or answer. No automatic model fallback or paid retry is added.

API usage and search are billable to the key's project. Configure project spending controls in OpenAI separately; this deployment does not alter them. Site-side caps remain 10 attempts/hour/IP and 100 attempts/day total. Shared networks share the hourly cap. Rate state is PHP-guarded, IPs are hashed, no question text is stored, and failure to save counters blocks paid calls. Multi-server deployments require a shared limiter.

AI questions may be used by the search service; do not include secrets or personal information. `store: false` disables storing the response object, not every provider retention mechanism. Responses display source links, or disclose when none was cited. Troubleshooting remains advice only; the assistant cannot change devices, records or lending terms.

If OpenAI rejects the key, update the private file. For insufficient quota check API billing. For missing cURL enable the PHP cURL extension in Hostinger. For model access errors, verify that the same project's key can use `gpt-6-luna` and Responses web search. Uncheck AI to use saved guidance after any error.

## Retirement and compatibility review — 16 October 2026

OpenAI's 14 October notice and [API deprecations page](https://developers.openai.com/api/docs/deprecations) identify **14 January 2027** as the shutdown date for GPT-4o, GPT-4o mini, GPT-4.1 and GPT-4.1 mini. The published replacement is `gpt-6-luna`. Complete production acceptance and the approved switch before that date.

The inspected GitHub main baseline was `9d6eba2591d3a79fb18bce5887da019cea782101`. Its `assistant-lib.php` hard-coded `gpt-4.1-mini`. Its setup guide described required search, but `assistant-api.php` actually used `tool_choice: auto`. This migration corrects that discrepancy. The Hostinger files, account permissions, billing and live AI success have **not** been verified by these offline tests.

| Model | Responses + web search | Input / cached input / output, USD per 1M tokens | Migration consequence |
| --- | --- | --- | --- |
| `gpt-4.1-mini` (old baseline) | Existing integration; retirement pending | $0.40 / $0.10 / $1.60 | Must be replaced before 14 January 2027. |
| **`gpt-6-luna` (selected)** | Supported | **$0.10 / $0.01 / $0.50** | Keep endpoint and text/citation format; explicitly set reasoning to `none`; require search. |
| `gpt-6.1-sol` (evaluated alternative) | Supported | $2.00 / $0.10 / $10.00 | No `none` reasoning support. Would require a reasoning budget/latency evaluation; not selected for this bounded help widget. |

Prices above are Standard short-context text rates checked 16 October 2026, not account-specific quotes. [Luna](https://developers.openai.com/api/docs/models/gpt-6-luna) supports the required tools and a non-reasoning setting. [Sol](https://developers.openai.com/api/docs/models/gpt-6.1-sol) costs more and requires reasoning. The [GPT-6 migration guide](https://developers.openai.com/api/docs/guides/latest-model) supports preserving effective reasoning effort. Actual answer quality and latency require staging evaluation; API compatibility alone does not establish them. The documented Luna identifier is an alias; no dated snapshot was selected or invented.

### Cost implications

At equal text-token counts, Luna's uncached input rate is 75% lower and output rate is 68.75% lower than [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini). This does **not** guarantee the same savings on the total bill. [Web search](https://developers.openai.com/api/docs/pricing) costs $10 per 1,000 calls plus search-content input tokens. GPT-4.1 mini bills search content as a fixed 8,000-token block per non-preview search call; Luna uses model-rate search tokens. GPT-6 cache writes, when applicable, have a separate 1.25x input rate ($0.125/M for Luna).

Illustration only: with 2,000 uncached prompt tokens, one search, 8,000 billed search-content tokens, and 800 output tokens per answer, without caching or premium processing:

| Model | Estimated USD / answer | Estimated USD / 1,000 answers |
| --- | ---: | ---: |
| GPT-4.1 mini | $0.01528 | $15.28 |
| GPT-6 Luna | $0.01140 | $11.40 |

For Luna the 8,000 search tokens are an assumption, not a fixed charge or measured usage. Enforcing required search can increase the number of billable calls relative to the old `auto` behavior. The unchanged request caps limit attempts, not a fixed dollar budget; review actual usage and project spending controls during acceptance.

## Verification and release gate

Do not merge or deploy this migration without the owner's approval. Keep it separate from the open design/software PRs. The existing GitHub workflow checks source and tests; it contains no deployment step. Hostinger's separate Git auto-deployment setting remains unverified and must be checked before an approved release.

Offline checks (Node 22+ and PHP CLI; no OpenAI key required):

```sh
npm ci --ignore-scripts
npm run build
npm test
node scripts/check-release-safety.mjs
php -l assistant-lib.php
php -l assistant-api.php
```

`tests/assistant-api.test.mjs` executes the actual PHP endpoint in a temporary local server, with PHP extensions disabled and a test-only cURL substitute. It checks the request model and search requirement, output/transport limits, input-only data flow, scope, citations, sanitized errors, readiness, opt-in/origin/header validation, secret rejection and fail-closed rate limiting. It fails if PHP is unavailable. Existing tests retain saved-guidance, safe rendering, navigation, private-key loading and global daily-cap coverage. No production transport injection point is added. These tests cannot prove provider model access or response quality.

Before requesting release approval, on a separately authorized staging copy:

1. Compare the two assistant PHP files with Hostinger, retain a rollback copy, and confirm auto-deploy is disabled. Preserve the private key, `assistant_rate.php`, all customer/runtime files, saved help JSON, frontend assets and software downloads. Do not overwrite the live tree from this GitHub baseline without reconciliation.
2. Confirm PHP cURL, existing project billing and access to Luna plus Responses web search. The current key-loading convention still requires the parent private file or environment variable. Do not relocate a key into a public directory to work around hosting restrictions.
3. Check the version marker and readiness endpoint. With AI unchecked, verify saved printer/Wi-Fi troubleshooting works without a provider call.
4. Opt into AI with a non-sensitive printer question asking for an official vendor source. Verify a completed web search in the provider result/usage, a useful answer and a clickable HTTPS citation. Check timeout behavior within the retained 8-second connect / 28-second total limits. Record actual usage and latency without saving secrets or personal questions.
5. Test both scopes: IT-only loan questions must redirect; mixed IT/loan questions must not answer the lending portion in IT scope; general Avesta questions must use published site facts. Test an instruction to ignore the rules, unsafe repair advice and a request for private account balances. Require safe handling with no invented access or loan decisions.
6. Verify follow-up navigation, minimize/reopen, source escaping, and recovery to saved guidance after a provider failure. Confirm saved troubleshooting and the software catalogue match the pre-migration version.
7. Record the results and obtain explicit approval before merging and before deploying. `configured: true` or passing CI alone is not a live acceptance result.

Rollback: restore only the paired pre-migration `assistant-api.php` and `assistant-lib.php` after approval; leave credentials, rate counters and customer/runtime data intact. The old model is only a temporary rollback option before its retirement and only if it still works for the project. At or after shutdown, keep AI disabled through the server-side credential configuration (preserve the private backup) and use saved guidance until a supported configuration passes acceptance. Do not automatically retry with a retired or more expensive model.

Search behavior and required tool selection are documented in the [Responses web-search guide](https://developers.openai.com/api/docs/guides/tools-web-search).
